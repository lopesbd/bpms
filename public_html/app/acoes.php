<?php
declare(strict_types=1);

/*
 * Automações das tarefas de serviço (serviceTask / sendTask / scriptTask).
 * Cada ação devolve ['variaveis' => [...novas variáveis...], 'resumo' => 'texto para o histórico'].
 */

const ACOES = [
    'email' => 'Enviar e-mail',
    'notificacao' => 'Notificar no sistema',
    'variavel' => 'Definir variáveis',
    'webhook' => 'Chamar API externa (webhook)',
];

function executar_acao(array $c, array $vars, array $inst): array
{
    switch ($c['acao'] ?? '') {
        case 'email':
            return acao_email($c['email'] ?? [], $vars, $inst);
        case 'notificacao':
            return acao_notificacao($c['notificacao'] ?? [], $vars, $inst);
        case 'variavel':
            return acao_variaveis($c['variaveis'] ?? [], $vars);
        case 'webhook':
            return acao_webhook($c['webhook'] ?? [], $vars);
    }
    throw new ErroNegocio('Automação sem ação configurada.');
}

/** Resolve destinatários (e-mails ou ids de usuário) conforme o tipo escolhido. */
function destinatarios(array $c, array $vars, array $inst, bool $comoIds): array
{
    $ids = [];
    $emails = [];
    $iniciador = (int) $inst['iniciado_por'];
    switch ($c['para_tipo'] ?? 'iniciador') {
        case 'iniciador':
            $ids[] = $iniciador;
            break;
        case 'gestor':
            $ids[] = (int) valor('SELECT gestor_id FROM usuarios WHERE id = ?', [$iniciador]) ?: $iniciador;
            break;
        case 'usuario':
            $ids[] = (int) ($c['usuario_id'] ?? 0);
            break;
        case 'grupo':
            $ids = array_map('intval', array_column(linhas(
                'SELECT ug.usuario_id FROM usuario_grupos ug JOIN usuarios u ON u.id = ug.usuario_id WHERE ug.grupo_id = ? AND u.ativo = 1',
                [(int) ($c['grupo_id'] ?? 0)]
            ), 'usuario_id'));
            break;
        case 'fixo':
            $emails = preg_split('/[\s,;]+/', modelo_texto((string) ($c['para'] ?? ''), $vars)) ?: [];
            break;
        case 'variavel':
            $emails = [(string) ($vars[$c['para_variavel'] ?? ''] ?? '')];
            break;
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if ($comoIds) {
        return $ids;
    }
    foreach ($ids as $id) {
        $emails[] = (string) valor('SELECT email FROM usuarios WHERE id = ?', [$id]);
    }
    return array_values(array_unique(array_filter($emails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
}

function acao_email(array $c, array $vars, array $inst): array
{
    $para = destinatarios($c, $vars, $inst, false);
    if (!$para) {
        throw new ErroNegocio('nenhum destinatário de e-mail válido.');
    }
    $assunto = modelo_texto((string) ($c['assunto'] ?? 'Atualização do processo {{codigo}}'), $vars);
    $corpo = modelo_texto((string) ($c['corpo'] ?? ''), $vars);
    foreach ($para as $p) {
        enviar_email($p, $assunto, $corpo);
    }
    return ['variaveis' => [], 'resumo' => 'e-mail "' . $assunto . '" enviado para ' . implode(', ', $para) . '.'];
}

function acao_notificacao(array $c, array $vars, array $inst): array
{
    $ids = destinatarios($c, $vars, $inst, true);
    $titulo = modelo_texto((string) ($c['titulo'] ?? 'Atualização em {{codigo}}'), $vars);
    $texto = modelo_texto((string) ($c['texto'] ?? ''), $vars);
    foreach ($ids as $id) {
        notificar($id, $titulo, $texto, 'processo.php?id=' . $inst['id']);
    }
    return ['variaveis' => [], 'resumo' => 'notificação enviada para ' . count($ids) . ' pessoa(s).'];
}

function acao_variaveis(array $lista, array $vars): array
{
    $novas = [];
    foreach ($lista as $item) {
        $nome = nome_variavel((string) ($item['nome'] ?? ''));
        if ($nome === '') {
            continue;
        }
        $v = calcular_valor((string) ($item['valor'] ?? ''), array_merge($vars, $novas));
        $novas[$nome] = $v;
    }
    $resumo = $novas ? implode(', ', array_map(fn($k, $v) => "$k = " . valor_exibicao($v), array_keys($novas), $novas)) : 'nada a definir';
    return ['variaveis' => $novas, 'resumo' => $resumo . '.'];
}

/**
 * Valor de uma variável definida por automação: aceita {{variaveis}} e,
 * se o resultado for uma conta simples (+ - * / parênteses), calcula.
 */
function calcular_valor(string $modelo, array $vars)
{
    $num = preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/u', function ($m) use ($vars) {
        $v = $vars[$m[1]] ?? '';
        $n = numero_ou_null(is_scalar($v) ? (string) $v : '');
        return $n === null ? '§' : (string) $n;
    }, $modelo) ?? '';
    if (preg_match('/^[\d\s.+\-*\/()]+$/', $num) && preg_match('/\d/', $num) && strpbrk($num, '+-*/') !== false) {
        $r = conta_segura($num);
        if ($r !== null) {
            return round($r, 6);
        }
    }
    $texto = modelo_texto($modelo, $vars);
    $n = numero_ou_null($texto);
    return $n !== null && preg_match('/^-?[\d.,]+$/', trim($texto)) ? $n : $texto;
}

/** Avaliador de expressões aritméticas sem eval (shunting-yard). */
function conta_segura(string $expr): ?float
{
    preg_match_all('/\d+(?:\.\d+)?|[+\-*\/()]/', $expr, $m);
    $saida = [];
    $ops = [];
    $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
    $anterior = null;
    foreach ($m[0] as $tk) {
        if (is_numeric($tk)) {
            $saida[] = (float) $tk;
        } elseif ($tk === '(') {
            $ops[] = $tk;
        } elseif ($tk === ')') {
            while ($ops && end($ops) !== '(') {
                $saida[] = array_pop($ops);
            }
            if (!$ops) {
                return null;
            }
            array_pop($ops);
        } else {
            if ($tk === '-' && ($anterior === null || in_array($anterior, ['+', '-', '*', '/', '('], true))) {
                $saida[] = 0.0; // menos unário
            }
            while ($ops && end($ops) !== '(' && $prec[end($ops)] >= $prec[$tk]) {
                $saida[] = array_pop($ops);
            }
            $ops[] = $tk;
        }
        $anterior = $tk;
    }
    while ($ops) {
        $op = array_pop($ops);
        if ($op === '(') {
            return null;
        }
        $saida[] = $op;
    }
    $pilha = [];
    foreach ($saida as $s) {
        if (is_float($s)) {
            $pilha[] = $s;
            continue;
        }
        if (count($pilha) < 2) {
            return null;
        }
        $b = array_pop($pilha);
        $a = array_pop($pilha);
        if ($s === '/' && $b == 0) {
            return null;
        }
        $pilha[] = match ($s) { '+' => $a + $b, '-' => $a - $b, '*' => $a * $b, '/' => $a / $b };
    }
    return count($pilha) === 1 ? $pilha[0] : null;
}

function acao_webhook(array $c, array $vars): array
{
    $url = trim(modelo_texto((string) ($c['url'] ?? ''), array_map(fn($v) => is_scalar($v) ? rawurlencode((string) $v) : '', $vars)));
    if (!preg_match('#^https?://#i', $url)) {
        throw new ErroNegocio('URL do webhook inválida.');
    }
    $metodo = in_array($c['metodo'] ?? 'POST', ['GET', 'POST', 'PUT', 'PATCH'], true) ? $c['metodo'] : 'POST';
    $corpo = null;
    if ($metodo !== 'GET') {
        $modelo = trim((string) ($c['corpo'] ?? ''));
        if ($modelo === '') {
            $corpo = json_enc(array_filter($vars, fn($k) => $k[0] !== '_', ARRAY_FILTER_USE_KEY));
        } else {
            // valores escapados para JSON antes de entrar no modelo
            $esc = array_map(fn($v) => is_scalar($v) ? substr(json_enc((string) $v), 1, -1) : '', $vars);
            $corpo = modelo_texto($modelo, $esc);
        }
    }
    $cabecalhos = ['Content-Type: application/json', 'Accept: application/json', 'User-Agent: BPMS/1.0'];
    if (!empty($c['autorizacao'])) {
        $cabecalhos[] = 'Authorization: ' . str_replace(["\r", "\n"], '', (string) $c['autorizacao']);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => $cabecalhos,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
    }
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        throw new ErroNegocio('falha ao chamar ' . $url . ': ' . $erro);
    }
    if ($status < 200 || $status >= 300) {
        throw new ErroNegocio('a API respondeu com status ' . $status . '.');
    }
    $novas = [];
    $salvar = nome_variavel((string) ($c['salvar_em'] ?? ''));
    if ($salvar !== '') {
        $json = json_decode((string) $resp, true);
        if (is_array($json)) {
            foreach ($json as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $novas[$salvar . '_' . nome_variavel((string) $k)] = $v;
                }
            }
            $novas[$salvar] = mb_substr((string) $resp, 0, 2000);
        } else {
            $novas[$salvar] = mb_substr((string) $resp, 0, 2000);
        }
    }
    return ['variaveis' => $novas, 'resumo' => "$metodo $url → HTTP $status."];
}

