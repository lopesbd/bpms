<?php
require __DIR__ . '/app/nucleo.php';
exigir_admin();

$lista = linhas(
    "SELECT p.*, vp.numero AS versao_pub,
       (SELECT numero FROM processo_versoes v WHERE v.processo_id = p.id AND v.status = 'rascunho' ORDER BY numero DESC LIMIT 1) AS versao_rasc,
       (SELECT COUNT(*) FROM instancias i WHERE i.processo_id = p.id) AS total,
       (SELECT COUNT(*) FROM instancias i WHERE i.processo_id = p.id AND i.status IN ('andamento','erro')) AS abertas
     FROM processos p LEFT JOIN processo_versoes vp ON vp.id = p.versao_publicada_id
     ORDER BY p.categoria, p.nome"
);
$iniciadores = [];
foreach (linhas('SELECT processo_id, grupo_id FROM processo_iniciadores') as $r) {
    $iniciadores[$r['processo_id']][] = (int) $r['grupo_id'];
}
$grupos = linhas('SELECT id, nome, cor FROM grupos ORDER BY nome');

cabecalho('Fluxos', 'fluxos');
cabecalho_pagina('Fluxos de processo', 'Desenhe, versione e publique os fluxos que a equipe executa.', '<button class="btn btn-primario" type="button" data-novo-fluxo>' . icone('mais', 16) . ' Novo fluxo</button>');
?>
<div class="card">
  <div class="card-cab"><div><h2>Fluxos cadastrados</h2><p>Alterações ficam em rascunho até você publicar. Processos em andamento continuam na versão em que começaram.</p></div></div>
  <?php if (!$lista): ?>
    <div class="estado-vazio grande"><?= icone('fluxo', 44) ?><h2>Nenhum fluxo ainda</h2><p>Crie o primeiro fluxo e desenhe as etapas no modelador BPMN.</p><button class="btn btn-primario" type="button" data-novo-fluxo><?= icone('mais', 16) ?> Novo fluxo</button></div>
  <?php else: ?>
  <div class="card-corpo" style="padding-top:0">
  <div class="tabela-wrap">
    <table class="tabela tabela-responsiva">
      <thead><tr><th>Processo</th><th>Sigla</th><th>Versão</th><th>Execuções</th><th>Disponível</th><th class="acoes">Ações</th></tr></thead>
      <tbody>
      <?php foreach ($lista as $p):
          $info = ['id' => (int) $p['id'], 'nome' => $p['nome'], 'sigla' => $p['sigla'], 'descricao' => $p['descricao'], 'categoria' => $p['categoria'],
              'cor' => $p['cor'], 'icone' => $p['icone'], 'titulo_modelo' => $p['titulo_modelo'], 'ativo' => (bool) $p['ativo'], 'iniciadores' => $iniciadores[$p['id']] ?? []]; ?>
        <tr data-href="modelador.php?id=<?= (int) $p['id'] ?>">
          <td>
            <div style="display:flex;gap:12px;align-items:center">
              <span class="mini-diagrama" style="background:<?= e($p['cor']) ?>"><?= icone($p['icone'], 20) ?></span>
              <div><strong><?= e($p['nome']) ?></strong><span class="sub"><?= e($p['categoria'] ?: 'Geral') ?></span></div>
            </div>
          </td>
          <td data-rotulo="Sigla"><span class="tag"><?= e($p['sigla']) ?></span></td>
          <td data-rotulo="Versão">
            <?= $p['versao_pub'] ? badge('Publicada v' . $p['versao_pub'], 'ok') : badge('Não publicada', 'neutro') ?>
            <?php if ($p['versao_rasc']): ?> <?= badge('Rascunho v' . $p['versao_rasc'], 'alerta') ?><?php endif; ?>
          </td>
          <td data-rotulo="Execuções"><strong><?= (int) $p['abertas'] ?></strong> abertas <span class="sub"><?= (int) $p['total'] ?> no total</span></td>
          <td>
            <label class="switch-linha" title="Permitir iniciar"><input type="checkbox" data-ativo="<?= (int) $p['id'] ?>"<?= $p['ativo'] ? ' checked' : '' ?>><span class="switch"></span></label>
          </td>
          <td class="acoes">
            <a class="btn btn-sm btn-primario" href="modelador.php?id=<?= (int) $p['id'] ?>"><?= icone('editar', 14) ?> Modelar</a>
            <button class="btn-icone" type="button" title="Propriedades" data-editar-info='<?= e(json_enc($info)) ?>'><?= icone('config', 15) ?></button>
            <button class="btn-icone" type="button" title="Duplicar" data-duplicar="<?= (int) $p['id'] ?>"><?= icone('copiar', 15) ?></button>
            <button class="btn-icone perigo" type="button" title="Excluir" data-excluir="<?= (int) $p['id'] ?>" data-nome="<?= e($p['nome']) ?>"><?= icone('lixo', 15) ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  </div>
  <?php endif; ?>
</div>
<?= dados_js('grupos', $grupos) ?>
<?= dados_js('icones', array_combine(icones_processo(), array_map(fn($i) => icone($i, 20), icones_processo()))) ?>
<?php rodape(['assets/js/fluxo-info.js', 'assets/js/fluxos.js']);
