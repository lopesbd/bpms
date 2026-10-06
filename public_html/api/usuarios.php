<?php
require dirname(__DIR__) . '/app/nucleo.php';
[$eu, $in] = api_inicio(true);

/*
 * Regras de administração de contas:
 * - Qualquer administrador cria, edita e exclui usuários comuns.
 * - Só o usuário master exclui administradores e concede ou retira o perfil de administrador.
 * - Ninguém altera ou exclui a conta master, a não ser o próprio master (que não pode se excluir).
 */
$souMaster = eh_master($eu);
$id = (int) ($in['id'] ?? 0);
$alvo = $id ? linha('SELECT id, nome, email, perfil FROM usuarios WHERE id = ?', [$id]) : null;
if ($id && !$alvo) {
    falhar('Usuário não encontrado.', 404);
}
$alvoEhMaster = $alvo && mb_strtolower($alvo['email']) === email_master();
$alvoEhOutroAdmin = $alvo && $alvo['perfil'] === 'admin' && (int) $alvo['id'] !== (int) $eu['id'];

switch ($in['acao'] ?? '') {
    case 'salvar':
        salvar_usuario($eu, $in, $id, $alvo, $souMaster, $alvoEhMaster, $alvoEhOutroAdmin);
        break;
    case 'excluir':
        excluir_usuario($eu, $in, $alvo, $souMaster, $alvoEhMaster);
        break;
    default:
        falhar('Ação desconhecida.');
}

function salvar_usuario(array $eu, array $in, int $id, ?array $alvo, bool $souMaster, bool $alvoEhMaster, bool $alvoEhOutroAdmin): void
{
    $nome = texto_limpo($in['nome'] ?? '', 120);
    $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
    $senha = (string) ($in['senha'] ?? '');
    $perfil = ($in['perfil'] ?? '') === 'admin' ? 'admin' : 'usuario';
    $ativo = !empty($in['ativo']) ? 1 : 0;
    $gestor = isset($in['gestor_id']) && $in['gestor_id'] ? (int) $in['gestor_id'] : null;

    if ($alvoEhMaster && !$souMaster) {
        falhar('Somente o próprio usuário master pode alterar a conta master.', 403);
    }
    if ($alvoEhOutroAdmin && !$souMaster) {
        falhar('Somente o usuário master pode alterar outros administradores.', 403);
    }
    $viraAdmin = $perfil === 'admin' && (!$alvo || $alvo['perfil'] !== 'admin');
    if ($viraAdmin && !$souMaster) {
        falhar('Somente o usuário master pode conceder o perfil de administrador.', 403);
    }
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        falhar('Informe nome e um e-mail válido.');
    }
    if ($email === email_master() && !$alvoEhMaster) {
        falhar('Este e-mail é reservado para o usuário master.');
    }
    if ($alvoEhMaster && $email !== email_master()) {
        falhar('O e-mail da conta master não pode ser alterado por aqui.');
    }
    if (valor('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?', [$email, $id])) {
        falhar('Já existe um usuário com este e-mail.');
    }
    if (($id === 0 || $senha !== '') && ($p = senha_problemas($senha))) {
        falhar($p);
    }
    if ($gestor === $id) {
        $gestor = null;
    }
    if ($id === (int) $eu['id'] && (!$ativo || $perfil !== 'admin')) {
        falhar('Você não pode desativar nem tirar o perfil de administrador da sua própria conta.');
    }

    $novoId = 0;
    transacao(function () use ($id, $nome, $email, $senha, $perfil, $ativo, $gestor, $in, &$novoId) {
        $dados = ['nome' => $nome, 'email' => $email, 'perfil' => $perfil, 'cargo' => texto_limpo($in['cargo'] ?? '', 100) ?: null, 'gestor_id' => $gestor, 'ativo' => $ativo];
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($dados)));
            q("UPDATE usuarios SET $sets WHERE id = ?", array_merge(array_values($dados), [$id]));
            if ($senha !== '') {
                // troca de senha derruba as sessões abertas desse usuário
                q('UPDATE usuarios SET senha_hash = ?, sessao_versao = sessao_versao + 1 WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $id]);
            }
            if (!$ativo) {
                q('UPDATE usuarios SET sessao_versao = sessao_versao + 1 WHERE id = ?', [$id]);
            }
            $novoId = $id;
        } else {
            $novoId = inserir('usuarios', $dados + ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT)]);
        }
        q('DELETE FROM usuario_grupos WHERE usuario_id = ?', [$novoId]);
        foreach (array_unique(array_map('intval', (array) ($in['grupos'] ?? []))) as $g) {
            if ($g > 0 && valor('SELECT 1 FROM grupos WHERE id = ?', [$g])) {
                inserir('usuario_grupos', ['usuario_id' => $novoId, 'grupo_id' => $g]);
            }
        }
    });
    responder(['ok' => true, 'id' => $novoId]);
}

