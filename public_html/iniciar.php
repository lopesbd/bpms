<?php
require __DIR__ . '/app/nucleo.php';
$u = exigir_login();

$disponiveis = array_values(array_filter(
    linhas("SELECT p.*, (SELECT COUNT(*) FROM instancias i WHERE i.processo_id = p.id) AS total
            FROM processos p WHERE p.ativo = 1 AND p.versao_publicada_id IS NOT NULL ORDER BY p.categoria, p.nome"),
    fn($p) => pode_iniciar($p, $u)
));

$escolhido = null;
if (!empty($_GET['p'])) {
    foreach ($disponiveis as $p) {
        if ((int) $p['id'] === (int) $_GET['p']) {
            $escolhido = $p;
        }
    }
    if (!$escolhido) {
        pagina_erro('Processo indisponível', 'Este processo não existe, não está publicado ou você não tem permissão para iniciá-lo.');
    }
}

if ($escolhido) {
    $modelo = modelo_versao((int) $escolhido['versao_publicada_id']);
    $inicio = null;
    foreach ($modelo['elementos'] as $el) {
        if ($el['categoria'] === 'inicio') {
            $inicio = $el;
            break;
        }
    }
    $cfgInicio = $inicio ? cfg_elemento($modelo, $inicio['id']) : [];
    $campos = $cfgInicio['formulario'] ?? [];
    $etapas = array_filter($modelo['elementos'], fn($e) => $e['categoria'] === 'humana');

    cabecalho('Iniciar ' . $escolhido['nome'], 'iniciar', ['css' => CSS_VISUALIZADOR]);
    echo '<a class="voltar" href="iniciar.php">' . icone('seta', 14) . ' Escolher outro processo</a>';
    ?>
    <div class="pagina-topo"><div class="cab-processo"><span class="cp-ic" style="background:<?= e($escolhido['cor']) ?>"><?= icone($escolhido['icone'], 24) ?></span><div>
      <h1><?= e($escolhido['nome']) ?></h1><div class="meta"><?= e($escolhido['descricao'] ?: 'Preencha os dados para iniciar.') ?></div></div></div></div>
    <div class="etapas"><span class="etapa feita"><b>1</b>Processo</span><span class="etapa ativa"><b>2</b>Dados iniciais</span><span class="etapa"><b>3</b>Acompanhamento</span></div>
    <div class="layout-detalhe">
      <div class="card" data-iniciar="<?= (int) $escolhido['id'] ?>">
        <div class="card-cab"><div><h2>Dados da solicitação</h2><p>Depois de iniciado, o processo segue automaticamente pelas etapas do fluxo.</p></div></div>
        <div class="card-corpo">
          <?php if (!empty($cfgInicio['instrucoes'])): ?><div class="instrucoes"><?= e($cfgInicio['instrucoes']) ?></div><?php endif; ?>
          <?= $campos ? formulario_html($campos, []) : '<p style="margin:0;color:var(--texto-2)">Este processo não pede dados iniciais. Clique em iniciar para começar.</p>' ?>
        </div>
        <div class="card-rodape"><a class="btn" href="iniciar.php">Cancelar</a><button class="btn btn-primario btn-lg" type="button" data-iniciar-btn>Iniciar processo <?= icone('seta', 16) ?></button></div>
      </div>
      <aside>
        <div class="card"><div class="card-cab"><h3>Como funciona</h3></div><div class="card-corpo">
          <ul class="linha-tempo">
            <li class="ok"><span class="lt-ic"><?= icone('iniciar', 11) ?></span>Você inicia a solicitação</li>
            <?php foreach (array_slice($etapas, 0, 6) as $et): ?><li class="info"><span class="lt-ic"><?= icone('tarefas', 11) ?></span><?= e(nome_elemento($modelo, $et['id'])) ?></li><?php endforeach; ?>
            <?php if (count($etapas) > 6): ?><li><span class="lt-ic">…</span>e mais <?= count($etapas) - 6 ?> etapas</li><?php endif; ?>
            <li class="ok"><span class="lt-ic"><?= icone('check', 11) ?></span>Conclusão e aviso para você</li>
          </ul>
        </div></div>
      </aside>
    </div>
    <?php
    rodape(['assets/js/iniciar.js']);
    exit;
}

cabecalho('Iniciar processo', 'iniciar');
cabecalho_pagina('Iniciar processo', 'Escolha o tipo de solicitação. Ela será distribuída automaticamente para as pessoas responsáveis.');
if (!$disponiveis) {
    echo '<div class="card"><div class="estado-vazio grande">' . icone('fluxo', 44) . '<h2>Nenhum processo disponível</h2><p>Ainda não há processos publicados para você iniciar.</p>'
        . (eh_admin() ? '<a class="btn btn-primario" href="fluxos.php">' . icone('mais', 16) . ' Criar um fluxo</a>' : '') . '</div></div>';
    rodape();
    exit;
}
?>
<div class="card" style="margin-bottom:20px"><div class="card-corpo filtros" style="padding:14px 16px">
  <label class="busca"><?= icone('busca', 16) ?><input type="search" placeholder="Buscar processo…" data-busca-proc aria-label="Buscar processo"></label>
</div></div>
<div data-catalogo>
<?php
$porCategoria = [];
foreach ($disponiveis as $p) {
    $porCategoria[$p['categoria'] ?: 'Geral'][] = $p;
}
foreach ($porCategoria as $cat => $lista): ?>
  <div data-grupo-cat>
  <h2 class="categoria-titulo"><?= e($cat) ?></h2>
  <div class="grade-processos">
    <?php foreach ($lista as $p): ?>
    <a class="cartao-proc" href="iniciar.php?p=<?= (int) $p['id'] ?>" style="--cor:<?= e($p['cor']) ?>" data-busca="<?= e(mb_strtolower($p['nome'] . ' ' . $p['descricao'] . ' ' . $cat)) ?>">
      <span class="cp-ic"><?= icone($p['icone'], 24) ?></span>
      <h3><?= e($p['nome']) ?></h3>
      <p><?= e($p['descricao'] ?: 'Sem descrição.') ?></p>
      <div class="cp-rodape"><span><?= (int) $p['total'] ?> iniciados</span><span class="cp-ir">Iniciar <?= icone('seta', 14) ?></span></div>
    </a>
    <?php endforeach; ?>
  </div>
  </div>
<?php endforeach; ?>
</div>
<?php rodape(['assets/js/iniciar.js']);
