<?php
declare(strict_types=1);

/*
 * Motor de execução BPMN.
 *
 * A execução anda por "tokens": cada token marca um elemento do diagrama onde a
 * instância está. Tarefas humanas e temporizadores deixam o token "aguardando";
 * automações, gateways e eventos simples são executados na hora e o token segue.
 */

const BPMN_NS = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

/** Tipo BPMN → categoria que o motor sabe executar. */
function categoria_bpmn(string $tipo): ?string
{
    return [
        'startEvent' => 'inicio',
        'endEvent' => 'fim',
        'task' => 'humana',
        'userTask' => 'humana',
        'manualTask' => 'humana',
        'receiveTask' => 'humana',
        'serviceTask' => 'automatica',
        'sendTask' => 'automatica',
        'scriptTask' => 'automatica',
        'businessRuleTask' => 'automatica',
        'exclusiveGateway' => 'gw_exclusivo',
        'parallelGateway' => 'gw_paralelo',
        'inclusiveGateway' => 'gw_inclusivo',
        'intermediateCatchEvent' => 'intermediario',
        'intermediateThrowEvent' => 'passagem',
    ][$tipo] ?? null;
}

/** Lê o XML BPMN e devolve elementos e fluxos já indexados. */
function modelo_ler(string $xml): array
{
    $dom = new DOMDocument();
    $anterior = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
    libxml_use_internal_errors($anterior);
    if (!$ok) {
        throw new ErroNegocio('O desenho do fluxo (XML BPMN) está inválido.');
    }
    $elementos = [];
    $fluxos = [];
    foreach ($dom->getElementsByTagNameNS(BPMN_NS, 'process') as $proc) {
        foreach ($proc->childNodes as $no) {
            if (!$no instanceof DOMElement || $no->namespaceURI !== BPMN_NS) {
                continue;
            }
            $id = $no->getAttribute('id');
            $tipo = $no->localName;
            if ($tipo === 'sequenceFlow') {
                $fluxos[$id] = [
                    'id' => $id,
                    'nome' => trim($no->getAttribute('name')),
                    'origem' => $no->getAttribute('sourceRef'),
                    'destino' => $no->getAttribute('targetRef'),
                ];
                continue;
            }
            if (in_array($tipo, ['laneSet', 'textAnnotation', 'association', 'dataObject', 'dataObjectReference', 'dataStoreReference', 'group', 'documentation', 'extensionElements'], true)) {
                continue;
            }
            $evento = null;
            foreach ($no->childNodes as $filho) {
                if ($filho instanceof DOMElement && str_ends_with($filho->localName, 'EventDefinition')) {
                    $evento = substr($filho->localName, 0, -strlen('EventDefinition'));
                }
            }
            $elementos[$id] = [
                'id' => $id,
                'tipo' => $tipo,
                'categoria' => categoria_bpmn($tipo),
                'nome' => trim($no->getAttribute('name')),
                'evento' => $evento,
                'padrao' => $no->getAttribute('default') ?: null,
                'entradas' => [],
                'saidas' => [],
            ];
        }
    }
    foreach ($fluxos as $f) {
        if (isset($elementos[$f['origem']])) {
            $elementos[$f['origem']]['saidas'][] = $f['id'];
        }
        if (isset($elementos[$f['destino']])) {
            $elementos[$f['destino']]['entradas'][] = $f['id'];
        }
    }
    return ['elementos' => $elementos, 'fluxos' => $fluxos];
}

function modelo_versao(int $versaoId): array
{
    static $cache = [];
    if (!isset($cache[$versaoId])) {
        $v = linha('SELECT xml, config FROM processo_versoes WHERE id = ?', [$versaoId]);
        if (!$v) {
            throw new ErroNegocio('Versão do fluxo não encontrada.');
        }
        $m = modelo_ler($v['xml']);
        $m['config'] = json_decode_arr($v['config']);
        $cache[$versaoId] = $m;
    }
    return $cache[$versaoId];
}

function cfg_elemento(array $modelo, string $id): array
{
    $c = $modelo['config'][$id] ?? [];
    return is_array($c) ? $c : [];
}

function nome_elemento(array $modelo, string $id): string
{
    $el = $modelo['elementos'][$id] ?? null;
    if (!$el) {
        return $id;
    }
    if ($el['nome'] !== '') {
        return $el['nome'];
    }
    return [
        'inicio' => 'Início', 'fim' => 'Fim', 'humana' => 'Tarefa', 'automatica' => 'Automação',
        'gw_exclusivo' => 'Decisão', 'gw_paralelo' => 'Paralelo', 'gw_inclusivo' => 'Inclusivo',
        'intermediario' => 'Temporizador', 'passagem' => 'Evento',
    ][$el['categoria'] ?? ''] ?? $id;
}

/* ===================== Validação para publicar ===================== */

