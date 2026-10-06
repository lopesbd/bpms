<?php
declare(strict_types=1);

/*
 * Formulários dinâmicos montados pelo administrador no modelador.
 * Cada campo grava numa variável do processo, que pode ser usada nas
 * condições dos gateways, nos e-mails e nos títulos ({{variavel}}).
 */

const TIPOS_CAMPO = [
    'texto' => 'Texto curto',
    'textarea' => 'Texto longo',
    'numero' => 'Número',
    'moeda' => 'Valor (R$)',
    'data' => 'Data',
    'email' => 'E-mail',
    'selecao' => 'Lista de opções',
    'radio' => 'Escolha única',
    'checkbox' => 'Sim / Não',
    'arquivo' => 'Anexo',
    'titulo' => 'Título de seção',
    'info' => 'Texto informativo',
];

function nome_variavel(string $s): string
{
    $s = strtolower(trim($s));
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    $s = preg_replace('/[^a-z0-9_]+/', '_', $s) ?? '';
    return substr(trim($s, '_'), 0, 50);
}

function campo_tem_valor(array $campo): bool
{
    return !in_array($campo['tipo'] ?? '', ['titulo', 'info'], true) && nome_variavel($campo['variavel'] ?? '') !== '';
}

/** Valida e normaliza o que veio do navegador. Lança ErroNegocio com a lista de problemas. */
function formulario_validar(array $campos, array $dados, array $varsAtuais): array
{
    $valores = [];
    $erros = [];
    $u = usuario_atual();
    foreach ($campos as $c) {
        if (!campo_tem_valor($c) || !empty($c['somente_leitura'])) {
            continue;
        }
        $var = nome_variavel($c['variavel']);
        $rotulo = $c['rotulo'] ?? $var;
        $bruto = $dados[$var] ?? null;
        $obrig = !empty($c['obrigatorio']);
        switch ($c['tipo']) {
            case 'numero':
            case 'moeda':
                $s = is_scalar($bruto) ? trim((string) $bruto) : '';
                if ($s === '') {
                    $v = null;
                } else {
                    $v = numero_ou_null($s);
                    if ($v === null) {
                        $erros[] = "$rotulo: informe um número válido.";
                        continue 2;
                    }
                    if ($c['tipo'] === 'moeda') {
                        $v = round($v, 2);
                    } elseif (floor($v) == $v && abs($v) < PHP_INT_MAX) {
                        $v = (int) $v;
                    }
                }
                break;
            case 'data':
                $s = is_scalar($bruto) ? trim((string) $bruto) : '';
                if ($s !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                    $erros[] = "$rotulo: data inválida.";
                    continue 2;
                }
                $v = $s === '' ? null : $s;
                break;
            case 'email':
                $s = is_scalar($bruto) ? trim((string) $bruto) : '';
                if ($s !== '' && !filter_var($s, FILTER_VALIDATE_EMAIL)) {
                    $erros[] = "$rotulo: e-mail inválido.";
                    continue 2;
                }
                $v = $s === '' ? null : mb_substr($s, 0, 160);
                break;
            case 'selecao':
            case 'radio':
                $s = is_scalar($bruto) ? trim((string) $bruto) : '';
                $opcoes = array_map('strval', $c['opcoes'] ?? []);
                if ($s !== '' && !in_array($s, $opcoes, true)) {
                    $erros[] = "$rotulo: opção inválida.";
                    continue 2;
                }
                $v = $s === '' ? null : $s;
                break;
            case 'checkbox':
                $v = filter_var($bruto, FILTER_VALIDATE_BOOLEAN);
                if ($obrig && !$v) {
                    $erros[] = "$rotulo: é preciso marcar esta opção.";
                }
                $valores[$var] = $v;
                continue 2;
            case 'arquivo':
                $ids = array_values(array_filter(array_map('intval', is_array($bruto) ? array_column($bruto, 'id') ?: $bruto : []), fn($i) => $i > 0));
                $lista = [];
                foreach ($ids as $id) {
                    $a = linha('SELECT id, nome_original, enviado_por, instancia_id FROM anexos WHERE id = ?', [$id]);
                    $existente = in_array($id, array_column((array) ($varsAtuais[$var] ?? []), 'id'), false);
                    if ($a && ($existente || ((int) $a['enviado_por'] === (int) ($u['id'] ?? 0) && $a['instancia_id'] === null))) {
                        $lista[] = ['id' => (int) $a['id'], 'nome' => $a['nome_original']];
                    }
                }
                $v = $lista ?: null;
                break;
            case 'textarea':
                $v = is_scalar($bruto) ? texto_limpo($bruto, 5000) : '';
                $v = $v === '' ? null : $v;
                break;
            default:
                $v = is_scalar($bruto) ? texto_limpo($bruto, 500) : '';
                $v = $v === '' ? null : $v;
        }
        if ($obrig && ($v === null || $v === [])) {
            $erros[] = "$rotulo é obrigatório.";
            continue;
        }
        $valores[$var] = $v;
    }
    if ($erros) {
        throw new ErroNegocio(implode("\n", $erros));
    }
    return $valores;
}