function excluir_usuario(array $eu, array $in, ?array $alvo, bool $souMaster, bool $alvoEhMaster): void
{
    if (!$alvo) {
        falhar('Usuário não encontrado.', 404);
    }
    if ((int) $alvo['id'] === (int) $eu['id']) {
        falhar('Você não pode excluir a sua própria conta.');
    }
    if ($alvoEhMaster) {
        falhar('A conta master não pode ser excluída.', 403);
    }
    if ($alvo['perfil'] === 'admin' && !$souMaster) {
        falhar('Somente o usuário master pode excluir administradores.', 403);
    }

    // tarefas abertas só dele (sem grupo) precisam de um novo responsável
    $individuais = (int) valor("SELECT COUNT(*) FROM tarefas WHERE usuario_id = ? AND status = 'aberta' AND grupo_id IS NULL", [$alvo['id']]);
    // tarefas para pessoas escolhidas em que só ele(a) ainda poderia assumir
    $soEle = array_map('intval', array_column(linhas(
        "SELECT t.id FROM tarefas t JOIN tarefa_candidatos tc ON tc.tarefa_id = t.id
         WHERE t.status = 'aberta' AND t.usuario_id IS NULL AND t.grupo_id IS NULL
         GROUP BY t.id HAVING SUM(tc.usuario_id = ?) = 1 AND COUNT(*) = 1",
        [$alvo['id']]
    ), 'id'));
    $individuais += count($soEle);
    $destino = (int) ($in['transferir_para'] ?? 0);
    if ($destino) {
        if ($destino === (int) $alvo['id'] || !valor('SELECT 1 FROM usuarios WHERE id = ? AND ativo = 1', [$destino])) {
            falhar('Escolha um usuário ativo para receber as tarefas.');
        }
    } elseif ($individuais > 0) {
        falhar("{$alvo['nome']} tem $individuais tarefa(s) aberta(s) só dele. Escolha quem vai recebê-las.");
    }

    $novoNome = $destino ? (string) valor('SELECT nome FROM usuarios WHERE id = ?', [$destino]) : '';
    $movidas = transacao(function () use ($alvo, $destino, $novoNome, $eu, $soEle) {
        foreach ($soEle as $tid) {
            q('INSERT IGNORE INTO tarefa_candidatos (tarefa_id, usuario_id) VALUES (?, ?)', [$tid, $destino]);
        }
        $abertas = linhas("SELECT id, instancia_id, elemento_id, nome, grupo_id FROM tarefas WHERE usuario_id = ? AND status = 'aberta'", [$alvo['id']]);
        foreach ($abertas as $t) {
            if ($destino) {
                q('UPDATE tarefas SET usuario_id = ?, assumida_em = NOW() WHERE id = ?', [$destino, $t['id']]);
                historico((int) $t['instancia_id'], 'reatribuida', $eu['nome'] . ' excluiu ' . $alvo['nome'] . ' e passou a tarefa "' . $t['nome'] . '" para ' . $novoNome . '.', $t['elemento_id'], (int) $eu['id']);
                notificar($destino, 'Tarefa transferida para você: ' . $t['nome'], '', 'tarefa.php?id=' . $t['id']);
            } else {
                // tarefa de grupo: volta para a fila do grupo
                q('UPDATE tarefas SET usuario_id = NULL, assumida_em = NULL WHERE id = ?', [$t['id']]);
                historico((int) $t['instancia_id'], 'tarefa_devolvida', 'A tarefa "' . $t['nome'] . '" voltou para o grupo porque ' . $alvo['nome'] . ' foi excluído(a).', $t['elemento_id'], (int) $eu['id']);
            }
        }
        // o restante (histórico, processos iniciados, comentários) fica preservado sem o vínculo
        q('DELETE FROM usuarios WHERE id = ?', [$alvo['id']]);
        return count($abertas);
    });
    responder(['ok' => true, 'tarefas_movidas' => $movidas]);
}