function modelo_validar(string $xml, array $config): array
{
    $erros = [];
    try {
        $m = modelo_ler($xml);
    } catch (ErroNegocio $e) {
        return [$e->getMessage()];
    }
    $m['config'] = $config;
    $inicios = array_filter($m['elementos'], fn($e) => $e['categoria'] === 'inicio');
    $fins = array_filter($m['elementos'], fn($e) => $e['categoria'] === 'fim');
    if (!$inicios) {
        $erros[] = 'O fluxo precisa de um evento de início.';
    }
    if (count($inicios) > 1) {
        $erros[] = 'Use apenas um evento de início.';
    }
    if (!$fins) {
        $erros[] = 'O fluxo precisa de pelo menos um evento de fim.';
    }
    foreach ($m['elementos'] as $el) {
        $nome = '"' . nome_elemento($m, $el['id']) . '"';
        if ($el['categoria'] === null) {
            $erros[] = "O elemento $nome ({$el['tipo']}) ainda não é suportado pelo motor. Troque por uma tarefa, gateway ou evento simples.";
            continue;
        }
        if ($el['categoria'] !== 'inicio' && !$el['entradas']) {
            $erros[] = "$nome não tem nenhuma seta chegando.";
        }
        if ($el['categoria'] !== 'fim' && !$el['saidas']) {
            $erros[] = "$nome não tem nenhuma seta saindo.";
        }
        $c = cfg_elemento($m, $el['id']);
        if ($el['categoria'] === 'humana') {
            $tipo = $c['atribuicao']['tipo'] ?? '';
            if ($tipo === '') {
                $erros[] = "Defina quem executa a tarefa $nome.";
            } elseif ($tipo === 'usuario' && !usuarios_da_atribuicao($c['atribuicao'])) {
                $erros[] = "Escolha uma ou mais pessoas para a tarefa $nome.";
            } elseif ($tipo === 'grupo' && empty($c['atribuicao']['grupo_id'])) {
                $erros[] = "Escolha o grupo responsável pela tarefa $nome.";
            } elseif ($tipo === 'executor' && empty($c['atribuicao']['elemento_ref'])) {
                $erros[] = "Escolha de qual etapa vem o executor da tarefa $nome.";
            }
        }
        if ($el['categoria'] === 'automatica' && empty($c['acao'])) {
            $erros[] = "Escolha o que a automação $nome deve fazer.";
        }
        if ($el['categoria'] === 'intermediario' && $el['evento'] !== 'timer') {
            $erros[] = "O evento intermediário $nome precisa ser um temporizador.";
        }
        if ($el['categoria'] === 'intermediario' && $el['evento'] === 'timer' && (float) ($c['timer']['valor'] ?? 0) <= 0) {
            $erros[] = "Informe o tempo de espera do temporizador $nome.";
        }
        if ($el['categoria'] === 'gw_exclusivo' && count($el['saidas']) > 1) {
            $semRegra = 0;
            foreach ($el['saidas'] as $fid) {
                $fc = cfg_elemento($m, $fid);
                if (empty($fc['regras']) && empty($fc['padrao']) && $el['padrao'] !== $fid) {
                    $semRegra++;
                }
            }
            if ($semRegra > 0) {
                $erros[] = "Na decisão $nome, toda seta de saída precisa de uma condição ou ser marcada como caminho padrão.";
            }
        }
    }
    return array_values(array_unique($erros));
}

/* ===================== Condições ===================== */

function numero_ou_null($v): ?float
{
    if (is_int($v) || is_float($v)) {
        return (float) $v;
    }
    if (!is_string($v)) {
        return null;
    }
    $s = trim($v);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $s) || preg_match('/^-?\d+,\d+$/', $s)) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

function avaliar_regra(array $regra, array $vars): bool
{
    $atual = $vars[$regra['variavel'] ?? ''] ?? null;
    if (is_array($atual)) {
        $atual = $atual['nome'] ?? implode(',', array_map('strval', $atual));
    }
    if (is_bool($atual)) {
        $atual = $atual ? 'sim' : 'não';
    }
    $esperado = modelo_texto((string) ($regra['valor'] ?? ''), $vars);
    $op = $regra['operador'] ?? 'igual';
    $na = numero_ou_null($atual);
    $ne = numero_ou_null($esperado);
    $numerico = $na !== null && $ne !== null;
    $a = mb_strtolower(trim((string) $atual));
    $b = mb_strtolower(trim($esperado));
    switch ($op) {
        case 'igual': return $numerico ? $na == $ne : $a === $b;
        case 'diferente': return $numerico ? $na != $ne : $a !== $b;
        case 'maior': return $numerico && $na > $ne;
        case 'menor': return $numerico && $na < $ne;
        case 'maior_igual': return $numerico && $na >= $ne;
        case 'menor_igual': return $numerico && $na <= $ne;
        case 'contem': return $b !== '' && mb_strpos($a, $b) !== false;
        case 'vazio': return $a === '';
        case 'nao_vazio': return $a !== '';
    }
    return false;
}

function fluxo_condicao_ok(array $modelo, string $fluxoId, array $vars): ?bool
{
    $c = cfg_elemento($modelo, $fluxoId);
    $regras = array_values(array_filter($c['regras'] ?? [], fn($r) => !empty($r['variavel'])));
    if (!$regras) {
        return null; // sem condição
    }
    $res = array_map(fn($r) => avaliar_regra($r, $vars), $regras);
    return ($c['juncao'] ?? 'e') === 'ou' ? in_array(true, $res, true) : !in_array(false, $res, true);
}

function fluxo_eh_padrao(array $modelo, array $el, string $fluxoId): bool
{
    return $el['padrao'] === $fluxoId || !empty(cfg_elemento($modelo, $fluxoId)['padrao']);
}

/** Escolhe por quais setas o token sai do elemento. */
function saidas_escolhidas(array $modelo, array $el, array $vars): array
{
    $saidas = $el['saidas'];
    if (!$saidas) {
        return [];
    }
    $cat = $el['categoria'];
    if ($cat === 'gw_paralelo') {
        return $saidas;
    }
    $verdadeiras = [];
    $semCondicao = [];
    $padrao = null;
    foreach ($saidas as $fid) {
        if (fluxo_eh_padrao($modelo, $el, $fid)) {
            $padrao = $fid;
            continue;
        }
        $r = fluxo_condicao_ok($modelo, $fid, $vars);
        if ($r === true) {
            $verdadeiras[] = $fid;
        } elseif ($r === null) {
            $semCondicao[] = $fid;
        }
    }
    if ($cat === 'gw_exclusivo') {
        if ($verdadeiras) {
            return [$verdadeiras[0]];
        }
        if ($semCondicao) {
            return [$semCondicao[0]];
        }
        return $padrao ? [$padrao] : [];
    }
    // inclusivo e atividades: todas as setas válidas (sem condição contam como verdadeiras)
    $todas = array_merge($verdadeiras, $semCondicao);
    if ($todas) {
        return $todas;
    }
    return $padrao ? [$padrao] : [];
}

/* ===================== Execução ===================== */

function travar_instancia(int $instId): array
{
    $i = linha('SELECT * FROM instancias WHERE id = ? FOR UPDATE', [$instId]);
    if (!$i) {
        throw new ErroNegocio('Processo não encontrado.');
    }
    return $i;
}

