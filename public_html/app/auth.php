<?php
declare(strict_types=1);

function https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function iniciar_sessao(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $seguro = https();
    session_name($seguro ? '__Host-bpms' : 'bpms');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $seguro,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    $limite = (int) config('sessao_minutos', 120) * 60;
    if (isset($_SESSION['uid'], $_SESSION['ultimo']) && time() - $_SESSION['ultimo'] > $limite) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['aviso'] = 'Sua sessão expirou por inatividade. Entre novamente.';
    }
    $_SESSION['ultimo'] = time();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function usuario_atual(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    $u = null;
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $r = linha('SELECT id, nome, email, perfil, cargo, gestor_id, ativo, sessao_versao FROM usuarios WHERE id = ?', [$_SESSION['uid']]);
    if (!$r || !$r['ativo'] || (int) $r['sessao_versao'] !== (int) ($_SESSION['sv'] ?? 0)) {
        $_SESSION = [];
        return null;
    }
    $r['grupos'] = array_map('intval', array_column(
        linhas('SELECT grupo_id FROM usuario_grupos WHERE usuario_id = ?', [$r['id']]),
        'grupo_id'
    ));
    $u = $r;
    return $u;
}

function eh_admin(): bool
{
    $u = usuario_atual();
    return $u !== null && $u['perfil'] === 'admin';
}

/** E-mail do usuário master (dono do sistema). Pode ser trocado no config.php com 'usuario_master'. */
function email_master(): string
{
    return mb_strtolower(trim((string) config('usuario_master', 'lopesbd@gmail.com')));
}

/** O master é o único que exclui administradores e concede ou retira o perfil de administrador. */
function eh_master(?array $u = null): bool
{
    $u = $u ?? usuario_atual();
    return $u !== null && $u['perfil'] === 'admin' && mb_strtolower((string) $u['email']) === email_master();
}

function exigir_login(): array
{
    $u = usuario_atual();
    if (!$u) {
        $_SESSION['voltar'] = $_SERVER['REQUEST_URI'] ?? '';
        redirecionar('login.php');
    }
    return $u;
}

function exigir_admin(): array
{
    $u = exigir_login();
    if ($u['perfil'] !== 'admin') {
        http_response_code(403);
        pagina_erro('Acesso restrito', 'Esta área é exclusiva para administradores.');
    }
    return $u;
}

function tentar_login(string $email, string $senha): ?string
{
    $ip = ip_cliente();
    q('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)');
    $falhas = (int) valor(
        'SELECT COUNT(*) FROM login_tentativas WHERE (ip = ? OR email = ?) AND criado_em > (NOW() - INTERVAL 15 MINUTE)',
        [$ip, $email]
    );
    if ($falhas >= 8) {
        return 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
    }
    $u = linha('SELECT id, senha_hash, ativo, sessao_versao FROM usuarios WHERE email = ?', [$email]);
    // password_verify sempre roda, para não revelar por tempo de resposta se o e-mail existe
    $hash = $u['senha_hash'] ?? '$2y$12$A.WEtYngcMcnEqRwu2E1jeKcV3KlqzKgPJKYC3p26ldNKicrElS0q';
    $ok = password_verify($senha, $hash);
    if (!$u || !$ok || !$u['ativo']) {
        inserir('login_tentativas', ['ip' => $ip, 'email' => mb_substr($email, 0, 160)]);
        return 'E-mail ou senha inválidos.';
    }
    q('DELETE FROM login_tentativas WHERE email = ?', [$email]);
    if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['sv'] = (int) $u['sessao_versao'];
    $_SESSION['ultimo'] = time();
    unset($_SESSION['csrf']);
    q('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?', [$u['id']]);
    return null;
}

function senha_problemas(string $senha): ?string
{
    if (mb_strlen($senha) < 8) {
        return 'A senha precisa ter pelo menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        return 'A senha precisa ter letras e números.';
    }
    return null;
}

/* ---------- APIs JSON ---------- */

function responder($dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_enc($dados);
    exit;
}

function falhar(string $mensagem, int $status = 400): void
{
    responder(['ok' => false, 'erro' => $mensagem], $status);
}

/** Abre uma API: exige login, valida CSRF em escrita e devolve [usuário, entrada]. */
function api_inicio(bool $somenteAdmin = false): array
{
    set_exception_handler(function (Throwable $e) {
        error_log('[bpms] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $msg = $e instanceof ErroNegocio ? $e->getMessage() : (config('debug') ? $e->getMessage() : 'Erro interno. Tente novamente.');
        responder(['ok' => false, 'erro' => $msg], $e instanceof ErroNegocio ? 422 : 500);
    });
    $u = usuario_atual();
    if (!$u) {
        falhar('Sua sessão expirou. Recarregue a página e entre novamente.', 401);
    }
    if ($somenteAdmin && $u['perfil'] !== 'admin') {
        falhar('Acesso restrito a administradores.', 403);
    }
    $entrada = [];
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $token = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null);
        if (!csrf_valido($token)) {
            falhar('Token de segurança inválido. Recarregue a página.', 419);
        }
        // o LiteSpeed da Hostinger nem sempre preenche CONTENT_TYPE: tenta as duas chaves e, sem POST de formulário, lê o JSON
        $tipo = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (stripos($tipo, 'application/json') !== false || !$_POST) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $entrada = is_array($json) ? $json : $_POST;
        } else {
            $entrada = $_POST;
        }
    } else {
        $entrada = $_GET;
    }
    return [$u, $entrada];
}

class ErroNegocio extends RuntimeException
{
}
