<?php
require __DIR__ . '/app/nucleo.php';
$u = exigir_login();

$t = linha('SELECT t.*, g.nome AS grupo_nome, ur.nome AS resp_nome, uc.nome AS concl_nome FROM tarefas t
            LEFT JOIN grupos g ON g.id = t.grupo_id LEFT JOIN usuarios ur ON ur.id = t.usuario_id LEFT JOIN usuarios uc ON uc.id = t.concluida_por
            WHERE t.id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$t) {
    pagina_erro('Tarefa não encontrada', 'Ela pode ter sido removida ou o link está incorreto.');
}
$inst = carregar_instancia((int) $t['instancia_id']);
$podeAgir = $t['status'] === 'aberta' && tarefa_visivel($t, $u);
if (!$podeAgir && !pode_ver_instancia($inst, $u)) {
    pagina_erro('Sem acesso', 'Esta tarefa pertence a outra pessoa ou grupo.');
}
$modelo = modelo_versao((int) $inst['versao_id']);
$c = cfg_elemento($modelo, $t['elemento_id']);
$vars = json_decode_arr($inst['variaveis']);
$campos = $c['formulario'] ?? [];
$decisoes = array_values(array_filter(array_map('trim', $c['decisoes'] ?? [])));
$compartilhada = tarefa_compartilhada($t);
$candidatos = array_column(linhas('SELECT u.nome FROM tarefa_candidatos tc JOIN usuarios u ON u.id = tc.usuario_id WHERE tc.tarefa_id = ? ORDER BY u.nome', [$t['id']]), 'nome');
$ehMinha = (int) $t['usuario_id'] === (int) $u['id'];
$noGrupo = $t['usuario_id'] === null;

$meta = '';
if ($t['prazo'] && $t['status'] === 'aberta') {
    $venc = strtotime($t['prazo']) < time();
    $meta .= badge(($venc ? 'Venceu ' : 'Prazo ') . data_br($t['prazo']), $venc ? 'erro' : 'alerta');
}
if ($t['prioridade'] === 'alta') {
    $meta .= badge('Urgente', 'erro');
}

cabecalho($t['nome'], 'tarefas', ['css' => CSS_VISUALIZADOR]);
echo '<a class="voltar" href="tarefas.php">' . icone('seta', 14) . ' Voltar para minhas tarefas</a>';
cabecalho_instancia($inst, $t['nome'], $meta, '<a class="btn" href="processo.php?id=' . (int) $inst['id'] . '">' . icone('olho', 16) . ' Ver processo</a>');
?>
<div class="etapas">
  <span class="etapa feita"><b>1</b>Recebida <?= e(tempo_relativo($t['criado_em'])) ?></span>
  <span class="etapa <?= $t['status'] === 'aberta' && !$noGrupo ? 'ativa' : ($t['assumida_em'] || $t['status'] === 'concluida' ? 'feita' : '') ?>"><b>2</b><?= $noGrupo ? 'Aguardando alguém assumir' : 'Com ' . e($t['resp_nome'] ?? '—') ?></span>
  <span class="etapa <?= $t['status'] === 'concluida' ? 'ativa' : '' ?>"><b>3</b><?= $t['status'] === 'concluida' ? 'Concluída por ' . e($t['concl_nome']) : ($t['status'] === 'cancelada' ? 'Cancelada' : 'Conclusão') ?></span>
</div>