function variaveis_instancia(int $instId): array
{
    return json_decode_arr(valor('SELECT variaveis FROM instancias WHERE id = ?', [$instId]));
}

function salvar_variaveis(int $instId, array $vars): void
{
    q('UPDATE instancias SET variaveis = ? WHERE id = ?', [json_enc($vars), $instId]);
}

function historico(int $instId, string $tipo, string $descricao, ?string $elemento = null, ?int $usuarioId = null): void
{
    inserir('historico', [
        'instancia_id' => $instId,
        'tipo' => $tipo,
        'elemento_id' => $elemento,
        'descricao' => mb_substr($descricao, 0, 500),
        'usuario_id' => $usuarioId,
    ]);
}

function criar_token(int $instId, string $elementoId): int
{
    return inserir('tokens', ['instancia_id' => $instId, 'elemento_id' => $elementoId, 'status' => 'ativo']);
}

function concluir_token(int $tokenId, string $status = 'concluido'): void
{
    q('UPDATE tokens SET status = ?, concluido_em = NOW() WHERE id = ?', [$status, $tokenId]);
}

/** Inicia um processo publicado. Devolve o id da instância. */
function motor_iniciar(int $processoId, array $usuario, array $dadosForm): int
{
    return transacao(function () use ($processoId, $usuario, $dadosForm) {
        $p = linha('SELECT * FROM processos WHERE id = ? FOR UPDATE', [$processoId]);
        if (!$p || !$p['ativo'] || !$p['versao_publicada_id']) {
            throw new ErroNegocio('Este processo não está disponível para iniciar.');
        }
        if (!pode_iniciar($p, $usuario)) {
            throw new ErroNegocio('Você não tem permissão para iniciar este processo.');
        }
        $versaoId = (int) $p['versao_publicada_id'];
        $modelo = modelo_versao($versaoId);
        $inicio = null;
        foreach ($modelo['elementos'] as $el) {
            if ($el['categoria'] === 'inicio') {
                $inicio = $el;
                break;
            }
        }
        if (!$inicio) {
            throw new ErroNegocio('O fluxo publicado não tem evento de início.');
        }
        $campos = cfg_elemento($modelo, $inicio['id'])['formulario'] ?? [];
        $valores = formulario_validar($campos, $dadosForm, []);

        $ano = date('Y');
        $seq = (int) valor('SELECT COUNT(*) FROM instancias WHERE processo_id = ? AND YEAR(iniciado_em) = ?', [$processoId, $ano]) + 1;
        do {
            $codigo = sprintf('%s-%s-%04d', $p['sigla'], $ano, $seq++);
        } while (valor('SELECT 1 FROM instancias WHERE codigo = ?', [$codigo]));

        $vars = array_merge($valores, [
            'codigo' => $codigo,
            'processo' => $p['nome'],
            'iniciador_nome' => $usuario['nome'],
            'iniciador_email' => $usuario['email'],
            'data_inicio' => date('d/m/Y'),
        ]);
        $titulo = trim(modelo_texto((string) ($p['titulo_modelo'] ?? ''), $vars));
        if ($titulo === '') {
            $titulo = $p['nome'];
        }
        $instId = inserir('instancias', [
            'processo_id' => $processoId,
            'versao_id' => $versaoId,
            'codigo' => $codigo,
            'titulo' => mb_substr($titulo, 0, 200),
            'variaveis' => json_enc($vars),
            'iniciado_por' => $usuario['id'],
        ]);
        formulario_vincular_anexos($campos, $valores, $instId, null);
        historico($instId, 'inicio', $usuario['nome'] . ' iniciou o processo.', $inicio['id'], (int) $usuario['id']);
        $tok = criar_token($instId, $inicio['id']);
        motor_executar($instId, [$tok]);
        return $instId;
    });
}

function pode_iniciar(array $processo, array $usuario): bool
{
    $grupos = array_map('intval', array_column(linhas('SELECT grupo_id FROM processo_iniciadores WHERE processo_id = ?', [$processo['id']]), 'grupo_id'));
    if (!$grupos || $usuario['perfil'] === 'admin') {
        return true;
    }
    return (bool) array_intersect($grupos, $usuario['grupos']);
}

