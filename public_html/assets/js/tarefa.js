/* Execução de uma tarefa: assumir, devolver, reatribuir e concluir */
(function () {
  'use strict';
  const { api, toast, modal, confirmar, carregando, coletarFormulario, prepararUploads, lerJSON, esc } = window.App;
  const card = document.querySelector('[data-tarefa-form]');
  if (!card) return;
  const id = Number(card.dataset.tarefaForm);
  prepararUploads(card);

  card.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-acao],[data-concluir]');
    if (!b) return;

    if (b.dataset.acao === 'assumir' || b.dataset.acao === 'devolver') {
      carregando(b, true);
      try {
        await api('api/tarefas.php', { acao: b.dataset.acao, id });
        location.reload();
      } catch (err) { toast(err.message, 'erro'); carregando(b, false); }
      return;
    }

    if (b.dataset.acao === 'reatribuir') {
      const usuarios = lerJSON('usuarios-ativos') || [];
      modal({
        titulo: 'Reatribuir tarefa',
        corpo: `<div class="campo"><label for="novo-resp">Novo responsável</label><select id="novo-resp">${usuarios.map((u) => `<option value="${u.id}">${esc(u.nome)}</option>`).join('')}</select></div>`,
        botoes: [{ texto: 'Cancelar' }, {
          texto: 'Reatribuir', classe: 'btn-primario',
          acao: async (f, corpo) => { await api('api/tarefas.php', { acao: 'reatribuir', id, usuario_id: Number(corpo.querySelector('select').value) }); location.reload(); },
        }],
      });
      return;
    }

    if (b.hasAttribute('data-concluir')) {
      const { dados, faltando } = coletarFormulario(card);
      if (faltando.length) { toast('Preencha os campos obrigatórios destacados.', 'erro'); return; }
      const decisao = b.dataset.decisao || null;
      if (decisao && !(await confirmar(`Confirmar a decisão "${decisao}"?`, { titulo: 'Concluir tarefa', texto: decisao }))) return;
      card.querySelectorAll('[data-concluir]').forEach((x) => { x.disabled = true; });
      carregando(b, true);
      try {
        const r = await api('api/tarefas.php', { acao: 'concluir', id, dados, decisao });
        toast(r.mensagem || 'Tarefa concluída!');
        setTimeout(() => { location.href = r.proxima ? 'tarefa.php?id=' + r.proxima : 'tarefas.php'; }, 700);
      } catch (err) {
        toast(err.message, 'erro');
        carregando(b, false);
        card.querySelectorAll('[data-concluir]').forEach((x) => { x.disabled = false; });
      }
    }
  });
})();
