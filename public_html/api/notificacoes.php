<?php
require dirname(__DIR__) . '/app/nucleo.php';
[$u, $in] = api_inicio();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($in['acao'] ?? '') === 'ler_todas') {
        q('UPDATE notificacoes SET lida = 1 WHERE usuario_id = ?', [$u['id']]);
    } elseif (($in['acao'] ?? '') === 'ler') {
        q('UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?', [(int) ($in['id'] ?? 0), $u['id']]);
    }
    responder(['ok' => true]);
}

$naoLidas = (int) valor('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0', [$u['id']]);
if (!empty($in['contar'])) {
    responder(['ok' => true, 'nao_lidas' => $naoLidas]);
}
$itens = linhas('SELECT id, titulo, texto, link, lida, criado_em FROM notificacoes WHERE usuario_id = ? ORDER BY id DESC LIMIT 30', [$u['id']]);
foreach ($itens as &$n) {
    $n['quando'] = tempo_relativo($n['criado_em']);
    $n['lida'] = (bool) $n['lida'];
    // só links internos
    if (!preg_match('#^[a-z0-9_\-]+\.php(\?[\w=&%\-]*)?$#i', (string) $n['link'])) {
        $n['link'] = '#';
    }
}
responder(['ok' => true, 'nao_lidas' => $naoLidas, 'itens' => $itens]);