/** Executa os tokens em fila até todos pararem (aguardando tarefa/timer, fim ou erro). */
function motor_executar(int $instId, array $fila): void
{
    $inst = linha('SELECT * FROM instancias WHERE id = ?', [$instId]);
    $modelo = modelo_versao((int) $inst['versao_id']);
    $passos = 0;
    while ($fila) {
        if (++$passos > 500) {
            throw new ErroNegocio('O fluxo entrou em repetição infinita (laço sem tarefa humana). Revise o desenho.');
        }
        $tokId = array_shift($fila);
        $tok = linha('SELECT * FROM tokens WHERE id = ?', [$tokId]);
        if (!$tok || $tok['status'] !== 'ativo') {
            continue;
        }
        $el = $modelo['elementos'][$tok['elemento_id']] ?? null;
        if (!$el) {
            marcar_erro($instId, $tokId, 'Elemento ' . $tok['elemento_id'] . ' não existe no fluxo.');
            continue;
        }
        $vars = variaveis_instancia($instId);
        $nome = nome_elemento($modelo, $el['id']);

        switch ($el['categoria']) {
            case 'inicio':
            case 'passagem':
                concluir_token($tokId);
                $fila = array_merge($fila, seguir($instId, $modelo, $el, $vars));
                break;

            case 'humana':
                criar_tarefa($inst, $modelo, $el, $tokId, $vars);
                q("UPDATE tokens SET status = 'aguardando' WHERE id = ?", [$tokId]);
                break;

            case 'automatica':
                try {
                    $res = executar_acao(cfg_elemento($modelo, $el['id']), $vars, $inst);
                    if ($res['variaveis']) {
                        $vars = array_merge($vars, $res['variaveis']);
                        salvar_variaveis($instId, $vars);
                    }
                    historico($instId, 'automacao', $nome . ': ' . $res['resumo'], $el['id']);
                    concluir_token($tokId);
                    $fila = array_merge($fila, seguir($instId, $modelo, $el, $vars));
                } catch (Throwable $e) {
                    marcar_erro($instId, $tokId, $nome . ': ' . $e->getMessage());
                }
                break;

            case 'intermediario':
                $c = cfg_elemento($modelo, $el['id'])['timer'] ?? [];
                $mult = ['minutos' => 60, 'horas' => 3600, 'dias' => 86400][$c['unidade'] ?? 'horas'] ?? 3600;
                $seg = (int) round((float) ($c['valor'] ?? 0) * $mult);
                $vence = date('Y-m-d H:i:s', time() + max(0, $seg));
                q("UPDATE tokens SET status = 'aguardando', vence_em = ? WHERE id = ?", [$vence, $tokId]);
                historico($instId, 'timer', $nome . ': aguardando até ' . data_br($vence) . '.', $el['id']);
                if ($seg <= 0) {
                    concluir_token($tokId);
                    $fila = array_merge($fila, seguir($instId, $modelo, $el, $vars));
                }
                break;

            case 'gw_exclusivo':
            case 'gw_inclusivo':
            case 'gw_paralelo':
                $juntar = count($el['entradas']) > 1;
                if ($juntar) {
                    q("UPDATE tokens SET status = 'aguardando' WHERE id = ?", [$tokId]);
                    if ($el['categoria'] === 'gw_exclusivo') {
                        $prontos = [$tokId];
                    } else {
                        $prontos = juncao_pronta($instId, $modelo, $el);
                    }
                    if (!$prontos) {
                        break;
                    }
                    foreach ($prontos as $p) {
                        concluir_token((int) $p);
                    }
                } else {
                    concluir_token($tokId);
                }
                $novos = seguir($instId, $modelo, $el, $vars);
                if (!$novos && $el['saidas']) {
                    marcar_erro($instId, $tokId, $nome . ': nenhuma condição de saída foi atendida e não há caminho padrão.');
                    break;
                }
                if ($el['categoria'] !== 'gw_paralelo' && count($el['saidas']) > 1) {
                    $destinos = array_map(fn($t) => nome_elemento($modelo, (string) valor('SELECT elemento_id FROM tokens WHERE id = ?', [$t])), $novos);
                    historico($instId, 'gateway', $nome . ' → ' . implode(', ', $destinos), $el['id']);
                }
                $fila = array_merge($fila, $novos);
                break;

            case 'fim':
                concluir_token($tokId);
                if ($el['evento'] === 'terminate') {
                    q("UPDATE tokens SET status = 'cancelado', concluido_em = NOW() WHERE instancia_id = ? AND status IN ('ativo','aguardando')", [$instId]);
                    q("UPDATE tarefas SET status = 'cancelada' WHERE instancia_id = ? AND status = 'aberta'", [$instId]);
                    $fila = [];
                }
                historico($instId, 'fim', 'Chegou ao fim: ' . $nome . '.', $el['id']);
                break;

            default:
                marcar_erro($instId, $tokId, 'Elemento não suportado: ' . $el['tipo']);
        }

        if (!$fila) {
            $fila = reavaliar_juncoes_inclusivas($instId, $modelo);
        }
    }
    verificar_conclusao($instId);
}

/** Cria um token em cada destino escolhido e devolve os ids. */
function seguir(int $instId, array $modelo, array $el, array $vars): array
{
    $novos = [];
    foreach (saidas_escolhidas($modelo, $el, $vars) as $fid) {
        $destino = $modelo['fluxos'][$fid]['destino'] ?? null;
        if ($destino !== null) {
            $novos[] = criar_token($instId, $destino);
        }
    }
    return $novos;
}

/** Junção: devolve os tokens a consumir quando a junção está completa, ou [] se ainda falta chegar alguém. */
function juncao_pronta(int $instId, array $modelo, array $el): array
{
    $aguardando = array_map('intval', array_column(linhas(
        "SELECT id FROM tokens WHERE instancia_id = ? AND elemento_id = ? AND status = 'aguardando' ORDER BY id",
        [$instId, $el['id']]
    ), 'id'));
    if ($el['categoria'] === 'gw_paralelo') {
        $n = count($el['entradas']);
        return count($aguardando) >= $n ? array_slice($aguardando, 0, $n) : [];
    }
    // inclusivo: espera enquanto algum outro token vivo ainda puder chegar aqui
    $outros = linhas(
        "SELECT elemento_id FROM tokens WHERE instancia_id = ? AND status IN ('ativo','aguardando') AND elemento_id <> ?",
        [$instId, $el['id']]
    );
    foreach ($outros as $o) {
        if (alcanca($modelo, $o['elemento_id'], $el['id'])) {
            return [];
        }
    }
    return $aguardando;
}

function reavaliar_juncoes_inclusivas(int $instId, array $modelo): array
{
    $esperando = linhas(
        "SELECT DISTINCT elemento_id FROM tokens WHERE instancia_id = ? AND status = 'aguardando'",
        [$instId]
    );
    foreach ($esperando as $r) {
        $el = $modelo['elementos'][$r['elemento_id']] ?? null;
        if (!$el || $el['categoria'] !== 'gw_inclusivo' || count($el['entradas']) < 2) {
            continue;
        }
        $prontos = juncao_pronta($instId, $modelo, $el);
        if ($prontos) {
            $primeiro = array_shift($prontos);
            foreach ($prontos as $p) {
                concluir_token((int) $p);
            }
            // reativa um token para que a fila o processe como chegada única
            q("UPDATE tokens SET status = 'concluido', concluido_em = NOW() WHERE id = ?", [$primeiro]);
            return seguir($instId, $modelo, $el, variaveis_instancia($instId));
        }
    }
    return [];
}

