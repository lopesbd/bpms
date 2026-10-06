<?php
require __DIR__ . '/app/nucleo.php';
$eu = exigir_admin();

$usuarios = linhas('SELECT u.*, g.nome AS gestor_nome FROM usuarios u LEFT JOIN usuarios g ON g.id = u.gestor_id ORDER BY u.ativo DESC, u.nome');
$grupos = linhas('SELECT * FROM grupos ORDER BY nome');
$membros = [];
foreach (linhas('SELECT usuario_id, grupo_id FROM usuario_grupos') as $r) {
    $membros[(int) $r['grupo_id']][] = (int) $r['usuario_id'];
}
$gruposDoUsuario = [];
foreach ($membros as $g => $us) {
    foreach ($us as $uid) {
        $gruposDoUsuario[$uid][] = $g;
    }
}
$souMaster = eh_master($eu);
// tarefas abertas que ficariam sem dono se o usuário fosse excluído
$pendentes = [];
foreach (linhas("SELECT usuario_id, COUNT(*) n FROM tarefas WHERE status = 'aberta' AND grupo_id IS NULL AND usuario_id IS NOT NULL GROUP BY usuario_id") as $r) {
    $pendentes[(int) $r['usuario_id']] = (int) $r['n'];
}
$nomeGrupo = array_column($grupos, 'nome', 'id');
$corGrupo = array_column($grupos, 'cor', 'id');