function formulario_vincular_anexos(array $campos, array $valores, int $instId, ?int $tarefaId): void
{
    foreach ($campos as $c) {
        if (($c['tipo'] ?? '') !== 'arquivo') {
            continue;
        }
        $var = nome_variavel($c['variavel'] ?? '');
        foreach ((array) ($valores[$var] ?? []) as $a) {
            q('UPDATE anexos SET instancia_id = ?, tarefa_id = COALESCE(tarefa_id, ?), campo = ? WHERE id = ? AND instancia_id IS NULL', [$instId, $tarefaId, $var, $a['id']]);
        }
    }
}

function valor_exibicao($v, string $tipo = 'texto'): string
{
    if ($v === null || $v === '' || $v === []) {
        return '—';
    }
    if (is_bool($v)) {
        return $v ? 'Sim' : 'Não';
    }
    if ($tipo === 'moeda' && is_numeric($v)) {
        return 'R$ ' . number_format((float) $v, 2, ',', '.');
    }
    if ($tipo === 'numero' && is_numeric($v)) {
        return number_format((float) $v, (floor((float) $v) == $v) ? 0 : 2, ',', '.');
    }
    if ($tipo === 'data' && is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return date('d/m/Y', strtotime($v));
    }
    if (is_array($v)) {
        return implode(', ', array_map(fn($x) => is_array($x) ? ($x['nome'] ?? '') : (string) $x, $v));
    }
    return (string) $v;
}

/** Mapa variavel → [rotulo, tipo] de todos os formulários do fluxo. */
function rotulos_variaveis(array $modelo): array
{
    $mapa = [];
    foreach ($modelo['elementos'] as $el) {
        $c = cfg_elemento($modelo, $el['id']);
        foreach ($c['formulario'] ?? [] as $campo) {
            if (campo_tem_valor($campo)) {
                $mapa[nome_variavel($campo['variavel'])] ??= ['rotulo' => $campo['rotulo'] ?? $campo['variavel'], 'tipo' => $campo['tipo']];
            }
        }
        if (!empty($c['decisoes'])) {
            $var = nome_variavel($c['decisao_variavel'] ?? '') ?: 'decisao';
            $mapa[$var] ??= ['rotulo' => 'Decisão: ' . nome_elemento($modelo, $el['id']), 'tipo' => 'texto'];
        }
    }
    return $mapa;
}