function alcanca(array $modelo, string $de, string $ate): bool
{
    $visitados = [];
    $pilha = [$de];
    while ($pilha) {
        $atual = array_pop($pilha);
        if (isset($visitados[$atual])) {
            continue;
        }
        $visitados[$atual] = true;
        foreach ($modelo['elementos'][$atual]['saidas'] ?? [] as $fid) {
            $dest = $modelo['fluxos'][$fid]['destino'] ?? null;
            if ($dest === $ate) {
                return true;
            }
            if ($dest !== null) {
                $pilha[] = $dest;
            }
        }
    }
    return false;
}

function marcar_erro(int $instId, int $tokId, string $mensagem): void
{
    q("UPDATE tokens SET status = 'erro', erro = ? WHERE id = ?", [mb_substr($mensagem, 0, 2000), $tokId]);
    q("UPDATE instancias SET status = 'erro' WHERE id = ? AND status = 'andamento'", [$instId]);
    historico($instId, 'erro', $mensagem, (string) valor('SELECT elemento_id FROM tokens WHERE id = ?', [$tokId]));
    foreach (linhas("SELECT id FROM usuarios WHERE perfil = 'admin' AND ativo = 1") as $a) {
        $cod = (string) valor('SELECT codigo FROM instancias WHERE id = ?', [$instId]);
        notificar((int) $a['id'], 'Erro no processo ' . $cod, $mensagem, 'processo.php?id=' . $instId);
    }
}

function verificar_conclusao(int $instId): void
{
    $vivos = (int) valor("SELECT COUNT(*) FROM tokens WHERE instancia_id = ? AND status IN ('ativo','aguardando','erro')", [$instId]);
    $inst = linha('SELECT status, iniciado_por, codigo, titulo FROM instancias WHERE id = ?', [$instId]);
    if ($vivos === 0 && in_array($inst['status'], ['andamento', 'erro'], true)) {
        q("UPDATE instancias SET status = 'concluida', concluido_em = NOW() WHERE id = ?", [$instId]);
        historico($instId, 'concluido', 'Processo concluído.');
        if ($inst['iniciado_por']) {
            notificar((int) $inst['iniciado_por'], 'Processo concluído', $inst['codigo'] . ' — ' . $inst['titulo'], 'processo.php?id=' . $instId);
        }
    } elseif ($inst['status'] === 'erro' && (int) valor("SELECT COUNT(*) FROM tokens WHERE instancia_id = ? AND status = 'erro'", [$instId]) === 0) {
        q("UPDATE instancias SET status = 'andamento' WHERE id = ?", [$instId]);
    }
}

/* ===================== Tarefas humanas ===================== */

function criar_tarefa(array $inst, array $modelo, array $el, int $tokId, array $vars): void
{
    $c = cfg_elemento($modelo, $el['id']);
    $at = $c['atribuicao'] ?? [];
    $usuarioId = null;
    $grupoId = null;
    $candidatos = [];
    $iniciador = $inst['iniciado_por'] ? (int) $inst['iniciado_por'] : null;
    switch ($at['tipo'] ?? '') {
        case 'usuario':
            // uma pessoa: a tarefa já é dela; várias: fica disponível para elas e quem assumir primeiro fica com a tarefa
            $ids = usuarios_da_atribuicao($at);
            $ativos = $ids ? array_map('intval', array_column(linhas(
                'SELECT id FROM usuarios WHERE ativo = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                $ids
            ), 'id')) : [];
            if (count($ativos) === 1) {
                $usuarioId = $ativos[0];
            } elseif (count($ativos) > 1) {
                $candidatos = $ativos;
            }
            break;
        case 'grupo':
            $grupoId = (int) $at['grupo_id'];
            break;
        case 'gestor':
            $usuarioId = $iniciador ? ((int) valor('SELECT gestor_id FROM usuarios WHERE id = ?', [$iniciador]) ?: $iniciador) : null;
            break;
        case 'executor':
            $usuarioId = (int) valor(
                "SELECT concluida_por FROM tarefas WHERE instancia_id = ? AND elemento_id = ? AND status = 'concluida' ORDER BY id DESC LIMIT 1",
                [$inst['id'], $at['elemento_ref'] ?? '']
            ) ?: $iniciador;
            break;
        default:
            $usuarioId = $iniciador;
    }
    if ($usuarioId && !valor('SELECT ativo FROM usuarios WHERE id = ?', [$usuarioId])) {
        $usuarioId = null;
    }
    if (!$usuarioId && !$grupoId && !$candidatos) {
        // ninguém válido: vai para os administradores decidirem
        $grupoId = null;
        $usuarioId = (int) valor("SELECT id FROM usuarios WHERE perfil = 'admin' AND ativo = 1 ORDER BY id LIMIT 1") ?: null;
    }
    $prazo = calcular_prazo($c, time());
    $nome = trim(modelo_texto(nome_elemento($modelo, $el['id']), $vars));
    $tarId = inserir('tarefas', [
        'instancia_id' => $inst['id'],
        'token_id' => $tokId,
        'elemento_id' => $el['id'],
        'nome' => mb_substr($nome, 0, 200),
        'usuario_id' => $usuarioId,
        'grupo_id' => $grupoId,
        'prioridade' => in_array($c['prioridade'] ?? '', ['baixa', 'normal', 'alta'], true) ? $c['prioridade'] : 'normal',
        'prazo' => $prazo,
    ]);
    foreach ($candidatos as $cid) {
        inserir('tarefa_candidatos', ['tarefa_id' => $tarId, 'usuario_id' => $cid]);
    }
    if ($usuarioId) {
        $quem = (string) valor('SELECT nome FROM usuarios WHERE id = ?', [$usuarioId]);
    } elseif ($candidatos) {
        $nomes = array_column(linhas('SELECT nome FROM usuarios WHERE id IN (' . implode(',', $candidatos) . ') ORDER BY nome'), 'nome');
        $quem = implode(', ', $nomes) . ' (quem assumir primeiro)';
    } else {
        $quem = 'grupo ' . (string) valor('SELECT nome FROM grupos WHERE id = ?', [$grupoId]);
    }
    historico((int) $inst['id'], 'tarefa_criada', 'Tarefa "' . $nome . '" enviada para ' . $quem . '.', $el['id']);

    if ($usuarioId) {
        $destinatarios = [$usuarioId];
    } elseif ($candidatos) {
        $destinatarios = $candidatos;
    } else {
        $destinatarios = array_map('intval', array_column(
            linhas('SELECT ug.usuario_id FROM usuario_grupos ug JOIN usuarios u ON u.id = ug.usuario_id WHERE ug.grupo_id = ? AND u.ativo = 1', [$grupoId]),
            'usuario_id'
        ));
    }
    foreach ($destinatarios as $d) {
        notificar($d, 'Nova tarefa: ' . $nome, $inst['codigo'] . ' — ' . $inst['titulo'], 'tarefa.php?id=' . $tarId);
    }
    if (cfg_banco('email_tarefas') === '1') {
        foreach ($destinatarios as $d) {
            $email = (string) valor('SELECT email FROM usuarios WHERE id = ?', [$d]);
            try {
                enviar_email($email, 'Nova tarefa: ' . $nome, "Você recebeu a tarefa \"$nome\" no processo {$inst['codigo']} — {$inst['titulo']}.\n\nAcesse: " . url_absoluta('tarefa.php?id=' . $tarId));
            } catch (Throwable $e) {
                error_log('[bpms] e-mail de tarefa: ' . $e->getMessage());
            }
        }
    }
}

