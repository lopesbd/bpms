<?php
require __DIR__ . '/app/nucleo.php';
$u = exigir_login();

$aba = $_GET['aba'] ?? 'iniciados';
if (!in_array($aba, ['iniciados', 'participei', 'todos'], true) || ($aba === 'todos' && !eh_admin())) {
    $aba = 'iniciados';
}
$status = $_GET['status'] ?? '';
$procId = (int) ($_GET['processo'] ?? 0);
$busca = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['pg'] ?? 1));
$porPagina = 25;

$participei = "EXISTS (SELECT 1 FROM tarefas tx WHERE tx.instancia_id = i.id AND tx.concluida_por = ?) AND (i.iniciado_por IS NULL OR i.iniciado_por <> ?)";
$condAba = [
    'iniciados' => ['i.iniciado_por = ?', [$u['id']]],
    'participei' => [$participei, [$u['id'], $u['id']]],
    'todos' => ['1=1', []],
];

function montar_filtro(array $cond, string $status, int $procId, string $busca): array
{
    [$where, $p] = $cond;
    if (in_array($status, ['andamento', 'concluida', 'cancelada', 'erro'], true)) {
        $where .= ' AND i.status = ?';
        $p[] = $status;
    }
    if ($procId) {
        $where .= ' AND i.processo_id = ?';
        $p[] = $procId;
    }
    if ($busca !== '') {
        $where .= ' AND (i.codigo LIKE ? OR i.titulo LIKE ?)';
        $p[] = '%' . $busca . '%';
        $p[] = '%' . $busca . '%';
    }
    return [$where, $p];
}

$contagens = [];
foreach ($condAba as $k => $c) {
    if ($k === 'todos' && !eh_admin()) {
        continue;
    }
    $contagens[$k] = (int) valor('SELECT COUNT(*) FROM instancias i WHERE ' . $c[0], $c[1]);
}

[$where, $params] = montar_filtro($condAba[$aba], $status, $procId, $busca);
$total = (int) valor("SELECT COUNT(*) FROM instancias i WHERE $where", $params);
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$lista = linhas(
    "SELECT i.*, p.nome AS proc_nome, p.cor, p.icone, u.nome AS iniciador,
       (SELECT GROUP_CONCAT(t.nome ORDER BY t.id SEPARATOR ' · ') FROM tarefas t WHERE t.instancia_id = i.id AND t.status = 'aberta') AS etapas,
       (SELECT MAX(h.criado_em) FROM historico h WHERE h.instancia_id = i.id) AS atualizado
     FROM instancias i JOIN processos p ON p.id = i.processo_id LEFT JOIN usuarios u ON u.id = i.iniciado_por
     WHERE $where ORDER BY i.id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina),
    $params
);
$processos = linhas('SELECT id, nome FROM processos ORDER BY nome');

function link_lista(array $mudar): string
{
    $q = array_merge($_GET, $mudar);
    return 'processos.php?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null && $v !== 0));
}

cabecalho('Meus processos', 'processos');
cabecalho_pagina('Acompanhar processos', 'Processos que você iniciou ou dos quais participou, com a etapa em que cada um está agora.', '<a class="btn btn-primario" href="iniciar.php">' . icone('mais', 16) . ' Iniciar processo</a>');
$nomesAba = ['iniciados' => 'Iniciados por mim', 'participei' => 'Participei', 'todos' => 'Todos'];
?>
<div class="card">
  <nav class="abas">
    <?php foreach ($contagens as $k => $n): ?>
      <a class="aba<?= $aba === $k ? ' ativa' : '' ?>" href="processos.php?aba=<?= $k ?>"><?= $nomesAba[$k] ?> <span class="num"><?= $n ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form class="card-corpo filtros" method="get" style="padding:14px 16px;border-bottom:1px solid var(--borda)">
    <input type="hidden" name="aba" value="<?= e($aba) ?>">
    <label class="busca"><?= icone('busca', 16) ?><input type="search" name="q" value="<?= e($busca) ?>" placeholder="Código ou título…" aria-label="Buscar"></label>
    <select name="status" aria-label="Status" data-auto-enviar>
      <option value="">Todos os status</option>
      <?php foreach (['andamento', 'concluida', 'cancelada', 'erro'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(status_instancia($s)[0]) ?></option><?php endforeach; ?>
    </select>
    <select name="processo" aria-label="Processo" data-auto-enviar>
      <option value="">Todos os processos</option>
      <?php foreach ($processos as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $procId === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtrar</button>
  </form>
  <?php if (!$lista): ?>
    <div class="estado-vazio"><?= icone('processos', 40) ?><h3>Nada por aqui</h3><p><?= $busca || $status || $procId ? 'Nenhum processo corresponde aos filtros.' : ($aba === 'participei' ? 'Quando você concluir tarefas em processos de outras pessoas, eles aparecem aqui.' : 'Você ainda não iniciou nenhum processo.') ?></p></div>
  <?php else: ?>
  <div class="tabela-wrap">
    <table class="tabela tabela-responsiva">
      <thead><tr><th>Código</th><th>Processo</th><th>Etapa atual</th><th>Status</th><th>Iniciado</th><th>Última movimentação</th></tr></thead>
      <tbody>
      <?php foreach ($lista as $i): [$st, $tp] = status_instancia($i['status']); ?>
        <tr data-href="processo.php?id=<?= (int) $i['id'] ?>">
          <td><a href="processo.php?id=<?= (int) $i['id'] ?>"><strong><?= e($i['codigo']) ?></strong></a></td>
          <td><strong><?= e($i['titulo']) ?></strong><span class="sub"><span style="color:<?= e($i['cor']) ?>">●</span> <?= e($i['proc_nome']) ?><?= $aba !== 'iniciados' ? ' · por ' . e($i['iniciador'] ?? '—') : '' ?></span></td>
          <td data-rotulo="Etapa"><?= $i['etapas'] ? e($i['etapas']) : '<span class="sub">—</span>' ?></td>
          <td><?= badge($st, $tp) ?></td>
          <td data-rotulo="Início"><?= e(data_br($i['iniciado_em'])) ?></td>
          <td data-rotulo="Movimentação"><?= e(tempo_relativo($i['atualizado'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($paginas > 1): ?>
  <div class="card-rodape" style="justify-content:space-between;align-items:center">
    <small style="color:var(--texto-2)"><?= $total ?> processos · página <?= $pagina ?> de <?= $paginas ?></small>
    <div class="decisoes">
      <a class="btn btn-sm<?= $pagina <= 1 ? ' desab' : '' ?>" href="<?= e(link_lista(['pg' => $pagina - 1])) ?>">Anterior</a>
      <a class="btn btn-sm<?= $pagina >= $paginas ? ' desab' : '' ?>" href="<?= e(link_lista(['pg' => $pagina + 1])) ?>">Próxima</a>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php rodape(['assets/js/lista.js']);