function formulario_html(array $campos, array $vars): string
{
    if (!$campos) {
        return '';
    }
    $h = '<div class="form-grid">';
    foreach ($campos as $c) {
        $tipo = $c['tipo'] ?? 'texto';
        $larg = ($c['largura'] ?? 'inteira') === 'metade' ? ' metade' : '';
        $rotulo = e($c['rotulo'] ?? '');
        if ($tipo === 'titulo') {
            $h .= '<h3 class="form-secao">' . $rotulo . '</h3>';
            continue;
        }
        if ($tipo === 'info') {
            $h .= '<div class="form-info">' . nl2br(e(modelo_texto((string) ($c['rotulo'] ?? ''), $vars))) . '</div>';
            continue;
        }
        $var = nome_variavel($c['variavel'] ?? '');
        if ($var === '') {
            continue;
        }
        $atual = $vars[$var] ?? null;
        $obrig = !empty($c['obrigatorio']);
        $ro = !empty($c['somente_leitura']);
        $idc = 'f_' . $var;
        $ajuda = !empty($c['ajuda']) ? '<small class="ajuda">' . e($c['ajuda']) . '</small>' : '';
        $marca = $obrig && !$ro ? '<span class="obrig">obrigatório</span>' : '';
        $h .= '<div class="campo' . $larg . '" data-campo="' . e($var) . '" data-tipo="' . e($tipo) . '">';
        if ($tipo !== 'checkbox') {
            $h .= '<label for="' . $idc . '"><span>' . $rotulo . '</span>' . $marca . '</label>';
        }
        if ($ro) {
            $h .= '<div class="valor-ro">' . e(valor_exibicao($atual, $tipo)) . '</div>' . $ajuda . '</div>';
            continue;
        }
        $req = $obrig ? ' required' : '';
        $ph = !empty($c['placeholder']) ? ' placeholder="' . e($c['placeholder']) . '"' : '';
        $attr = ' id="' . $idc . '" name="' . e($var) . '"' . $req . $ph;
        switch ($tipo) {
            case 'textarea':
                $h .= '<textarea' . $attr . ' rows="4">' . e(is_scalar($atual) ? $atual : '') . '</textarea>';
                break;
            case 'numero':
                $h .= '<input type="number" step="any"' . $attr . ' value="' . e(is_scalar($atual) ? $atual : '') . '">';
                break;
            case 'moeda':
                $v = is_numeric($atual) ? number_format((float) $atual, 2, ',', '.') : '';
                $h .= '<div class="input-prefixo"><span>R$</span><input type="text" inputmode="decimal" data-moeda' . $attr . ' value="' . e($v) . '"></div>';
                break;
            case 'data':
                $h .= '<input type="date"' . $attr . ' value="' . e(is_scalar($atual) ? $atual : '') . '">';
                break;
            case 'email':
                $h .= '<input type="email"' . $attr . ' value="' . e(is_scalar($atual) ? $atual : '') . '">';
                break;
            case 'selecao':
                $h .= '<select' . $attr . '><option value="">Selecione...</option>';
                foreach ($c['opcoes'] ?? [] as $o) {
                    $h .= '<option' . ((string) $atual === (string) $o ? ' selected' : '') . '>' . e($o) . '</option>';
                }
                $h .= '</select>';
                break;
            case 'radio':
                $h .= '<div class="opcoes-radio">';
                foreach ($c['opcoes'] ?? [] as $i => $o) {
                    $h .= '<label class="radio-pill"><input type="radio" name="' . e($var) . '" value="' . e($o) . '"' . ((string) $atual === (string) $o ? ' checked' : '') . ($i === 0 ? $req : '') . '><span>' . e($o) . '</span></label>';
                }
                $h .= '</div>';
                break;
            case 'checkbox':
                $h .= '<label class="switch-linha"><input type="checkbox" id="' . $idc . '" name="' . e($var) . '"' . ($atual ? ' checked' : '') . '><span class="switch"></span><span>' . $rotulo . '</span>' . $marca . '</label>';
                break;
            case 'arquivo':
                $lista = is_array($atual) ? $atual : [];
                $h .= '<div class="upload" data-upload="' . e($var) . '"' . ($obrig ? ' data-obrigatorio' : '') . '>'
                    . '<input type="file" id="' . $idc . '" multiple hidden>'
                    . '<label for="' . $idc . '" class="upload-area">' . icone('clipe', 22) . '<span><strong>Clique ou arraste arquivos aqui</strong><small>Formatos: ' . e(cfg_banco('formatos_anexo')) . ' · até ' . e(cfg_banco('tamanho_max_mb', '10')) . ' MB</small></span></label>'
                    . '<ul class="upload-lista">';
                foreach ($lista as $a) {
                    $h .= '<li data-id="' . (int) $a['id'] . '"><a href="arquivo.php?id=' . (int) $a['id'] . '" target="_blank" rel="noopener">' . e($a['nome']) . '</a><button type="button" class="btn-icone" data-remover-anexo title="Remover">' . icone('x', 14) . '</button></li>';
                }
                $h .= '</ul></div>';
                break;
            default:
                $h .= '<input type="text"' . $attr . ' value="' . e(is_scalar($atual) ? $atual : '') . '" maxlength="500">';
        }
        $h .= $ajuda . '</div>';
    }
    return $h . '</div>';
}