function tarefa_visivel(array $t, array $u): bool
{
    if ($u['perfil'] === 'admin') {
        return true;
    }
    if ((int) $t['usuario_id'] === (int) $u['id']) {
        return true;
    }
    if ($t['usuario_id'] !== null) {
        return false;
    }
    if ($t['grupo_id'] && in_array((int) $t['grupo_id'], $u['grupos'], true)) {
        return true;
    }
    return (bool) valor('SELECT 1 FROM tarefa_candidatos WHERE tarefa_id = ? AND usuario_id = ?', [$t['id'], $u['id']]);
}

/** Tarefa que fica disponível para várias pessoas (grupo ou pessoas escolhidas) e pode ser devolvida. */
function tarefa_compartilhada(array $t): bool
{
    return (bool) $t['grupo_id'] || (bool) valor('SELECT 1 FROM tarefa_candidatos WHERE tarefa_id = ? LIMIT 1', [$t['id']]);
}

/** Lista de pessoas configuradas na atividade (aceita o formato antigo, com um único usuario_id). */
function usuarios_da_atribuicao(array $at): array
{
    $ids = array_map('intval', (array) ($at['usuario_ids'] ?? []));
    if (!$ids && !empty($at['usuario_id'])) {
        $ids = [(int) $at['usuario_id']];
    }
    return array_values(array_unique(array_filter($ids, fn($i) => $i > 0)));
}

/*
 * Prazo da atividade: valor + unidade (minutos ou horas) e, se marcado, contando só o horário útil
 * (segunda a sexta, dentro do expediente configurado e fora dos feriados).
 */
function calcular_prazo(array $c, int $inicio): ?string
{
    $valor = (float) ($c['prazo_valor'] ?? 0);
    $unidade = ($c['prazo_unidade'] ?? 'horas') === 'minutos' ? 'minutos' : 'horas';
    if ($valor <= 0 && !empty($c['prazo_horas'])) {
        $valor = (float) $c['prazo_horas']; // configuração antiga
        $unidade = 'horas';
    }
    if ($valor <= 0) {
        return null;
    }
    $minutos = (int) round($unidade === 'horas' ? $valor * 60 : $valor);
    $fim = !empty($c['prazo_uteis']) ? somar_minutos_uteis($inicio, $minutos) : $inicio + $minutos * 60;
    return date('Y-m-d H:i:s', $fim);
}

function expediente(): array
{
    $ler = function (string $chave, string $padrao): int {
        $v = cfg_banco($chave, $padrao);
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m)) {
            [$h, $mi] = explode(':', $padrao);
            return (int) $h * 60 + (int) $mi;
        }
        return min(1439, (int) $m[1] * 60 + (int) $m[2]);
    };
    $ini = $ler('expediente_inicio', '08:00');
    $fim = $ler('expediente_fim', '18:00');
    if ($fim <= $ini) {
        [$ini, $fim] = [480, 1080];
    }
    $feriados = [];
    foreach (preg_split('/[\s,;]+/', cfg_banco('feriados')) as $d) {
        if (preg_match('#^(\d{2})/(\d{2})(?:/(\d{4}))?$#', $d, $m)) {
            // dd/mm vale todo ano; dd/mm/aaaa só naquele ano
            $feriados[] = isset($m[3]) ? "$m[3]-$m[2]-$m[1]" : "$m[2]-$m[1]";
        }
    }
    return ['ini' => $ini, 'fim' => $fim, 'feriados' => $feriados];
}

function dia_util(int $ts, array $exp): bool
{
    if ((int) date('N', $ts) >= 6) {
        return false;
    }
    return !in_array(date('Y-m-d', $ts), $exp['feriados'], true) && !in_array(date('m-d', $ts), $exp['feriados'], true);
}

function somar_minutos_uteis(int $inicio, int $minutos): int
{
    $exp = expediente();
    $cursor = $inicio - ($inicio % 60);
    for ($guarda = 0; $guarda < 3700 && $minutos > 0; $guarda++) {
        $meiaNoite = strtotime(date('Y-m-d 00:00:00', $cursor));
        $agora = (int) (($cursor - $meiaNoite) / 60);
        if (!dia_util($cursor, $exp) || $agora >= $exp['fim']) {
            $cursor = strtotime('+1 day', $meiaNoite) + $exp['ini'] * 60;
            continue;
        }
        if ($agora < $exp['ini']) {
            $cursor = $meiaNoite + $exp['ini'] * 60;
            $agora = $exp['ini'];
        }
        $disponivel = $exp['fim'] - $agora;
        if ($minutos <= $disponivel) {
            return $cursor + $minutos * 60;
        }
        $minutos -= $disponivel;
        $cursor = strtotime('+1 day', $meiaNoite) + $exp['ini'] * 60;
    }
    return $cursor;
}

