<?php
require __DIR__ . '/app/nucleo.php';
$u = exigir_login();

[$filtro, $params] = filtro_tarefas_usuario($u);
$base = "SELECT t.*, i.codigo, i.titulo AS inst_titulo, p.nome AS proc_nome, p.cor, p.icone, g.nome AS grupo_nome,
           (SELECT COUNT(*) FROM tarefa_candidatos tc WHERE tc.tarefa_id = t.id) AS n_candidatos
         FROM tarefas t JOIN instancias i ON i.id = t.instancia_id JOIN processos p ON p.id = i.processo_id
         LEFT JOIN grupos g ON g.id = t.grupo_id";

$abertas = linhas("$base WHERE t.status = 'aberta' AND $filtro ORDER BY (t.prazo IS NULL), t.prazo, FIELD(t.prioridade,'alta','normal','baixa'), t.id", $params);
$concluidas = linhas("$base WHERE t.status = 'concluida' AND t.concluida_por = ? AND t.concluida_em > (NOW() - INTERVAL 7 DAY) ORDER BY t.concluida_em DESC LIMIT 12", [$u['id']]);

$doGrupo = array_values(array_filter($abertas, fn($t) => $t['usuario_id'] === null));
$minhas = array_values(array_filter($abertas, fn($t) => (int) $t['usuario_id'] === (int) $u['id']));
$atrasadas = count(array_filter($abertas, fn($t) => $t['prazo'] && strtotime($t['prazo']) < time()));
$processosFiltro = array_unique(array_column($abertas, 'proc_nome'));
sort($processosFiltro);

/** Mesmas tarefas do quadro, em formato de dados para a visão em lista (montada no navegador). */
function item_lista(array $t, string $situacao): array
{
    $prazoCls = '';
    $prazoTxt = '';
    if ($t['prazo'] && $situacao !== 'concluidas') {
        $ts = strtotime($t['prazo']);
        $prazoCls = $ts < time() ? 'vencido' : ($ts < time() + 86400 ? 'proximo' : '');
        $prazoTxt = ($ts < time() ? 'Venceu ' : 'Vence ') . tempo_relativo($t['prazo']);
    }
    return [
        'id' => (int) $t['id'],
        'nome' => $t['nome'],
        'codigo' => $t['codigo'],
        'titulo' => $t['inst_titulo'],
        'processo' => $t['proc_nome'],
        'cor' => $t['cor'],
        'grupo' => fila_tarefa($t),
        'urgente' => $t['prioridade'] === 'alta',
        'situacao' => $situacao,
        'prazo' => $t['prazo'] ? strtotime($t['prazo']) : null,
        'prazo_data' => $t['prazo'] ? data_br($t['prazo']) : '',
        'prazo_txt' => $prazoTxt,
        'prazo_cls' => $prazoCls,
        'recebida' => strtotime($t['criado_em']),
        'recebida_txt' => data_br($t['criado_em']),
        'concluida_txt' => $t['concluida_em'] ? data_br($t['concluida_em']) : '',
        'busca' => mb_strtolower($t['nome'] . ' ' . $t['codigo'] . ' ' . $t['inst_titulo'] . ' ' . $t['proc_nome']),
    ];
}
$dadosLista = array_merge(
    array_map(fn($t) => item_lista($t, 'grupo'), $doGrupo),
    array_map(fn($t) => item_lista($t, 'minhas'), $minhas),
    array_map(fn($t) => item_lista($t, 'concluidas'), $concluidas)
);

/** Rótulo da fila da tarefa: o grupo ou as pessoas escolhidas. */
function fila_tarefa(array $t): ?string
{
    if ($t['grupo_nome']) {
        return $t['grupo_nome'];
    }
    return (int) $t['n_candidatos'] > 1 ? $t['n_candidatos'] . ' pessoas' : null;
}

function cartao_tarefa(array $t, bool $concluida = false): string
{
    $prazoHtml = '';
    if (!$concluida && $t['prazo']) {
        $ts = strtotime($t['prazo']);
        $cls = $ts < time() ? 'vencido' : ($ts < time() + 86400 ? 'proximo' : '');
        $txt = $ts < time() ? 'Venceu ' . tempo_relativo($t['prazo']) : 'Vence ' . tempo_relativo($t['prazo']);
        $prazoHtml = '<span class="prazo ' . $cls . '" title="' . e(data_br($t['prazo'])) . '">' . icone('relogio', 13) . e($txt) . '</span>';
    } elseif ($concluida) {
        $prazoHtml = '<span class="prazo">' . icone('check', 13) . 'Concluída ' . e(tempo_relativo($t['concluida_em'])) . '</span>';
    } else {
        $prazoHtml = '<span>Recebida ' . e(tempo_relativo($t['criado_em'])) . '</span>';
    }
    $busca = mb_strtolower($t['nome'] . ' ' . $t['codigo'] . ' ' . $t['inst_titulo'] . ' ' . $t['proc_nome']);
    return '<a class="cartao-tarefa' . ($concluida ? ' concluida' : '') . '" href="tarefa.php?id=' . (int) $t['id'] . '" style="--cor:' . e($t['cor']) . '"'
        . ($concluida ? ' draggable="false"' : ' draggable="true"')
        . ' data-tarefa="' . (int) $t['id'] . '" data-grupo="' . (fila_tarefa($t) ? 1 : 0) . '" data-busca="' . e($busca) . '" data-proc="' . e($t['proc_nome']) . '">'
        . '<div class="ct-topo"><h4>' . e($t['nome']) . '</h4>' . ($t['prioridade'] === 'alta' && !$concluida ? '<span class="prio-alta">Urgente</span>' : '') . '</div>'
        . '<div class="ct-proc"><b>' . e($t['codigo']) . '</b> · ' . e($t['inst_titulo']) . '</div>'
        . '<div class="ct-rodape">' . $prazoHtml . (fila_tarefa($t) ? '<span class="grupo-tag">' . e(fila_tarefa($t)) . '</span>' : '') . '</div>'
        . '</a>';
}

