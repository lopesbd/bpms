<?php
require __DIR__ . '/app/nucleo.php';
$u = exigir_login();

$inst = carregar_instancia((int) ($_GET['id'] ?? 0));
if (!$inst || !pode_ver_instancia($inst, $u)) {
    pagina_erro('Processo não encontrado', 'Ele não existe ou você não participa dele.');
}
$id = (int) $inst['id'];
$tarefas = linhas(
    'SELECT t.*, ur.nome AS resp_nome, uc.nome AS concl_nome, g.nome AS grupo_nome FROM tarefas t
     LEFT JOIN usuarios ur ON ur.id = t.usuario_id LEFT JOIN usuarios uc ON uc.id = t.concluida_por LEFT JOIN grupos g ON g.id = t.grupo_id
     WHERE t.instancia_id = ? ORDER BY t.id',
    [$id]
);
$erros = linhas("SELECT elemento_id, erro FROM tokens WHERE instancia_id = ? AND status = 'erro'", [$id]);
$anexos = linhas('SELECT a.*, u.nome FROM anexos a LEFT JOIN usuarios u ON u.id = a.enviado_por WHERE a.instancia_id = ? ORDER BY a.id', [$id]);

$acoes = '';
$ativo = in_array($inst['status'], ['andamento', 'erro'], true);
if ($ativo && $erros && eh_admin()) {
    $acoes .= '<button class="btn btn-escuro" type="button" data-instancia-acao="repetir" data-id="' . $id . '">' . icone('raio', 16) . ' Reexecutar etapas com erro</button>';
}
if ($ativo && (eh_admin() || (int) $inst['iniciado_por'] === (int) $u['id'])) {
    $acoes .= '<button class="btn btn-perigo" type="button" data-instancia-acao="cancelar" data-id="' . $id . '">' . icone('x', 16) . ' Cancelar</button>';
}
$duracao = (strtotime($inst['concluido_em'] ?? agora()) - strtotime($inst['iniciado_em']));

cabecalho($inst['codigo'], 'processos', ['css' => CSS_VISUALIZADOR]);
echo '<a class="voltar" href="processos.php">' . icone('seta', 14) . ' Voltar para processos</a>';
cabecalho_instancia($inst, $inst['titulo'], '<span>· ' . ($inst['concluido_em'] ? 'durou ' : 'aberto há ') . e(duracao($duracao)) . '</span>', $acoes);

foreach ($erros as $er): ?>
  <div class="alerta alerta-erro"><?= icone('alerta', 18) ?><div><strong>Uma etapa automática falhou.</strong><br><?= e($er['erro']) ?><?= eh_admin() ? '<br><small>Corrija a causa (configuração da automação ou do servidor) e clique em "Reexecutar etapas com erro".</small>' : '<br><small>Os administradores já foram avisados.</small>' ?></div></div>
<?php endforeach; ?>

<div class="layout-detalhe">
  <div>
    <?= card_diagrama($inst) ?>
    <div class="card">
      <div class="card-cab"><h3>Tarefas</h3><small class="tag"><?= count($tarefas) ?></small></div>
      <?php if (!$tarefas): ?><div class="vazio-mini">Este processo ainda não gerou tarefas humanas.</div><?php else: ?>
      <div class="tabela-wrap"><table class="tabela tabela-responsiva">
        <thead><tr><th>Etapa</th><th>Responsável</th><th>Situação</th><th>Recebida</th><th>Concluída</th></tr></thead>
        <tbody>
        <?php foreach ($tarefas as $t):
            $sit = ['aberta' => ['Aberta', 'info'], 'concluida' => ['Concluída', 'ok'], 'cancelada' => ['Cancelada', 'neutro']][$t['status']];
            if ($t['status'] === 'aberta' && $t['prazo'] && strtotime($t['prazo']) < time()) {
                $sit = ['Atrasada', 'erro'];
            }
            $quem = $t['status'] === 'concluida' ? $t['concl_nome'] : ($t['resp_nome'] ?? ($t['grupo_nome'] ? 'Grupo ' . $t['grupo_nome'] : 'Pessoas indicadas (ninguém assumiu)')); ?>
          <tr data-href="tarefa.php?id=<?= (int) $t['id'] ?>">
            <td><a href="tarefa.php?id=<?= (int) $t['id'] ?>"><strong><?= e($t['nome']) ?></strong></a></td>
            <td data-rotulo="Responsável"><?= e($quem ?? '—') ?></td>
            <td><?= badge($sit[0], $sit[1]) ?></td>
            <td data-rotulo="Recebida"><?= e(data_br($t['criado_em'])) ?></td>
            <td data-rotulo="Concluída"><?= e(data_br($t['concluida_em'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
    <?php if ($anexos): ?>
    <div class="card"><div class="card-cab"><h3>Anexos</h3><small class="tag"><?= count($anexos) ?></small></div><div class="card-corpo"><ul class="upload-lista" style="margin:0">
      <?php foreach ($anexos as $a): ?><li><a href="arquivo.php?id=<?= (int) $a['id'] ?>" target="_blank" rel="noopener"><?= e($a['nome_original']) ?></a><small style="color:var(--texto-3);white-space:nowrap"><?= e(number_format($a['tamanho'] / 1024, 0, ',', '.')) ?> KB · <?= e($a['nome'] ?? '') ?></small></li><?php endforeach; ?>
    </ul></div></div>
    <?php endif; ?>
  </div>
  <aside>
    <?= card_dados($inst) ?>
    <?= card_comentarios($id) ?>
    <?= card_historico($id) ?>
  </aside>
</div>
<?php rodape(JS_VISUALIZADOR);
