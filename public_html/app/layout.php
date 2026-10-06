<?php
declare(strict_types=1);

function cabecalho(string $titulo, string $ativo, array $opcoes = []): void
{
    $u = usuario_atual();
    processar_timers_de_carona();
    $css = $opcoes['css'] ?? [];
    $empresa = cfg_banco('empresa', 'Minha Empresa');
    $pendentes = 0;
    $naoLidas = 0;
    if ($u) {
        $pendentes = contar_tarefas_pendentes($u);
        $naoLidas = (int) valor('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0', [$u['id']]);
    }
    $v = versao_assets();
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($titulo) ?> · Fluxa BPMS</title>
<link rel="icon" href="assets/img/icone.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800;6..12,900&display=swap" rel="stylesheet">
<?php foreach ($css as $c): ?><link rel="stylesheet" href="<?= e($c) ?>?v=<?= versao_arquivo($c) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="assets/css/app.css?v=<?= versao_arquivo("assets/css/app.css") ?>">
</head>
<body class="<?= e($opcoes['classe'] ?? '') ?>">
<header class="topo">
  <button class="topo-menu" type="button" data-abrir-menu aria-label="Abrir menu"><?= icone('menu') ?></button>
  <a class="marca" href="tarefas.php">
    <span class="marca-logo"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h7l3 5h6"/><path d="M14 12l-3 5H4"/><circle cx="20" cy="12" r="1.2"/></svg></span>
    <strong>Fluxa</strong><span class="marca-sub">Gestão de Processos · BPMS</span>
  </a>
  <div class="topo-dir">
    <span class="pill pill-topo esconder-mobile"><?= e($empresa) ?></span>
    <?php if ($u): ?>
    <div class="notif" data-notificacoes>
      <button class="pill pill-topo pill-icone" type="button" data-abrir-notificacoes aria-label="Notificações">
        <?= icone('sino', 18) ?><span class="notif-contador<?= $naoLidas ? '' : ' oculto' ?>" data-notif-contador><?= $naoLidas > 99 ? '99+' : $naoLidas ?></span>
      </button>
      <div class="notif-painel" data-notif-painel hidden>
        <div class="notif-topo"><strong>Notificações</strong><button type="button" class="link" data-ler-todas>Marcar todas como lidas</button></div>
        <div class="notif-lista" data-notif-lista><div class="vazio-mini">Carregando…</div></div>
      </div>
    </div>
    <div class="usuario-menu">
      <button class="pill pill-topo pill-usuario" type="button" data-abrir-usuario>
        <?= avatar($u['nome'], 'avatar-sm') ?>
        <span class="esconder-mobile"><strong><?= e(explode(' ', $u['nome'])[0]) ?></strong> <small><?= $u['perfil'] === 'admin' ? 'ADM' : e($u['cargo'] ?: 'Usuário') ?></small></span>
        <svg width="10" height="10" viewBox="0 0 10 10"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
      </button>
      <div class="usuario-painel" data-usuario-painel hidden>
        <div class="usuario-info"><?= avatar($u['nome']) ?><div><strong><?= e($u['nome']) ?></strong><small><?= e($u['email']) ?></small></div></div>
        <a href="perfil.php"><?= icone('usuario', 16) ?> Meu perfil e senha</a>
        <a href="sair.php"><?= icone('sair', 16) ?> Sair</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</header>
<?php if ($u): ?>
<nav class="lateral" data-menu-lateral>
  <?php
    $itens = [
        ['tarefas', 'tarefas.php', 'tarefas', 'Tarefas', $pendentes],
        ['iniciar', 'iniciar.php', 'iniciar', 'Iniciar', 0],
        ['processos', 'processos.php', 'processos', 'Processos', 0],
    ];
    if ($u['perfil'] === 'admin') {
        $itens[] = ['painel', 'painel.php', 'painel', 'Painel', 0];
        $itens[] = ['fluxos', 'fluxos.php', 'fluxo', 'Fluxos', 0];
        $itens[] = ['config', 'configuracoes.php', 'config', 'Config.', 0];
    }
    foreach ($itens as [$chave, $href, $ic, $rotulo, $cont]): ?>
    <a href="<?= $href ?>" class="<?= $ativo === $chave ? 'ativo' : '' ?>">
      <?= icone($ic, 19) ?><span><?= $rotulo ?></span>
      <?php if ($cont): ?><em class="contador"><?= $cont > 99 ? '99+' : $cont ?></em><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<main class="conteudo<?= $u ? '' : ' sem-menu' ?>">
<?php
    if (!empty($_SESSION['flash'])) {
        echo dados_js('flash', $_SESSION['flash']);
        unset($_SESSION['flash']);
    }
}

function rodape(array $js = []): void
{
    $v = versao_assets();
    ?>
</main>
<div class="toasts" data-toasts aria-live="polite"></div>
<script src="assets/js/app.js?v=<?= versao_arquivo("assets/js/app.js") ?>"></script>
<?php foreach ($js as $j): ?><script src="<?= e($j) ?>?v=<?= versao_arquivo($j) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

function versao_arquivo(string $caminho): string
{
    return (string) @filemtime(PUBLIC_DIR . "/" . $caminho);
}

function versao_assets(): string
{
    static $v = null;
    return $v ??= (string) @filemtime(PUBLIC_DIR . '/assets/css/app.css') . (string) @filemtime(PUBLIC_DIR . '/assets/js/app.js');
}

function flash(string $mensagem, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
}

function pagina_erro(string $titulo, string $texto): void
{
    cabecalho($titulo, '');
    echo '<div class="estado-vazio grande">' . icone('alerta', 40) . '<h2>' . e($titulo) . '</h2><p>' . e($texto) . '</p><a class="btn btn-primario" href="tarefas.php">Voltar ao início</a></div>';
    rodape();
    exit;
}

function cabecalho_pagina(string $titulo, string $subtitulo = '', string $acoes = ''): void
{
    echo '<div class="pagina-topo"><div><h1>' . e($titulo) . '</h1>' . ($subtitulo !== '' ? '<p>' . e($subtitulo) . '</p>' : '') . '</div>'
        . ($acoes !== '' ? '<div class="pagina-acoes">' . $acoes . '</div>' : '') . '</div>';
}

function contar_tarefas_pendentes(array $u): int
{
    [$sql, $p] = filtro_tarefas_usuario($u);
    return (int) valor("SELECT COUNT(*) FROM tarefas t WHERE t.status = 'aberta' AND $sql", $p);
}

/** Condição SQL para as tarefas que o usuário pode ver na caixa de entrada. */
function filtro_tarefas_usuario(array $u): array
{
    $grupos = $u['grupos'] ?: [0];
    $marcas = implode(',', array_fill(0, count($grupos), '?'));
    return [
        "(t.usuario_id = ? OR (t.usuario_id IS NULL AND (t.grupo_id IN ($marcas)
            OR EXISTS (SELECT 1 FROM tarefa_candidatos tc WHERE tc.tarefa_id = t.id AND tc.usuario_id = ?))))",
        array_merge([$u['id']], $grupos, [$u['id']]),
    ];
}