function motor_assumir(int $tarefaId, array $u): void
{
    transacao(function () use ($tarefaId, $u) {
        $t = linha('SELECT * FROM tarefas WHERE id = ? FOR UPDATE', [$tarefaId]);
        if (!$t || $t['status'] !== 'aberta') {
            throw new ErroNegocio('Esta tarefa não está mais aberta.');
        }
        if ((int) $t['usuario_id'] === (int) $u['id']) {
            return;
        }
        if ($t['usuario_id'] !== null) {
            throw new ErroNegocio('Outra pessoa já assumiu esta tarefa.');
        }
        if (!tarefa_visivel($t, $u)) {
            throw new ErroNegocio('Esta tarefa não está disponível para você.');
        }
        q('UPDATE tarefas SET usuario_id = ?, assumida_em = NOW() WHERE id = ?', [$u['id'], $tarefaId]);
        historico((int) $t['instancia_id'], 'tarefa_assumida', $u['nome'] . ' assumiu a tarefa "' . $t['nome'] . '".', $t['elemento_id'], (int) $u['id']);
    });
}

function motor_devolver(int $tarefaId, array $u): void
{
    transacao(function () use ($tarefaId, $u) {
        $t = linha('SELECT * FROM tarefas WHERE id = ? FOR UPDATE', [$tarefaId]);
        if (!$t || $t['status'] !== 'aberta') {
            throw new ErroNegocio('Esta tarefa não está mais aberta.');
        }
        if (!tarefa_compartilhada($t)) {
            throw new ErroNegocio('Esta tarefa é individual e não pode ser devolvida.');
        }
        if ((int) $t['usuario_id'] !== (int) $u['id'] && $u['perfil'] !== 'admin') {
            throw new ErroNegocio('Só quem assumiu a tarefa pode devolvê-la.');
        }
        q('UPDATE tarefas SET usuario_id = NULL, assumida_em = NULL WHERE id = ?', [$tarefaId]);
        historico((int) $t['instancia_id'], 'tarefa_devolvida', $u['nome'] . ' devolveu a tarefa "' . $t['nome'] . '", que voltou a ficar disponível.', $t['elemento_id'], (int) $u['id']);
    });
}

function motor_reatribuir(int $tarefaId, int $novoUsuario, array $admin): void
{
    transacao(function () use ($tarefaId, $novoUsuario, $admin) {
        $t = linha('SELECT * FROM tarefas WHERE id = ? FOR UPDATE', [$tarefaId]);
        $n = linha('SELECT id, nome FROM usuarios WHERE id = ? AND ativo = 1', [$novoUsuario]);
        if (!$t || $t['status'] !== 'aberta' || !$n) {
            throw new ErroNegocio('Não foi possível reatribuir esta tarefa.');
        }
        q('UPDATE tarefas SET usuario_id = ?, assumida_em = NOW() WHERE id = ?', [$novoUsuario, $tarefaId]);
        historico((int) $t['instancia_id'], 'reatribuida', $admin['nome'] . ' reatribuiu a tarefa "' . $t['nome'] . '" para ' . $n['nome'] . '.', $t['elemento_id'], (int) $admin['id']);
        notificar($novoUsuario, 'Tarefa atribuída a você: ' . $t['nome'], '', 'tarefa.php?id=' . $tarefaId);
    });
}

function motor_concluir_tarefa(int $tarefaId, array $u, array $dados, ?string $decisao): void
{
    transacao(function () use ($tarefaId, $u, $dados, $decisao) {
        $t = linha('SELECT * FROM tarefas WHERE id = ?', [$tarefaId]);
        if (!$t) {
            throw new ErroNegocio('Tarefa não encontrada.');
        }
        travar_instancia((int) $t['instancia_id']);
        $t = linha('SELECT * FROM tarefas WHERE id = ? FOR UPDATE', [$tarefaId]);
        if ($t['status'] !== 'aberta') {
            throw new ErroNegocio('Esta tarefa já foi concluída por ' . (valor('SELECT nome FROM usuarios WHERE id = ?', [$t['concluida_por']]) ?: 'outra pessoa') . '.');
        }
        if ((int) $t['usuario_id'] !== (int) $u['id']) {
            if ($t['usuario_id'] === null && tarefa_visivel($t, $u)) {
                q('UPDATE tarefas SET usuario_id = ?, assumida_em = NOW() WHERE id = ?', [$u['id'], $tarefaId]);
            } elseif ($u['perfil'] !== 'admin') {
                throw new ErroNegocio('Esta tarefa está com outra pessoa.');
            }
        }
        $inst = linha('SELECT * FROM instancias WHERE id = ?', [$t['instancia_id']]);
        $modelo = modelo_versao((int) $inst['versao_id']);
        $c = cfg_elemento($modelo, $t['elemento_id']);
        $vars = json_decode_arr($inst['variaveis']);

        $decisoes = array_values(array_filter(array_map('trim', $c['decisoes'] ?? [])));
        if ($decisoes) {
            if ($decisao === null || !in_array($decisao, $decisoes, true)) {
                throw new ErroNegocio('Escolha uma das decisões: ' . implode(', ', $decisoes) . '.');
            }
        }
        $valores = formulario_validar($c['formulario'] ?? [], $dados, $vars);
        if ($decisoes) {
            $varDec = nome_variavel($c['decisao_variavel'] ?? '') ?: 'decisao';
            $valores[$varDec] = $decisao;
        }
        $vars = array_merge($vars, $valores);
        salvar_variaveis((int) $inst['id'], $vars);
        formulario_vincular_anexos($c['formulario'] ?? [], $valores, (int) $inst['id'], $tarefaId);

        q("UPDATE tarefas SET status = 'concluida', concluida_em = NOW(), concluida_por = ?, dados = ? WHERE id = ?", [$u['id'], json_enc($valores), $tarefaId]);
        historico(
            (int) $inst['id'],
            'tarefa_concluida',
            $u['nome'] . ' concluiu "' . $t['nome'] . '"' . ($decisao ? ' com a decisão: ' . $decisao : '') . '.',
            $t['elemento_id'],
            (int) $u['id']
        );
        $el = $modelo['elementos'][$t['elemento_id']];
        concluir_token((int) $t['token_id']);
        $novos = seguir((int) $inst['id'], $modelo, $el, $vars);
        if (!$novos && $el['saidas']) {
            $tok = criar_token((int) $inst['id'], $el['id']);
            marcar_erro((int) $inst['id'], $tok, nome_elemento($modelo, $el['id']) . ': nenhuma condição de saída foi atendida.');
        }
        motor_executar((int) $inst['id'], $novos);
    });
}