cabecalho('Configurações', 'config');
cabecalho_pagina('Configurações', 'Usuários, grupos de trabalho e preferências de ' . cfg_banco('empresa') . '.');
?>
<div class="card">
  <nav class="abas" data-abas>
    <button class="aba ativa" type="button" data-aba="usuarios">Usuários <span class="num"><?= count($usuarios) ?></span></button>
    <button class="aba" type="button" data-aba="grupos">Grupos <span class="num"><?= count($grupos) ?></span></button>
    <button class="aba" type="button" data-aba="geral">Geral</button>
  </nav>

  <section data-painel-aba="usuarios">
    <div class="card-cab" style="border-bottom:0">
      <div><h2>Usuários do sistema</h2><p>Administradores criam fluxos e veem todos os processos. Só o usuário master concede ou retira o perfil de administrador e exclui administradores.</p></div>
      <button class="btn btn-primario" type="button" data-usuario-novo><?= icone('mais', 16) ?> Novo usuário</button>
    </div>
    <div class="card-corpo" style="padding-top:0">
      <div class="tabela-wrap"><table class="tabela tabela-responsiva">
        <thead><tr><th>Nome</th><th>Cargo</th><th>Perfil</th><th>Gestor</th><th>Grupos</th><th>Último acesso</th><th class="acoes">Ações</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $us):
            $ehMaster = mb_strtolower($us['email']) === email_master();
            $ehEu = (int) $us['id'] === (int) $eu['id'];
            $podeEditar = $ehEu || $souMaster || $us['perfil'] !== 'admin';
            $podeExcluir = !$ehEu && !$ehMaster && ($souMaster || $us['perfil'] !== 'admin');
            $dados = ['id' => (int) $us['id'], 'nome' => $us['nome'], 'email' => $us['email'], 'cargo' => $us['cargo'], 'perfil' => $us['perfil'],
                'gestor_id' => $us['gestor_id'] ? (int) $us['gestor_id'] : null, 'ativo' => (bool) $us['ativo'], 'grupos' => $gruposDoUsuario[$us['id']] ?? []]; ?>
          <tr<?= $us['ativo'] ? '' : ' style="opacity:.55"' ?>>
            <td><div style="display:flex;gap:10px;align-items:center"><?= avatar($us['nome']) ?><div><strong><?= e($us['nome']) ?></strong><span class="sub"><?= e($us['email']) ?></span></div></div></td>
            <td data-rotulo="Cargo"><?= e($us['cargo'] ?: '—') ?></td>
            <td><?= $ehMaster ? badge('Master', 'alerta') . ' ' : '' ?><?= $us['perfil'] === 'admin' ? badge('Administrador', 'escuro') : badge('Usuário', 'neutro') ?><?= $us['ativo'] ? '' : ' ' . badge('Inativo', 'erro') ?></td>
            <td data-rotulo="Gestor"><?= e($us['gestor_nome'] ?? '—') ?></td>
            <td data-rotulo="Grupos"><?php foreach ($gruposDoUsuario[$us['id']] ?? [] as $g): ?><span class="tag" style="margin:1px;border-left:3px solid <?= e($corGrupo[$g]) ?>"><?= e($nomeGrupo[$g]) ?></span> <?php endforeach; ?></td>
            <td data-rotulo="Acesso"><?= e(tempo_relativo($us['ultimo_acesso'])) ?></td>
            <td class="acoes">
              <?php if ($podeEditar): ?><button class="btn-icone" type="button" title="Editar" data-usuario-editar='<?= e(json_enc($dados)) ?>'><?= icone('editar', 15) ?></button><?php else: ?><span class="btn-icone" style="opacity:.35;cursor:not-allowed" title="Só o usuário master altera outros administradores"><?= icone('escudo', 15) ?></span><?php endif; ?>
              <?php if ($podeExcluir): ?><button class="btn-icone perigo" type="button" title="Excluir" data-usuario-excluir="<?= (int) $us['id'] ?>" data-nome="<?= e($us['nome']) ?>" data-admin="<?= $us['perfil'] === 'admin' ? 1 : 0 ?>" data-pendentes="<?= $pendentes[(int) $us['id']] ?? 0 ?>"><?= icone('lixo', 15) ?></button><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </section>

  <section data-painel-aba="grupos" hidden>
    <div class="card-cab" style="border-bottom:0">
      <div><h2>Grupos de trabalho</h2><p>Arraste as pessoas da lista para dentro de um grupo. Tarefas de grupo aparecem para todos os membros até alguém assumir.</p></div>
      <button class="btn btn-primario" type="button" data-grupo-novo><?= icone('mais', 16) ?> Novo grupo</button>
    </div>
    <div class="card-corpo" style="padding-top:0">
      <div class="grupos-layout">
        <div class="pessoas-lista">
          <h4>Pessoas</h4>
          <input type="search" placeholder="Filtrar…" data-filtro-pessoas aria-label="Filtrar pessoas">
          <div data-pessoas>
          <?php foreach ($usuarios as $us): if (!$us['ativo']) continue; ?>
            <div class="pessoa" draggable="true" data-pessoa="<?= (int) $us['id'] ?>" data-nome="<?= e(mb_strtolower($us['nome'])) ?>"><?= avatar($us['nome'], 'avatar-sm') ?><span><?= e($us['nome']) ?><small><?= e($us['cargo'] ?: $us['email']) ?></small></span></div>
          <?php endforeach; ?>
          </div>
        </div>
        <div class="grade-grupos" data-grade-grupos>
        <?php if (!$grupos): ?><div class="estado-vazio" style="grid-column:1/-1"><?= icone('grupo', 40) ?><h3>Nenhum grupo</h3><p>Crie grupos como "Financeiro", "Compras" ou "Diretoria".</p></div><?php endif; ?>
        <?php foreach ($grupos as $g): ?>
          <div class="grupo-card" data-grupo="<?= (int) $g['id'] ?>" style="--cor:<?= e($g['cor']) ?>">
            <div class="grupo-topo"><div><strong><?= e($g['nome']) ?></strong><small><?= e($g['descricao'] ?: 'Sem descrição') ?></small></div>
              <div><button class="btn-icone" type="button" title="Editar" data-grupo-editar='<?= e(json_enc(['id' => (int) $g['id'], 'nome' => $g['nome'], 'descricao' => $g['descricao'], 'cor' => $g['cor']])) ?>'><?= icone('editar', 14) ?></button>
              <button class="btn-icone perigo" type="button" title="Excluir" data-grupo-excluir="<?= (int) $g['id'] ?>" data-nome="<?= e($g['nome']) ?>"><?= icone('lixo', 14) ?></button></div></div>
            <div class="grupo-membros" data-membros>
              <?php foreach ($membros[$g['id']] ?? [] as $uid):
                  $un = null;
                  foreach ($usuarios as $x) { if ((int) $x['id'] === $uid) { $un = $x; } }
                  if (!$un) continue; ?>
                <span class="membro" data-membro="<?= $uid ?>"><?= avatar($un['nome'], 'avatar-xs') ?><?= e($un['nome']) ?><button type="button" title="Remover do grupo" data-remover-membro>✕</button></span>
              <?php endforeach; ?>
              <span class="soltar-aqui">Solte pessoas aqui</span>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section data-painel-aba="geral" hidden>
    <form class="card-corpo" data-form-geral style="max-width:760px">
      <div class="form-grid">
        <div class="campo metade"><label for="g-empresa">Nome da empresa</label><input id="g-empresa" name="empresa" value="<?= e(cfg_banco('empresa')) ?>" maxlength="100"></div>
        <div class="campo metade"><label for="g-remetente">E-mail remetente</label><input id="g-remetente" type="email" name="email_remetente" value="<?= e(cfg_banco('email_remetente')) ?>" placeholder="nao-responda@seudominio.com.br"><small class="ajuda">Na Hostinger, use uma conta de e-mail criada no mesmo domínio.</small></div>
        <div class="campo"><label class="switch-linha"><input type="checkbox" name="email_tarefas"<?= cfg_banco('email_tarefas') === '1' ? ' checked' : '' ?>><span class="switch"></span><span>Avisar por e-mail quando alguém receber uma tarefa</span></label></div>
        <div class="campo metade"><label for="g-exp-ini">Início do expediente</label><input id="g-exp-ini" type="time" name="expediente_inicio" value="<?= e(cfg_banco('expediente_inicio', '08:00')) ?>"><small class="ajuda">Usado nos prazos em horas úteis (segunda a sexta).</small></div>
        <div class="campo metade"><label for="g-exp-fim">Fim do expediente</label><input id="g-exp-fim" type="time" name="expediente_fim" value="<?= e(cfg_banco('expediente_fim', '18:00')) ?>"></div>
        <div class="campo"><label for="g-feriados">Feriados</label><textarea id="g-feriados" name="feriados" rows="3" placeholder="25/12  01/01  21/04  (dd/mm vale todo ano; dd/mm/aaaa só naquele ano)"><?= e(cfg_banco('feriados')) ?></textarea><small class="ajuda">Datas separadas por espaço, vírgula ou linha. Não contam como horário útil.</small></div>
        <div class="campo metade"><label for="g-formatos">Formatos de anexo aceitos</label><input id="g-formatos" name="formatos_anexo" value="<?= e(cfg_banco('formatos_anexo')) ?>"><small class="ajuda">Extensões separadas por vírgula.</small></div>
        <div class="campo metade"><label for="g-tamanho">Tamanho máximo (MB)</label><input id="g-tamanho" type="number" min="1" max="100" name="tamanho_max_mb" value="<?= e(cfg_banco('tamanho_max_mb', '10')) ?>"></div>
      </div>
      <div style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-primario" type="submit">Salvar preferências</button><button class="btn" type="button" data-testar-email>Enviar e-mail de teste para mim</button></div>
    </form>
  </section>
</div>
<?= dados_js('cfg-eu', ['id' => (int) $eu['id'], 'master' => $souMaster]) ?>
<?= dados_js('cfg-usuarios', array_map(fn($x) => ['id' => (int) $x['id'], 'nome' => $x['nome']], array_filter($usuarios, fn($x) => $x['ativo']))) ?>
<?= dados_js('cfg-grupos', array_map(fn($g) => ['id' => (int) $g['id'], 'nome' => $g['nome']], $grupos)) ?>
<?php rodape(['assets/js/configuracoes.js']);
