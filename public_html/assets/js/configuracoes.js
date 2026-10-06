/* Configurações: abas, usuários, grupos (arrastar pessoas) e preferências */
(function () {
  'use strict';
  const { api, toast, modal, confirmar, esc, lerJSON } = window.App;

  /* abas (lembra a última pela âncora) */
  const abas = document.querySelectorAll('[data-aba]');
  function abrirAba(nome) {
    abas.forEach((a) => a.classList.toggle('ativa', a.dataset.aba === nome));
    document.querySelectorAll('[data-painel-aba]').forEach((p) => { p.hidden = p.dataset.painelAba !== nome; });
    history.replaceState(null, '', '#' + nome);
  }
  abas.forEach((a) => a.addEventListener('click', () => abrirAba(a.dataset.aba)));
  if (location.hash && document.querySelector(`[data-aba="${location.hash.slice(1)}"]`)) abrirAba(location.hash.slice(1));

  /* ---------- Usuários ---------- */
  function formUsuario(u) {
    const usuarios = (lerJSON('cfg-usuarios') || []).filter((x) => !u || x.id !== u.id);
    const grupos = lerJSON('cfg-grupos') || [];
    const d = Object.assign({ nome: '', email: '', cargo: '', perfil: 'usuario', gestor_id: null, ativo: true, grupos: [] }, u || {});
    const perfilTravado = !(lerJSON('cfg-eu') || {}).master;
    const corpo = document.createElement('div');
    corpo.innerHTML = `<div class="form-grid">
      <div class="campo metade"><label>Nome completo</label><input name="nome" value="${esc(d.nome)}" maxlength="120"></div>
      <div class="campo metade"><label>E-mail (login)</label><input name="email" type="email" value="${esc(d.email)}" maxlength="160"></div>
      <div class="campo metade"><label>Cargo</label><input name="cargo" value="${esc(d.cargo || '')}" maxlength="100"></div>
      <div class="campo metade"><label>Perfil</label><select name="perfil"${perfilTravado ? ' disabled' : ''}><option value="usuario"${d.perfil === 'usuario' ? ' selected' : ''}>Usuário</option><option value="admin"${d.perfil === 'admin' ? ' selected' : ''}>Administrador</option></select>${perfilTravado ? '<small class="ajuda">Só o usuário master concede ou retira o perfil de administrador.</small>' : ''}</div>
      <div class="campo metade"><label>Gestor</label><select name="gestor_id"><option value="">Nenhum</option>${usuarios.map((x) => `<option value="${x.id}"${x.id === d.gestor_id ? ' selected' : ''}>${esc(x.nome)}</option>`).join('')}</select></div>
      <div class="campo metade"><label>${u ? 'Nova senha <span class="obrig">deixe vazio para manter</span>' : 'Senha inicial'}</label><input name="senha" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres, letras e números"></div>
      <div class="campo"><label>Grupos</label><div class="opcoes-radio">${grupos.map((g) => `<label class="check-linha" style="border:1px solid var(--borda);border-radius:999px;padding:5px 12px"><input type="checkbox" name="grupos" value="${g.id}"${d.grupos.includes(g.id) ? ' checked' : ''}> ${esc(g.nome)}</label>`).join('') || '<small class="ajuda">Nenhum grupo cadastrado.</small>'}</div></div>
      ${u ? `<div class="campo"><label class="switch-linha"><input type="checkbox" name="ativo"${d.ativo ? ' checked' : ''}><span class="switch"></span><span>Usuário ativo (pode entrar no sistema)</span></label></div>` : ''}
    </div>`;
    modal({
      titulo: u ? 'Editar usuário' : 'Novo usuário',
      corpo,
      largo: true,
      botoes: [{ texto: 'Cancelar' }, {
        texto: 'Salvar', classe: 'btn-primario',
        acao: async () => {
          const v = (n) => corpo.querySelector(`[name=${n}]`);
          await api('api/usuarios.php', {
            acao: 'salvar', id: u?.id, nome: v('nome').value, email: v('email').value, cargo: v('cargo').value, perfil: v('perfil').value,
            gestor_id: v('gestor_id').value ? Number(v('gestor_id').value) : null, senha: v('senha').value,
            ativo: u ? v('ativo').checked : true,
            grupos: Array.from(corpo.querySelectorAll('[name=grupos]:checked')).map((c) => Number(c.value)),
          });
          location.reload();
        },
      }],
    });
  }
  document.querySelector('[data-usuario-novo]')?.addEventListener('click', () => formUsuario(null));

  /* excluir usuário (administradores só pelo master; as regras são conferidas também no servidor) */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-usuario-excluir]');
    if (!b) return;
    const id = Number(b.dataset.usuarioExcluir);
    const pendentes = Number(b.dataset.pendentes || 0);
    const outros = (lerJSON('cfg-usuarios') || []).filter((x) => x.id !== id);
    const corpo = `
      ${b.dataset.admin === '1' ? '<div class="alerta alerta-alerta">Você está excluindo um <strong>administrador</strong>. Somente o usuário master pode fazer isso.</div>' : ''}
      <p style="margin-top:0">A conta de <strong>${esc(b.dataset.nome)}</strong> será excluída e ele(a) não poderá mais entrar. O histórico dos processos é preservado.</p>
      <div class="campo"><label for="transferir">Transferir as tarefas abertas para ${pendentes ? '<span class="obrig">obrigatório</span>' : ''}</label>
        <select id="transferir"><option value="">${pendentes ? 'Escolha quem vai receber…' : 'Ninguém (tarefas de grupo voltam para o grupo)'}</option>${outros.map((x) => `<option value="${x.id}">${esc(x.nome)}</option>`).join('')}</select>
        <small class="ajuda">${pendentes ? `Há ${pendentes} tarefa(s) aberta(s) só dele(a) que precisam de um novo responsável.` : 'Tarefas de grupo que estavam com ele(a) voltam para a fila do grupo.'}</small></div>`;
    modal({
      titulo: 'Excluir usuário',
      corpo,
      botoes: [{ texto: 'Cancelar' }, {
        texto: 'Excluir usuário', classe: 'btn-perigo',
        acao: async (f, c) => {
          const destino = Number(c.querySelector('#transferir').value) || 0;
          if (pendentes && !destino) throw new Error('Escolha quem vai receber as tarefas abertas.');
          const r = await api('api/usuarios.php', { acao: 'excluir', id, transferir_para: destino || null });
          b.closest('tr').remove();
          toast(`${b.dataset.nome} foi excluído(a).` + (r.tarefas_movidas ? ` ${r.tarefas_movidas} tarefa(s) redistribuída(s).` : ''));
        },
      }],
    });
  });
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-usuario-editar]');
    if (b) formUsuario(JSON.parse(b.dataset.usuarioEditar));
  });

  /* ---------- Grupos ---------- */
  const CORES = ['#00995d', '#00ae9d', '#0b7a75', '#7db61c', '#0369a1', '#49479d', '#a21caf', '#c2410c', '#b45309', '#0c2c34'];
  function formGrupo(g) {
    const d = Object.assign({ nome: '', descricao: '', cor: CORES[Math.floor(Math.random() * CORES.length)] }, g || {});
    modal({
      titulo: g ? 'Editar grupo' : 'Novo grupo',
      corpo: `<div class="campo"><label>Nome</label><input name="nome" value="${esc(d.nome)}" maxlength="80" placeholder="Ex.: Financeiro"></div>
        <div class="campo" style="margin-top:12px"><label>Descrição</label><input name="descricao" value="${esc(d.descricao || '')}" maxlength="255"></div>
        <div class="campo" style="margin-top:12px"><label>Cor</label><input type="color" name="cor" value="${esc(d.cor)}"></div>`,
      botoes: [{ texto: 'Cancelar' }, {
        texto: 'Salvar', classe: 'btn-primario',
        acao: async (f, corpo) => {
          await api('api/grupos.php', { acao: 'salvar', id: g?.id, nome: corpo.querySelector('[name=nome]').value, descricao: corpo.querySelector('[name=descricao]').value, cor: corpo.querySelector('[name=cor]').value });
          location.hash = 'grupos';
          location.reload();
        },
      }],
    });
  }
  document.querySelector('[data-grupo-novo]')?.addEventListener('click', () => formGrupo(null));
  document.addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-grupo-editar]');
    if (ed) { formGrupo(JSON.parse(ed.dataset.grupoEditar)); return; }
    const ex = e.target.closest('[data-grupo-excluir]');
    if (ex) {
      if (!(await confirmar(`Excluir o grupo "${ex.dataset.nome}"? Tarefas abertas desse grupo ficarão sem grupo.`, { titulo: 'Excluir grupo', texto: 'Excluir', perigo: true }))) return;
      try { await api('api/grupos.php', { acao: 'excluir', id: Number(ex.dataset.grupoExcluir) }); ex.closest('.grupo-card').remove(); toast('Grupo excluído.'); } catch (err) { toast(err.message, 'erro'); }
      return;
    }
    const rm = e.target.closest('[data-remover-membro]');
    if (rm) {
      const chip = rm.closest('[data-membro]');
      const card = rm.closest('[data-grupo]');
      try {
        await api('api/grupos.php', { acao: 'remover', id: Number(card.dataset.grupo), usuario_id: Number(chip.dataset.membro) });
        chip.remove();
      } catch (err) { toast(err.message, 'erro'); }
    }
  });

  const filtro = document.querySelector('[data-filtro-pessoas]');
  filtro?.addEventListener('input', () => {
    const t = filtro.value.trim().toLowerCase();
    document.querySelectorAll('[data-pessoa]').forEach((p) => { p.hidden = t && !p.dataset.nome.includes(t); });
  });

  let pessoa = null;
  document.addEventListener('dragstart', (e) => {
    const p = e.target.closest('[data-pessoa]');
    if (!p) return;
    pessoa = p;
    e.dataTransfer.effectAllowed = 'copy';
    e.dataTransfer.setData('text/plain', p.dataset.pessoa);
    p.classList.add('arrastando');
  });
  document.addEventListener('dragend', () => { pessoa?.classList.remove('arrastando'); pessoa = null; document.querySelectorAll('.grupo-card.alvo').forEach((c) => c.classList.remove('alvo')); });
  document.querySelectorAll('[data-grupo]').forEach((card) => {
    card.addEventListener('dragover', (e) => { if (!pessoa) return; e.preventDefault(); card.classList.add('alvo'); });
    card.addEventListener('dragleave', (e) => { if (!card.contains(e.relatedTarget)) card.classList.remove('alvo'); });
    card.addEventListener('drop', async (e) => {
      e.preventDefault();
      card.classList.remove('alvo');
      if (!pessoa) return;
      const uid = Number(pessoa.dataset.pessoa);
      if (card.querySelector(`[data-membro="${uid}"]`)) { toast('Essa pessoa já está no grupo.'); return; }
      const nome = pessoa.querySelector('span:not(.avatar)').firstChild.textContent.trim();
      const av = pessoa.querySelector('.avatar').outerHTML.replace('avatar-sm', 'avatar-xs');
      try {
        await api('api/grupos.php', { acao: 'adicionar', id: Number(card.dataset.grupo), usuario_id: uid });
        card.querySelector('.soltar-aqui').insertAdjacentHTML('beforebegin', `<span class="membro" data-membro="${uid}">${av}${esc(nome)}<button type="button" title="Remover do grupo" data-remover-membro>✕</button></span>`);
        toast(nome + ' entrou no grupo.');
      } catch (err) { toast(err.message, 'erro'); }
    });
  });

  /* ---------- Geral ---------- */
  const fg = document.querySelector('[data-form-geral]');
  fg?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const dados = Object.fromEntries(new FormData(fg).entries());
    dados.email_tarefas = fg.querySelector('[name=email_tarefas]').checked ? '1' : '0';
    try { await api('api/configuracoes.php', { acao: 'salvar', ...dados }); toast('Preferências salvas.'); } catch (err) { toast(err.message, 'erro'); }
  });
  document.querySelector('[data-testar-email]')?.addEventListener('click', async (e) => {
    const b = e.currentTarget;
    window.App.carregando(b, true);
    try { const r = await api('api/configuracoes.php', { acao: 'testar_email' }); toast(r.mensagem); } catch (err) { toast(err.message, 'erro'); }
    window.App.carregando(b, false);
  });
})();
