<?php
require dirname(__DIR__) . '/app/nucleo.php';
[, $in] = api_inicio(true);

$id = (int) ($in['id'] ?? 0);
switch ($in['acao'] ?? '') {
    case 'salvar':
        $nome = texto_limpo($in['nome'] ?? '', 80);
        if ($nome === '') {
            falhar('Informe o nome do grupo.');
        }
        if (valor('SELECT 1 FROM grupos WHERE nome = ? AND id <> ?', [$nome, $id])) {
            falhar('Já existe um grupo com este nome.');
        }
        $cor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($in['cor'] ?? '')) ? $in['cor'] : '#00995d';
        $desc = texto_limpo($in['descricao'] ?? '', 255) ?: null;
        if ($id) {
            q('UPDATE grupos SET nome = ?, descricao = ?, cor = ? WHERE id = ?', [$nome, $desc, $cor, $id]);
        } else {
            $id = inserir('grupos', ['nome' => $nome, 'descricao' => $desc, 'cor' => $cor]);
        }
        responder(['ok' => true, 'id' => $id]);
        // no break
    case 'excluir':
        q('DELETE FROM grupos WHERE id = ?', [$id]);
        responder(['ok' => true]);
        // no break
    case 'adicionar':
        $uid = (int) ($in['usuario_id'] ?? 0);
        if (!valor('SELECT 1 FROM grupos WHERE id = ?', [$id]) || !valor('SELECT 1 FROM usuarios WHERE id = ?', [$uid])) {
            falhar('Grupo ou usuário não encontrado.', 404);
        }
        q('INSERT IGNORE INTO usuario_grupos (usuario_id, grupo_id) VALUES (?, ?)', [$uid, $id]);
        responder(['ok' => true]);
        // no break
    case 'remover':
        q('DELETE FROM usuario_grupos WHERE usuario_id = ? AND grupo_id = ?', [(int) ($in['usuario_id'] ?? 0), $id]);
        responder(['ok' => true]);
        // no break
    default:
        falhar('Ação desconhecida.');
}
