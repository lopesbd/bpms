<?php
declare(strict_types=1);

/* Blocos de tela reaproveitados entre a tarefa e o detalhe do processo. */

function pode_ver_instancia(array $inst, array $u): bool
{
    if ($u['perfil'] === 'admin' || (int) $inst['iniciado_por'] === (int) $u['id']) {
        return true;
    }
    [$filtro, $p] = filtro_tarefas_usuario($u);
    return (bool) valor(
        "SELECT 1 FROM tarefas t WHERE t.instancia_id = ? AND (t.concluida_por = ? OR $filtro) LIMIT 1",
        array_merge([$inst['id'], $u['id']], $p)
    );
}

function card_diagrama(array $inst, string $titulo = 'Andamento no fluxo'): string
{
    $modelo = modelo_versao((int) $inst['versao_id']);
    $sit = situacao_elementos((int) $inst['id']);
    $xml = (string) valor('SELECT xml FROM processo_versoes WHERE id = ?', [$inst['versao_id']]);
    $dados = ['xml' => $xml, 'situacao' => $sit, 'caminho' => fluxos_percorridos($modelo, $sit)];
    return '<div class="card"><div class="card-cab"><h3>' . e($titulo) . '</h3>'
        . '<div class="diagrama-legenda"><span><i style="background:#e6f6ee;border:2px solid var(--verde)"></i>Concluído</span><span><i style="background:#fff6e0;border:2px solid #f59e0b"></i>Em andamento</span><span><i style="background:#fdecec;border:2px solid var(--erro)"></i>Erro</span></div></div>'
        . '<div class="diagrama" data-visualizador="diag-' . (int) $inst['id'] . '"><div class="diagrama-zoom"><button type="button" class="btn-icone" data-zoom="mais" title="Aproximar">+</button><button type="button" class="btn-icone" data-zoom="menos" title="Afastar">−</button><button type="button" class="btn-icone" data-zoom="ajustar" title="Ajustar">⤢</button></div></div>'
        . dados_js('diag-' . (int) $inst['id'], $dados) . '</div>';
}

function card_dados(array $inst): string
{
    $modelo = modelo_versao((int) $inst['versao_id']);
    $rotulos = rotulos_variaveis($modelo);
    $vars = json_decode_arr($inst['variaveis']);
    $h = '<div class="card"><div class="card-cab"><h3>Dados do processo</h3></div><div class="card-corpo" style="padding-top:8px;padding-bottom:8px"><dl class="dados-lista">';
    $h .= '<div><dt>Código</dt><dd>' . e($inst['codigo']) . '</dd></div>';
    $h .= '<div><dt>Iniciado por</dt><dd>' . e($vars['iniciador_nome'] ?? '—') . '</dd></div>';
    $h .= '<div><dt>Início</dt><dd>' . e(data_br($inst['iniciado_em'])) . '</dd></div>';
    if ($inst['concluido_em']) {
        $h .= '<div><dt>Encerrado em</dt><dd>' . e(data_br($inst['concluido_em'])) . '</dd></div>';
    }
    foreach ($rotulos as $var => $info) {
        if (!array_key_exists($var, $vars) || $vars[$var] === null || $vars[$var] === '') {
            continue;
        }
        $v = $vars[$var];
        if ($info['tipo'] === 'arquivo' && is_array($v)) {
            $links = array_map(fn($a) => '<a href="arquivo.php?id=' . (int) $a['id'] . '" target="_blank" rel="noopener">' . e($a['nome']) . '</a>', $v);
            $h .= '<div><dt>' . e($info['rotulo']) . '</dt><dd>' . implode('<br>', $links) . '</dd></div>';
            continue;
        }
        $h .= '<div><dt>' . e($info['rotulo']) . '</dt><dd>' . nl2br(e(valor_exibicao($v, $info['tipo']))) . '</dd></div>';
    }
    return $h . '</dl></div></div>';
}

