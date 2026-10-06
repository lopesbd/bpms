<?php
require dirname(__DIR__) . '/app/nucleo.php';
[$u, $in] = api_inicio();

$id = (int) ($in['id'] ?? 0);
switch ($in['acao'] ?? '') {
    case 'iniciar':
        $dados = is_array($in['dados'] ?? null) ? $in['dados'] : [];
        $instId = motor_iniciar((int) ($in['processo_id'] ?? 0), $u, $dados);
        $inst = linha('SELECT codigo, status FROM instancias WHERE id = ?', [$instId]);
        $proxima = valor("SELECT id FROM tarefas WHERE instancia_id = ? AND status = 'aberta' AND usuario_id = ? ORDER BY id LIMIT 1", [$instId, $u['id']]);
        responder(['ok' => true, 'id' => $instId, 'codigo' => $inst['codigo'], 'proxima' => $proxima ? (int) $proxima : null]);
        // no break
    case 'comentar':
        $inst = linha('SELECT * FROM instancias WHERE id = ?', [$id]);
        if (!$inst || !pode_ver_instancia($inst, $u)) {
            falhar('Processo não encontrado.', 404);
        }
        $texto = texto_limpo($in['texto'] ?? '', 2000);
        if ($texto === '') {
            falhar('Escreva o comentário.');
        }
        inserir('comentarios', ['instancia_id' => $id, 'usuario_id' => $u['id'], 'texto' => $texto]);
        // avisa quem participa (iniciador e responsáveis por tarefas abertas), menos o autor
        $avisar = array_map('intval', array_column(linhas(
            "SELECT DISTINCT usuario_id FROM tarefas WHERE instancia_id = ? AND usuario_id IS NOT NULL UNION SELECT iniciado_por FROM instancias WHERE id = ?",
            [$id, $id]
        ), 'usuario_id'));
        foreach (array_unique(array_filter($avisar)) as $d) {
            if ($d !== (int) $u['id']) {
                notificar($d, $u['nome'] . ' comentou em ' . $inst['codigo'], mb_substr($texto, 0, 140), 'processo.php?id=' . $id);
            }
        }
        responder(['ok' => true, 'nome' => $u['nome'], 'avatar' => avatar($u['nome'], 'avatar-sm')]);
        // no break
    case 'cancelar':
        motor_cancelar($id, $u, texto_limpo($in['motivo'] ?? '', 400));
        responder(['ok' => true]);
        // no break
    case 'repetir':
        if ($u['perfil'] !== 'admin') {
            falhar('Só administradores podem reexecutar etapas.', 403);
        }
        motor_repetir($id, $u);
        responder(['ok' => true]);
        // no break
    default:
        falhar('Ação desconhecida.');
}
