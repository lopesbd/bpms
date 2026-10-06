<?php
require dirname(__DIR__) . '/app/nucleo.php';
[$u, $in] = api_inicio();

$id = (int) ($in['id'] ?? 0);
switch ($in['acao'] ?? '') {
    case 'assumir':
        motor_assumir($id, $u);
        responder(['ok' => true]);
        // no break
    case 'devolver':
        motor_devolver($id, $u);
        responder(['ok' => true]);
        // no break
    case 'reatribuir':
        if ($u['perfil'] !== 'admin') {
            falhar('Só administradores podem reatribuir tarefas.', 403);
        }
        motor_reatribuir($id, (int) ($in['usuario_id'] ?? 0), $u);
        responder(['ok' => true]);
        // no break
    case 'concluir':
        $instId = (int) valor('SELECT instancia_id FROM tarefas WHERE id = ?', [$id]);
        $dados = is_array($in['dados'] ?? null) ? $in['dados'] : [];
        $decisao = isset($in['decisao']) && $in['decisao'] !== null ? (string) $in['decisao'] : null;
        motor_concluir_tarefa($id, $u, $dados, $decisao);
        // se a próxima etapa caiu com a mesma pessoa, já leva para ela
        $proxima = valor("SELECT id FROM tarefas WHERE instancia_id = ? AND status = 'aberta' AND usuario_id = ? AND id > ? ORDER BY id LIMIT 1", [$instId, $u['id'], $id]);
        $status = (string) valor('SELECT status FROM instancias WHERE id = ?', [$instId]);
        $msg = $status === 'concluida' ? 'Tarefa concluída. O processo foi finalizado!' : ($status === 'erro' ? 'Tarefa concluída, mas uma automação seguinte falhou. Os administradores foram avisados.' : 'Tarefa concluída. O processo seguiu para a próxima etapa.');
        responder(['ok' => true, 'proxima' => $proxima ? (int) $proxima : null, 'mensagem' => $msg]);
        // no break
    default:
        falhar('Ação desconhecida.');
}
