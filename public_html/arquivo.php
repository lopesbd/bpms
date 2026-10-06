<?php
require __DIR__ . '/app/nucleo.php';
$u = exigir_login();

$a = linha('SELECT * FROM anexos WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$permitido = false;
if ($a) {
    if ($a['instancia_id'] === null) {
        $permitido = (int) $a['enviado_por'] === (int) $u['id'];
    } else {
        $inst = linha('SELECT * FROM instancias WHERE id = ?', [$a['instancia_id']]);
        $permitido = $inst && pode_ver_instancia($inst, $u);
    }
}
$caminho = $a ? realpath(STORAGE_DIR . '/anexos/' . $a['arquivo']) : false;
if (!$permitido || !$caminho || !str_starts_with($caminho, realpath(STORAGE_DIR . '/anexos'))) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$inline = in_array($a['mime'], ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain'], true);
header('Content-Type: ' . $a['mime']);
header('Content-Length: ' . filesize($caminho));
header('X-Content-Type-Options: nosniff');
if ($a['mime'] !== 'application/pdf') {
    // o visualizador de PDF do Chrome não abre com sandbox
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($a['nome_original']));
header('Cache-Control: private, max-age=0');
readfile($caminho);
