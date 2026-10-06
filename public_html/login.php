<?php
require __DIR__ . '/app/nucleo.php';

if (usuario_atual()) {
    redirecionar('tarefas.php');
}

$erro = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta por muito tempo. Tente de novo.';
    } else {
        $erro = tentar_login($email, (string) ($_POST['senha'] ?? ''));
        if ($erro === null) {
            $voltar = (string) ($_SESSION['voltar'] ?? '');
            unset($_SESSION['voltar']);
            // só volta para páginas internas
            if ($voltar !== '' && preg_match('#^/[a-z0-9_\-/]*\.php(\?[^\s]*)?$#i', $voltar) && !str_contains($voltar, '//')) {
                header('Location: ' . $voltar);
                exit;
            }
            redirecionar('tarefas.php');
        }
    }
}
$empresa = cfg_banco('empresa', 'Minha Empresa');
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Fluxa BPMS</title>
<link rel="icon" href="assets/img/icone.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800;6..12,900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css?v=<?= versao_assets() ?>">
</head>
<body>
<div class="tela-login">
  <section class="login-arte">
    <div class="marca"><span class="marca-logo"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h7l3 5h6"/><path d="M14 12l-3 5H4"/><circle cx="20" cy="12" r="1.2"/></svg></span><strong>Fluxa</strong><span class="marca-sub">Gestão de Processos · BPMS</span></div>
    <div>
      <h2>Seus processos rodando sozinhos, do pedido à conclusão.</h2>
      <p>Desenhe fluxos em BPMN, distribua tarefas para as pessoas certas e acompanhe cada etapa em tempo real.</p>
    </div>
    <svg class="fluxo-deco" viewBox="0 0 520 220" fill="none">
      <g class="no"><circle cx="40" cy="110" r="22" stroke="#7de0b4" stroke-width="3"/></g>
      <path class="seta-anim" d="M62 110h48" stroke="#7de0b4" stroke-width="2.5"/>
      <g class="no"><rect x="110" y="80" width="110" height="60" rx="12" fill="rgba(255,255,255,.06)" stroke="#fff" stroke-width="2"/><text x="165" y="116" fill="#fff" font-size="14" font-weight="700" text-anchor="middle" font-family="Nunito Sans,sans-serif">Solicitar</text></g>
      <path class="seta-anim" d="M220 110h40" stroke="#7de0b4" stroke-width="2.5"/>
      <g class="no"><rect x="270" y="85" width="50" height="50" rx="4" transform="rotate(45 295 110)" fill="rgba(255,255,255,.06)" stroke="#f5c451" stroke-width="2.5"/><text x="295" y="116" fill="#f5c451" font-size="20" font-weight="900" text-anchor="middle">×</text></g>
      <path class="seta-anim" d="M330 95 L370 50h20" stroke="#7de0b4" stroke-width="2.5"/>
      <path class="seta-anim" d="M330 125 L370 170h20" stroke="#7de0b4" stroke-width="2.5"/>
      <g class="no"><rect x="390" y="22" width="110" height="56" rx="12" fill="rgba(0,174,157,.25)" stroke="#00ae9d" stroke-width="2"/><text x="445" y="55" fill="#fff" font-size="14" font-weight="700" text-anchor="middle" font-family="Nunito Sans,sans-serif">Aprovar</text></g>
      <g class="no"><rect x="390" y="142" width="110" height="56" rx="12" fill="rgba(125,182,28,.2)" stroke="#9ccd3c" stroke-width="2"/><text x="445" y="175" fill="#fff" font-size="14" font-weight="700" text-anchor="middle" font-family="Nunito Sans,sans-serif">Executar</text></g>
    </svg>
    <small style="color:#7fb5a3"><?= e($empresa) ?></small>
  </section>
  <section class="login-form">
    <form class="login-caixa" method="post" autocomplete="on">
      <h1>Bem-vindo de volta</h1>
      <p>Entre com seu e-mail e senha para acessar suas tarefas.</p>
      <?php if ($erro): ?><div class="alerta alerta-erro"><?= icone('alerta', 18) ?><span><?= e($erro) ?></span></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="campo"><label for="email">E-mail</label><input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></div>
      <div class="campo"><label for="senha">Senha</label><input type="password" id="senha" name="senha" required autocomplete="current-password"></div>
      <button class="btn btn-primario btn-lg" type="submit">Entrar <?= icone('seta', 16) ?></button>
    </form>
  </section>
</div>
</body>
</html>
