<?php
require __DIR__ . '/app/nucleo.php';
exigir_admin();

$kpi = linha("SELECT
    (SELECT COUNT(*) FROM instancias WHERE status = 'andamento') AS andamento,
    (SELECT COUNT(*) FROM instancias WHERE status = 'erro') AS erro,
    (SELECT COUNT(*) FROM instancias WHERE status = 'concluida' AND concluido_em >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS concluidas_mes,
    (SELECT COUNT(*) FROM tarefas WHERE status = 'aberta') AS tarefas_abertas,
    (SELECT COUNT(*) FROM tarefas WHERE status = 'aberta' AND prazo IS NOT NULL AND prazo < NOW()) AS atrasadas,
    (SELECT AVG(TIMESTAMPDIFF(SECOND, iniciado_em, concluido_em)) FROM instancias WHERE status = 'concluida' AND concluido_em >= NOW() - INTERVAL 90 DAY) AS media");

// iniciados por mês (6 meses)
$meses = [];
for ($i = 5; $i >= 0; $i--) {
    $meses[date('Y-m', strtotime("first day of -$i month"))] = 0;
}
foreach (linhas("SELECT DATE_FORMAT(iniciado_em, '%Y-%m') AS m, COUNT(*) AS n FROM instancias WHERE iniciado_em >= ? GROUP BY m", [array_key_first($meses) . '-01']) as $r) {
    $meses[$r['m']] = (int) $r['n'];
}
$maxMes = max(1, max($meses));
$nomesMes = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];

$porProcesso = linhas("SELECT p.nome, p.cor, COUNT(i.id) AS total, SUM(i.status = 'andamento') AS abertas
                       FROM processos p LEFT JOIN instancias i ON i.processo_id = p.id GROUP BY p.id ORDER BY total DESC LIMIT 8");
$maxProc = max(1, ...array_map(fn($r) => (int) $r['total'], $porProcesso ?: [['total' => 1]]));

$gargalos = linhas("SELECT t.nome, p.cor, p.sigla, COUNT(*) AS n, AVG(TIMESTAMPDIFF(SECOND, t.criado_em, t.concluida_em)) AS media
                    FROM tarefas t JOIN instancias i ON i.id = t.instancia_id JOIN processos p ON p.id = i.processo_id
                    WHERE t.status = 'concluida' AND t.concluida_em >= NOW() - INTERVAL 90 DAY
                    GROUP BY t.nome, p.id ORDER BY media DESC LIMIT 8");
$maxGarg = max(1, ...array_map(fn($r) => (int) $r['media'], $gargalos ?: [['media' => 1]]));

$carga = linhas("SELECT u.nome, COUNT(t.id) AS n, SUM(t.prazo IS NOT NULL AND t.prazo < NOW()) AS atrasadas
                 FROM tarefas t JOIN usuarios u ON u.id = t.usuario_id WHERE t.status = 'aberta' GROUP BY u.id ORDER BY n DESC LIMIT 8");
$maxCarga = max(1, ...array_map(fn($r) => (int) $r['n'], $carga ?: [['n' => 1]]));

$atrasadas = linhas("SELECT t.id, t.nome, t.prazo, i.codigo, i.titulo, u.nome AS resp, g.nome AS grupo
                     FROM tarefas t JOIN instancias i ON i.id = t.instancia_id LEFT JOIN usuarios u ON u.id = t.usuario_id LEFT JOIN grupos g ON g.id = t.grupo_id
                     WHERE t.status = 'aberta' AND t.prazo IS NOT NULL AND t.prazo < NOW() ORDER BY t.prazo LIMIT 10");

cabecalho('Painel', 'painel');
cabecalho_pagina('Painel de processos', 'Visão geral da operação: volume, gargalos e tarefas em atraso.');
?>
<div class="resumo-tarefas" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
  <div class="kpi"><span class="kpi-ic info"><?= icone('processos', 22) ?></span><div><strong><?= (int) $kpi['andamento'] ?></strong><span>Em andamento</span></div></div>
  <div class="kpi"><span class="kpi-ic"><?= icone('check', 22) ?></span><div><strong><?= (int) $kpi['concluidas_mes'] ?></strong><span>Concluídos no mês</span></div></div>
  <div class="kpi"><span class="kpi-ic lima"><?= icone('tarefas', 22) ?></span><div><strong><?= (int) $kpi['tarefas_abertas'] ?></strong><span>Tarefas abertas</span></div></div>
  <div class="kpi"><span class="kpi-ic alerta"><?= icone('relogio', 22) ?></span><div><strong><?= (int) $kpi['atrasadas'] ?></strong><span>Tarefas atrasadas</span></div></div>
  <div class="kpi"><span class="kpi-ic"><?= icone('raio', 22) ?></span><div><strong><?= $kpi['media'] ? e(duracao((int) $kpi['media'])) : '—' ?></strong><span>Duração média (90 dias)</span></div></div>
  <?php if ((int) $kpi['erro']): ?><a class="kpi" href="processos.php?aba=todos&status=erro" style="color:inherit"><span class="kpi-ic alerta"><?= icone('alerta', 22) ?></span><div><strong><?= (int) $kpi['erro'] ?></strong><span>Com erro: ver</span></div></a><?php endif; ?>
</div>

<div class="grade-painel">
  <div class="card"><div class="card-cab"><h3>Processos iniciados por mês</h3></div><div class="card-corpo">
    <div class="colunas-grafico">
      <?php foreach ($meses as $m => $n): ?><div class="col"><i data-altura="<?= round($n / $maxMes * 100) ?>"><b><?= $n ?></b></i><span><?= $nomesMes[substr($m, 5)] ?></span></div><?php endforeach; ?>
    </div>
  </div></div>

  <div class="card"><div class="card-cab"><h3>Execuções por processo</h3></div><div class="card-corpo">
    <?php if (!$porProcesso): ?><div class="vazio-mini">Sem dados ainda.</div><?php endif; ?>
    <div class="barras"><?php foreach ($porProcesso as $r): ?>
      <div class="barra-linha"><span class="rot" title="<?= e($r['nome']) ?>"><?= e($r['nome']) ?></span><span class="trilho"><i style="background:<?= e($r['cor']) ?>" data-largura="<?= round($r['total'] / $maxProc * 100) ?>"></i></span><span class="val"><?= (int) $r['total'] ?></span></div>
    <?php endforeach; ?></div>
  </div></div>

  <div class="card"><div class="card-cab"><div><h3>Gargalos: tempo médio por etapa</h3><p>Tarefas concluídas nos últimos 90 dias.</p></div></div><div class="card-corpo">
    <?php if (!$gargalos): ?><div class="vazio-mini">Nenhuma tarefa concluída no período.</div><?php endif; ?>
    <div class="barras"><?php foreach ($gargalos as $r): ?>
      <div class="barra-linha"><span class="rot" title="<?= e($r['nome']) ?>"><span class="tag"><?= e($r['sigla']) ?></span> <?= e($r['nome']) ?></span><span class="trilho"><i style="background:<?= e($r['cor']) ?>" data-largura="<?= round($r['media'] / $maxGarg * 100) ?>"></i></span><span class="val"><?= e(duracao((int) $r['media'])) ?></span></div>
    <?php endforeach; ?></div>
  </div></div>

  <div class="card"><div class="card-cab"><div><h3>Carga por pessoa</h3><p>Tarefas abertas atribuídas a cada um.</p></div></div><div class="card-corpo">
    <?php if (!$carga): ?><div class="vazio-mini">Ninguém com tarefas abertas.</div><?php endif; ?>
    <div class="barras"><?php foreach ($carga as $r): ?>
      <div class="barra-linha"><span class="rot"><?= e($r['nome']) ?></span><span class="trilho"><i data-largura="<?= round(($r['n'] - $r['atrasadas']) / $maxCarga * 100) ?>"></i><i style="background:var(--erro);border-radius:0 6px 6px 0" data-largura="<?= round($r['atrasadas'] / $maxCarga * 100) ?>"></i></span><span class="val"><?= (int) $r['n'] ?></span></div>
    <?php endforeach; ?></div>
    <?php if ($carga): ?><div class="diagrama-legenda" style="margin-top:12px"><span><i style="background:var(--verde)"></i>No prazo</span><span><i style="background:var(--erro)"></i>Atrasadas</span></div><?php endif; ?>
  </div></div>
</div>

<div class="card" style="margin-top:18px"><div class="card-cab"><h3>Tarefas atrasadas</h3><?= badge((string) count($atrasadas), count($atrasadas) ? 'erro' : 'ok') ?></div>
  <?php if (!$atrasadas): ?><div class="vazio-mini">Nenhuma tarefa atrasada. 🎉</div><?php else: ?>
  <div class="tabela-wrap"><table class="tabela tabela-responsiva"><thead><tr><th>Tarefa</th><th>Processo</th><th>Responsável</th><th>Venceu</th></tr></thead><tbody>
  <?php foreach ($atrasadas as $t): ?>
    <tr data-href="tarefa.php?id=<?= (int) $t['id'] ?>"><td><strong><?= e($t['nome']) ?></strong></td><td data-rotulo="Processo"><?= e($t['codigo']) ?> · <?= e($t['titulo']) ?></td><td data-rotulo="Responsável"><?= e($t['resp'] ?? ($t['grupo'] ? 'Grupo ' . $t['grupo'] : 'Pessoas indicadas')) ?></td><td><span class="prazo vencido"><?= e(tempo_relativo($t['prazo'])) ?></span></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
<?php rodape();
