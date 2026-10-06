<?php
/*
 * Instalação pelo terminal (use PHP 8: /opt/alt/php83/usr/bin/php na Hostinger):
 *
 *   php database/instalacao.php --email=admin@empresa.com --nome="Seu Nome" --senha="SenhaForte123"
 *
 * Cria as tabelas (se não existirem) e o administrador. Rodar de novo redefine a senha do admin.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/public_html/app/nucleo.php';

$op = getopt('', ['email:', 'nome:', 'senha:']);
$email = mb_strtolower(trim($op['email'] ?? ''));
$nome = trim($op['nome'] ?? 'Administrador');
$senha = (string) ($op['senha'] ?? getenv('BPMS_SENHA') ?: '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || senha_problemas($senha)) {
    fwrite(STDERR, "Uso: php database/instalacao.php --email=voce@empresa.com --nome=\"Seu Nome\" --senha=\"SenhaForte123\"\n");
    fwrite(STDERR, "A senha precisa de 8+ caracteres com letras e números (ou use a variável BPMS_SENHA).\n");
    exit(1);
}

echo "→ Criando tabelas…\n";
$sql = file_get_contents(__DIR__ . '/schema.sql');
foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $cmd) {
    $limpo = trim(preg_replace('/^\s*--.*$/m', '', $cmd));
    if ($limpo !== '') {
        db()->exec($limpo);
    }
}

$hash = password_hash($senha, PASSWORD_DEFAULT);
$id = (int) valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
if ($id) {
    q("UPDATE usuarios SET senha_hash = ?, perfil = 'admin', ativo = 1, sessao_versao = sessao_versao + 1 WHERE id = ?", [$hash, $id]);
    echo "→ Administrador existente atualizado: $email\n";
} else {
    inserir('usuarios', ['nome' => $nome, 'email' => $email, 'senha_hash' => $hash, 'perfil' => 'admin', 'cargo' => 'Administrador']);
    echo "→ Administrador criado: $email\n";
}
echo "✓ Pronto.\n";