cabecalho('Minhas tarefas', 'tarefas');
cabecalho_pagina(
    'Minhas tarefas',
    'Atividades dos processos que aguardam você ou o seu grupo. Veja em quadro (arraste para assumir ou devolver) ou em lista.',
    '<a class="btn btn-primario" href="iniciar.php">' . icone('mais', 16) . ' Iniciar processo</a>'
);
?>
<div class="resumo-tarefas">
  <div class="kpi"><span class="kpi-ic"><?= icone('usuario', 22) ?></span><div><strong data-kpi="minhas"><?= count($minhas) ?></strong><span>Comigo</span></div></div>
  <div class="kpi"><span class="kpi-ic info"><?= icone('grupo', 22) ?></span><div><strong data-kpi="grupo"><?= count($doGrupo) ?></strong><span>Disponíveis no grupo</span></div></div>
  <div class="kpi"><span class="kpi-ic alerta"><?= icone('relogio', 22) ?></span><div><strong><?= $atrasadas ?></strong><span>Atrasadas</span></div></div>
  <div class="kpi"><span class="kpi-ic lima"><?= icone('check', 22) ?></span><div><strong><?= count($concluidas) ?></strong><span>Concluídas (7 dias)</span></div></div>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="card-corpo filtros" style="padding:14px 16px">
    <label class="busca"><?= icone('busca', 16) ?><input type="search" placeholder="Buscar por tarefa, código ou título…" data-filtro-busca aria-label="Buscar tarefas"></label>
    <select data-filtro-proc aria-label="Filtrar por processo">
      <option value="">Todos os processos</option>
      <?php foreach ($processosFiltro as $pn): ?><option><?= e($pn) ?></option><?php endforeach; ?>
    </select>
    <div class="alternador" role="group" aria-label="Forma de visualização" data-alternador>
      <button type="button" class="ativo" data-visao="quadro" aria-pressed="true" title="Quadro (kanban)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="4" height="8" rx="1"/></svg><span>Quadro</span></button>
      <button type="button" data-visao="lista" aria-pressed="false" title="Lista"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></svg><span>Lista</span></button>
    </div>
  </div>
</div>

<div class="card" data-lista-tarefas hidden>
  <div class="card-cab">
    <div class="situacoes" role="group" aria-label="Filtrar por situação" data-situacoes>
      <button type="button" class="ativo" data-situacao="">Todas <span class="num" data-conta-sit=""></span></button>
      <button type="button" data-situacao="grupo">Disponíveis <span class="num" data-conta-sit="grupo"></span></button>
      <button type="button" data-situacao="minhas">Comigo <span class="num" data-conta-sit="minhas"></span></button>
      <button type="button" data-situacao="concluidas">Concluídas <span class="num" data-conta-sit="concluidas"></span></button>
    </div>
  </div>
  <div class="tabela-wrap">
    <table class="tabela tabela-responsiva tabela-tarefas">
      <thead><tr>
        <th><button type="button" class="ordenar" data-ordem="nome">Tarefa</button></th>
        <th>Processo</th>
        <th>Situação</th>
        <th><button type="button" class="ordenar ativo" data-ordem="prazo">Prazo</button></th>
        <th><button type="button" class="ordenar" data-ordem="recebida">Recebida</button></th>
        <th class="acoes">Ações</th>
      </tr></thead>
      <tbody data-linhas></tbody>
    </table>
  </div>
  <div class="vazio-mini" data-lista-vazia hidden>Nenhuma tarefa com esses filtros.</div>
</div>

<div class="kanban" data-kanban>
  <section class="coluna" data-coluna="grupo">
    <div class="coluna-cab"><h3><i style="background:var(--info)"></i>Disponíveis</h3><small data-conta><?= count($doGrupo) ?></small></div>
    <div class="coluna-lista">
      <?php foreach ($doGrupo as $t) echo cartao_tarefa($t); ?>
      <div class="coluna-dica" <?= $doGrupo ? 'hidden' : '' ?> data-dica>Nenhuma tarefa aguardando o seu grupo.</div>
    </div>
  </section>
  <section class="coluna" data-coluna="minhas">
    <div class="coluna-cab"><h3><i style="background:var(--verde)"></i>Comigo</h3><small data-conta><?= count($minhas) ?></small></div>
    <div class="coluna-lista">
      <?php foreach ($minhas as $t) echo cartao_tarefa($t); ?>
      <div class="coluna-dica" <?= $minhas ? 'hidden' : '' ?> data-dica>Arraste tarefas do grupo para cá para assumi-las.</div>
    </div>
  </section>
  <section class="coluna" data-coluna="concluidas">
    <div class="coluna-cab"><h3><i style="background:var(--lima)"></i>Concluídas</h3><small>últimos 7 dias</small></div>
    <div class="coluna-lista">
      <?php foreach ($concluidas as $t) echo cartao_tarefa($t, true); ?>
      <div class="coluna-dica" <?= $concluidas ? 'hidden' : '' ?> data-dica>Solte aqui uma tarefa sua para abri-la e concluir.</div>
    </div>
  </section>
</div>
<?= dados_js('tarefas-lista', $dadosLista) ?>
<?php rodape(['assets/js/tarefas.js']);