function motor_cancelar(int $instId, array $u, string $motivo): void
{
    transacao(function () use ($instId, $u, $motivo) {
        $i = travar_instancia($instId);
        if (!in_array($i['status'], ['andamento', 'erro'], true)) {
            throw new ErroNegocio('Este processo já foi encerrado.');
        }
        if ($u['perfil'] !== 'admin' && (int) $i['iniciado_por'] !== (int) $u['id']) {
            throw new ErroNegocio('Só quem iniciou o processo ou um administrador pode cancelá-lo.');
        }
        q("UPDATE instancias SET status = 'cancelada', concluido_em = NOW() WHERE id = ?", [$instId]);
        q("UPDATE tokens SET status = 'cancelado', concluido_em = NOW() WHERE instancia_id = ? AND status IN ('ativo','aguardando','erro')", [$instId]);
        q("UPDATE tarefas SET status = 'cancelada' WHERE instancia_id = ? AND status = 'aberta'", [$instId]);
        historico($instId, 'cancelado', $u['nome'] . ' cancelou o processo' . ($motivo !== '' ? ': ' . $motivo : '.'), null, (int) $u['id']);
    });
}

/** Reexecuta tokens com erro (depois que o admin corrigiu a causa). */
function motor_repetir(int $instId, array $u): void
{
    transacao(function () use ($instId, $u) {
        travar_instancia($instId);
        $erros = array_map('intval', array_column(linhas("SELECT id FROM tokens WHERE instancia_id = ? AND status = 'erro'", [$instId]), 'id'));
        if (!$erros) {
            throw new ErroNegocio('Não há etapas com erro neste processo.');
        }
        q("UPDATE instancias SET status = 'andamento' WHERE id = ?", [$instId]);
        foreach ($erros as $t) {
            q("UPDATE tokens SET status = 'ativo', erro = NULL WHERE id = ?", [$t]);
        }
        historico($instId, 'repetir', $u['nome'] . ' mandou reexecutar as etapas com erro.', null, (int) $u['id']);
        motor_executar($instId, $erros);
    });
}

/** Temporizadores vencidos. Roda pelo cron e, de carona, a cada minuto quando alguém navega. */
function processar_timers(int $limite = 50): int
{
    $vencidos = linhas(
        "SELECT t.id, t.instancia_id FROM tokens t JOIN instancias i ON i.id = t.instancia_id
         WHERE t.status = 'aguardando' AND t.vence_em IS NOT NULL AND t.vence_em <= NOW() AND i.status = 'andamento'
         ORDER BY t.vence_em LIMIT " . (int) $limite
    );
    $n = 0;
    foreach ($vencidos as $v) {
        try {
            transacao(function () use ($v, &$n) {
                $inst = travar_instancia((int) $v['instancia_id']);
                $tok = linha('SELECT * FROM tokens WHERE id = ? FOR UPDATE', [$v['id']]);
                if (!$tok || $tok['status'] !== 'aguardando') {
                    return;
                }
                $modelo = modelo_versao((int) $inst['versao_id']);
                $el = $modelo['elementos'][$tok['elemento_id']];
                concluir_token((int) $tok['id']);
                historico((int) $inst['id'], 'timer', nome_elemento($modelo, $el['id']) . ': tempo de espera encerrado.', $el['id']);
                motor_executar((int) $inst['id'], seguir((int) $inst['id'], $modelo, $el, json_decode_arr($inst['variaveis'])));
                $n++;
            });
        } catch (Throwable $e) {
            error_log('[bpms] timer ' . $v['id'] . ': ' . $e->getMessage());
        }
    }
    return $n;
}

function processar_timers_de_carona(): void
{
    $ultimo = (int) cfg_banco('timers_ultimo', '0');
    if (time() - $ultimo < 60) {
        return;
    }
    salvar_cfg_banco('timers_ultimo', (string) time());
    try {
        processar_timers(20);
    } catch (Throwable $e) {
        error_log('[bpms] timers: ' . $e->getMessage());
    }
}

/** Situação de cada elemento para pintar o diagrama: concluido | ativo | erro. */
function situacao_elementos(int $instId): array
{
    $sit = [];
    foreach (linhas('SELECT elemento_id, status FROM tokens WHERE instancia_id = ? ORDER BY id', [$instId]) as $t) {
        $s = ['concluido' => 'concluido', 'ativo' => 'ativo', 'aguardando' => 'ativo', 'erro' => 'erro', 'cancelado' => 'cancelado'][$t['status']];
        $atual = $sit[$t['elemento_id']] ?? null;
        $peso = ['cancelado' => 0, 'concluido' => 1, 'ativo' => 2, 'erro' => 3];
        if ($atual === null || $peso[$s] > $peso[$atual]) {
            $sit[$t['elemento_id']] = $s;
        }
    }
    return $sit;
}

/** Setas percorridas (origem e destino com token) para destacar o caminho. */
function fluxos_percorridos(array $modelo, array $situacao): array
{
    $ids = [];
    foreach ($modelo['fluxos'] as $f) {
        if (($situacao[$f['origem']] ?? '') === 'concluido' && isset($situacao[$f['destino']])) {
            $ids[] = $f['id'];
        }
    }
    return $ids;
}