function card_historico(int $instId): string
{
    $itens = linhas('SELECT h.*, u.nome FROM historico h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.instancia_id = ? ORDER BY h.id DESC', [$instId]);
    $tipos = [
        'inicio' => ['ok', 'iniciar'], 'tarefa_criada' => ['info', 'tarefas'], 'tarefa_assumida' => ['info', 'usuario'],
        'tarefa_devolvida' => ['alerta', 'grupo'], 'tarefa_concluida' => ['ok', 'check'], 'automacao' => ['ok', 'raio'],
        'gateway' => ['info', 'fluxo'], 'timer' => ['alerta', 'relogio'], 'fim' => ['ok', 'check'], 'concluido' => ['ok', 'check'],
        'cancelado' => ['erro', 'x'], 'erro' => ['erro', 'alerta'], 'repetir' => ['alerta', 'raio'], 'reatribuida' => ['info', 'usuario'],
    ];
    $h = '<div class="card"><div class="card-cab"><h3>Linha do tempo</h3><small class="tag">' . count($itens) . ' eventos</small></div><div class="card-corpo"><ul class="linha-tempo">';
    foreach ($itens as $i) {
        [$cls, $ic] = $tipos[$i['tipo']] ?? ['info', 'fluxo'];
        $h .= '<li class="' . $cls . '"><span class="lt-ic">' . icone($ic, 11) . '</span>' . e($i['descricao']) . '<time title="' . e(data_br($i['criado_em'])) . '">' . e(data_br($i['criado_em'])) . ' · ' . e(tempo_relativo($i['criado_em'])) . '</time></li>';
    }
    return $h . '</ul></div></div>';
}

function card_comentarios(int $instId): string
{
    $itens = linhas('SELECT c.*, u.nome FROM comentarios c LEFT JOIN usuarios u ON u.id = c.usuario_id WHERE c.instancia_id = ? ORDER BY c.id', [$instId]);
    $h = '<div class="card"><div class="card-cab"><h3>Comentários</h3></div><div class="card-corpo"><div class="comentarios" data-comentarios>';
    if (!$itens) {
        $h .= '<div class="vazio-mini" data-sem-comentarios>Nenhum comentário ainda.</div>';
    }
    foreach ($itens as $c) {
        $nome = $c['nome'] ?? 'Usuário removido';
        $h .= '<div class="comentario">' . avatar($nome, 'avatar-sm') . '<div class="bolha"><small>' . e($nome) . ' · ' . e(tempo_relativo($c['criado_em'])) . '</small>' . e($c['texto']) . '</div></div>';
    }
    $h .= '</div><form class="form-comentario" data-form-comentario="' . $instId . '"><textarea placeholder="Escreva um comentário…" maxlength="2000" required aria-label="Comentário"></textarea><button class="btn btn-primario" type="submit">Enviar</button></form></div></div>';
    return $h;
}

function cabecalho_instancia(array $inst, string $titulo, string $extraMeta = '', string $acoes = ''): void
{
    [$st, $tipo] = status_instancia($inst['status']);
    echo '<div class="pagina-topo"><div class="cab-processo"><span class="cp-ic" style="background:' . e($inst['cor']) . '">' . icone($inst['icone'], 24) . '</span><div>'
        . '<h1>' . e($titulo) . '</h1><div class="meta"><strong>' . e($inst['codigo']) . '</strong><span>' . e($inst['proc_nome']) . '</span>' . badge($st, $tipo) . $extraMeta . '</div></div></div>'
        . ($acoes ? '<div class="pagina-acoes">' . $acoes . '</div>' : '') . '</div>';
}

function carregar_instancia(int $id): ?array
{
    return linha('SELECT i.*, p.nome AS proc_nome, p.cor, p.icone FROM instancias i JOIN processos p ON p.id = i.processo_id WHERE i.id = ?', [$id]);
}

const CSS_VISUALIZADOR = ['assets/vendor/bpmn-js/diagram-js.css', 'assets/vendor/bpmn-js/bpmn-js.css', 'assets/vendor/bpmn-js/bpmn-embedded.css'];
const JS_VISUALIZADOR = ['assets/vendor/bpmn-js/bpmn-navigated-viewer.production.min.js', 'assets/js/visualizador.js', 'assets/js/processo.js'];
