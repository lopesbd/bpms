<?php
require __DIR__ . '/app/nucleo.php';
$u = exigir_login();

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atual = (string) ($_POST['atual'] ?? '');
    $nova = (string) ($_POST['nova'] ?? '');
    $hash = (string) valor('SELECT senha_hash FROM usuarios WHERE id = ?', [$u['id']]);
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $erro = 'A página expirou. Tente de novo.';
    } elseif (!password_verify($atual, $hash)) {
        $erro = 'A senha atual não confere.';
    } elseif ($nova !== (string) ($_POST['confirma'] ?? '')) {
        $erro = 'A confirmação não é igual à nova senha.';
    } elseif ($p = senha_problemas($nova)) {
        $erro = $p;
    } else {
        q('UPDATE usuarios SET senha_hash = ?, sessao_versao = sessao_versao + 1 WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
        $_SESSION['sv'] = (int) valor('SELECT sessao_versao FROM usuarios WHERE id = ?', [$u['id']]);
        session_regenerate_id(true);
        flash('Senha alterada. Outras sessões abertas foram encerradas.');
        redirecionar('perfil.php');
    }
}
$grupos = linhas('SELECT g.nome, g.cor FROM grupos g JOIN usuario_grupos ug ON ug.grupo_id = g.id WHERE ug.usuario_id = ? ORDER BY g.nome', [$u['id']]);
$gestor = $u['gestor_id'] ? valor('SELECT nome FROM usuarios WHERE id = ?', [$u['gestor_id']]) : null;

cabecalho('Meu perfil', '');
cabecalho_pagina('Meu perfil', 'Seus dados e sua senha de acesso.');
?>
<div class="duas-colunas">
  <div class="card"><div class="card-cab"><h2>Dados</h2></div><div class="card-corpo">
    <div style="display:flex;gap:14px;align-items:center;margin-bottom:16px"><?= avatar($u['nome'], 'avatar-lg') ?><div><strong style="font-size:18px"><?= e($u['nome']) ?></strong><br><span style="color:var(--texto-2)"><?= e($u['email']) ?></span></div></div>
    <dl class="dados-lista">
      <div><dt>Perfil</dt><dd><?= $u['perfil'] === 'admin' ? 'Administrador' : 'Usuário' ?></dd></div>
      <div><dt>Cargo</dt><dd><?= e($u['cargo'] ?: '—') ?></dd></div>
      <div><dt>Gestor</dt><dd><?= e($gestor ?: '—') ?></dd></div>
      <div><dt>Grupos</dt><dd><?php foreach ($grupos as $g): ?><span class="tag" style="border-left:3px solid <?= e($g['cor']) ?>;margin:1px"><?= e($g['nome']) ?></span> <?php endforeach; ?><?= $grupos ? '' : '—' ?></dd></div>
    </dl>
    <p class="ajuda" style="margin-top:12px">Para alterar nome, cargo ou grupos, fale com um administrador.</p>
  </div></div>
  <form class="card" method="post"><div class="card-cab"><h2>Trocar senha</h2></div><div class="card-corpo">
    <?php if ($erro): ?><div class="alerta alerta-erro"><?= icone('alerta', 18) ?><span><?= e($erro) ?></span></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-grid">
      <div class="campo"><label for="atual">Senha atual</label><input type="password" id="atual" name="atual" required autocomplete="current-password"></div>
      <div class="campo"><label for="nova">Nova senha</label><input type="password" id="nova" name="nova" required autocomplete="new-password"><small class="ajuda">Mínimo 8 caracteres, com letras e números.</small></div>
      <div class="campo"><label for="confirma">Confirmar nova senha</label><input type="password" id="confirma" name="confirma" required autocomplete="new-password"></div>
    </div>
  </div><div class="card-rodape"><button class="btn btn-primario" type="submit">Salvar nova senha</button></div></form>
</div>
<?php rodape();