<div class="layout-detalhe">
  <div>
    <div class="card" data-tarefa-form="<?= (int) $t['id'] ?>">
      <div class="card-cab">
        <div><h2><?= $podeAgir ? 'Execute a atividade' : 'Dados registrados na tarefa' ?></h2>
        <p><?= $t['grupo_nome'] ? 'Tarefa do grupo ' . e($t['grupo_nome']) . '.' : ($candidatos ? 'Tarefa para ' . e(implode(', ', $candidatos)) . ' (quem assumir primeiro).' : 'Tarefa individual.') ?> <?= $podeAgir ? 'Preencha os campos e conclua para o processo seguir.' : '' ?></p></div>
        <?php if ($podeAgir && $noGrupo): ?><span class="badge badge-info"><?= $t['grupo_nome'] ? 'Disponível para o grupo' : 'Disponível para as pessoas indicadas' ?></span><?php endif; ?>
      </div>
      <div class="card-corpo">
        <?php if (!empty($c['instrucoes'])): ?><div class="instrucoes"><?= e(modelo_texto($c['instrucoes'], $vars)) ?></div><?php endif; ?>
        <?php if ($podeAgir): ?>
          <?= $campos ? formulario_html($campos, $vars) : '<p style="margin:0;color:var(--texto-2)">Esta etapa não tem formulário. ' . ($decisoes ? 'Escolha uma decisão abaixo.' : 'Clique em concluir quando terminar a atividade.') . '</p>' ?>
        <?php elseif ($t['status'] === 'concluida'): ?>
          <?php $dados = json_decode_arr($t['dados']); $rot = rotulos_variaveis($modelo); ?>
          <?php if (!$dados): ?><p style="margin:0;color:var(--texto-2)">Concluída sem dados de formulário.</p><?php else: ?>
          <dl class="dados-lista"><?php foreach ($dados as $k => $v): ?><div><dt><?= e($rot[$k]['rotulo'] ?? $k) ?></dt><dd><?= e(valor_exibicao($v, $rot[$k]['tipo'] ?? 'texto')) ?></dd></div><?php endforeach; ?></dl>
          <?php endif; ?>
        <?php else: ?>
          <div class="alerta alerta-info"><?= icone('alerta', 18) ?><span>Esta tarefa está <?= $t['status'] === 'cancelada' ? 'cancelada' : 'com ' . e($t['resp_nome'] ?? 'outro grupo') ?>.</span></div>
        <?php endif; ?>
      </div>
      <?php if ($podeAgir): ?>
      <div class="card-rodape" style="justify-content:space-between">
        <div class="decisoes">
          <?php if ($noGrupo): ?><button class="btn" type="button" data-acao="assumir"><?= icone('usuario', 16) ?> Assumir para mim</button><?php endif; ?>
          <?php if ($ehMinha && $compartilhada): ?><button class="btn" type="button" data-acao="devolver"><?= icone('grupo', 16) ?> <?= $t['grupo_id'] ? 'Devolver ao grupo' : 'Devolver' ?></button><?php endif; ?>
          <?php if (eh_admin()): ?><button class="btn" type="button" data-acao="reatribuir"><?= icone('pessoa', 16) ?> Reatribuir</button><?php endif; ?>
        </div>
        <div class="decisoes">
          <?php if ($decisoes): foreach ($decisoes as $i => $d):
              $neg = preg_match('/reprov|recus|negar|neg[ao]|cancel|devolv|rejeit/i', $d);
              $cor = cor_botao($c['cores_botoes'][$d] ?? null); ?>
            <button class="btn btn-decisao <?= $cor ? '' : ($neg ? 'btn-perigo' : 'btn-primario') ?>" type="button" data-concluir data-decisao="<?= e($d) ?>"<?= $cor ? ' style="' . e($cor) . '"' : '' ?>><?= $neg ? icone('x', 16) : icone('check', 16) ?> <?= e($d) ?></button>
          <?php endforeach; else: ?>
            <?php $cor = cor_botao($c['cor_concluir'] ?? null); ?><button class="btn btn-lg <?= $cor ? '' : 'btn-primario' ?>" type="button" data-concluir<?= $cor ? ' style="' . e($cor) . '"' : '' ?>>Concluir tarefa <?= icone('seta', 16) ?></button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?= card_diagrama($inst, 'Onde esta tarefa está no fluxo') ?>
  </div>
  <aside>
    <?= card_dados($inst) ?>
    <?= card_comentarios((int) $inst['id']) ?>
    <?= card_historico((int) $inst['id']) ?>
  </aside>
</div>
<?php
if (eh_admin()) {
    echo dados_js('usuarios-ativos', linhas('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome'));
}
echo dados_js('tarefa-destaque', $t['elemento_id']);
rodape(array_merge(JS_VISUALIZADOR, ['assets/js/tarefa.js']));