function url_absoluta(string $caminho): string
{
    $base = (string) config('url_publica', '');
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = (https() ? 'https://' : 'http://') . $host . rtrim((string) config('base_url', ''), '/');
    }
    return rtrim($base, '/') . '/' . ltrim($caminho, '/');
}

function enviar_email(string $para, string $assunto, string $corpo): void
{
    $remetente = cfg_banco('email_remetente') ?: (string) config('email_remetente', '');
    $empresa = cfg_banco('empresa', 'BPMS');
    if (config('email_simulado')) {
        @mkdir(STORAGE_DIR, 0775, true);
        file_put_contents(STORAGE_DIR . '/emails.log', '[' . agora() . "] Para: $para\nAssunto: $assunto\n$corpo\n\n", FILE_APPEND);
        return;
    }
    if ($remetente === '') {
        throw new ErroNegocio('configure o e-mail remetente em Configurações › Geral.');
    }
    $cab = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($empresa) . ' <' . $remetente . '>',
        'Reply-To: ' . $remetente,
    ];
    $ok = mail($para, mb_encode_mimeheader($assunto, 'UTF-8'), $corpo, implode("\r\n", $cab), '-f' . $remetente);
    if (!$ok) {
        throw new ErroNegocio('o servidor recusou o envio do e-mail para ' . $para . '.');
    }
}
