<?php
require __DIR__ . '/app/nucleo.php';
exigir_admin();

$p = linha('SELECT * FROM processos WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$p) {
    pagina_erro('Fluxo não encontrado', 'Ele pode ter sido excluído.');
}
$v = versao_para_editar((int) $p['id']);
$temPublicada = (bool) valor("SELECT 1 FROM processo_versoes WHERE processo_id = ? AND status <> 'rascunho'", [$p['id']]);
$iniciadores = array_map('intval', array_column(linhas('SELECT grupo_id FROM processo_iniciadores WHERE processo_id = ?', [$p['id']]), 'grupo_id'));

$dados = [
    'processo' => [
        'id' => (int) $p['id'], 'nome' => $p['nome'], 'sigla' => $p['sigla'], 'descricao' => $p['descricao'], 'categoria' => $p['categoria'],
        'cor' => $p['cor'], 'icone' => $p['icone'], 'titulo_modelo' => $p['titulo_modelo'], 'ativo' => (bool) $p['ativo'], 'iniciadores' => $iniciadores,
    ],
    'versao' => ['numero' => (int) $v['numero'], 'status' => $v['status']],
    'temPublicada' => $temPublicada,
    'xml' => $v['xml'],
    'config' => (object) json_decode_arr($v['config']),
    'usuarios' => linhas('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome'),
    'grupos' => linhas('SELECT id, nome, cor FROM grupos ORDER BY nome'),
    'tiposCampo' => TIPOS_CAMPO,
    'acoes' => ACOES,
    'expediente' => ['ini' => cfg_banco('expediente_inicio', '08:00'), 'fim' => cfg_banco('expediente_fim', '18:00')],
];

cabecalho('Modelador · ' . $p['nome'], 'fluxos', [
    'classe' => 'pagina-modelador',
    'css' => ['assets/vendor/bpmn-js/diagram-js.css', 'assets/vendor/bpmn-js/bpmn-js.css', 'assets/vendor/bpmn-js/bpmn-embedded.css', 'assets/css/modelador.css'],
]);
?>
<div class="mod-barra">
  <div class="mod-titulo">
    <a class="btn-icone" href="fluxos.php" title="Voltar para fluxos"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M15 18l-6-6 6-6"/></svg></a>
    <span class="mini-diagrama" style="background:<?= e($p['cor']) ?>;width:38px;height:38px" data-mod-cor><?= icone($p['icone'], 18) ?></span>
    <div>
      <h1 data-mod-nome><?= e($p['nome']) ?></h1>
      <div class="mod-status" data-mod-status></div>
    </div>
  </div>
  <div class="mod-acoes">
    <button class="btn btn-sm" type="button" data-mod="propriedades"><?= icone('config', 15) ?><span class="esconder-mobile">Processo</span></button>
    <div class="mod-menu">
      <button class="btn btn-sm" type="button" data-mod="arquivo"><?= icone('documento', 15) ?><span class="esconder-mobile">Arquivo</span></button>
      <div class="mod-menu-lista" hidden data-mod-menu>
        <button type="button" data-mod="importar">Importar .bpmn</button>
        <button type="button" data-mod="exportar-bpmn">Baixar .bpmn</button>
        <button type="button" data-mod="exportar-svg">Baixar imagem .svg</button>
        <button type="button" data-mod="descartar" data-so-rascunho>Descartar rascunho</button>
      </div>
      <input type="file" accept=".bpmn,.xml" hidden data-mod-arquivo>
    </div>
    <button class="btn btn-sm" type="button" data-mod="validar"><?= icone('check', 15) ?><span class="esconder-mobile">Validar</span></button>
    <button class="btn btn-sm btn-escuro" type="button" data-mod="salvar" title="Ctrl+S"><?= icone('documento', 15) ?> Salvar</button>
    <button class="btn btn-sm btn-primario" type="button" data-mod="publicar"><?= icone('raio', 15) ?> Publicar</button>
  </div>
</div>
<div class="mod-area">
  <div class="mod-canvas" id="mod-canvas">
    <div class="mod-dica" data-mod-dica>Arraste elementos da paleta à esquerda · clique num elemento para configurar · use a <b>chave inglesa</b> para trocar o tipo</div>
    <div class="diagrama-zoom"><button type="button" class="btn-icone" data-zoom="mais" title="Aproximar">+</button><button type="button" class="btn-icone" data-zoom="menos" title="Afastar">−</button><button type="button" class="btn-icone" data-zoom="ajustar" title="Ajustar à tela">⤢</button></div>
  </div>
  <aside class="mod-painel" id="mod-painel"></aside>
</div>
<?= dados_js('dados-modelador', $dados) ?>
<?= dados_js('grupos', $dados['grupos']) ?>
<?= dados_js('icones', array_combine(icones_processo(), array_map(fn($i) => icone($i, 20), icones_processo()))) ?>
<?php rodape([
    'assets/vendor/bpmn-js/bpmn-modeler.production.min.js',
    'assets/js/fluxo-info.js',
    'assets/js/construtor-formulario.js',
    'assets/js/modelador.js',
]);
