/* Detalhe do processo: comentários, cancelar e reexecutar */
(function () {
  'use strict';
  const { api, toast, esc, modal, confirmar } = window.App;

  document.querySelectorAll('[data-form-comentario]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const ta = form.querySelector('textarea');
      const texto = ta.value.trim();
      if (!texto) return;
      const btn = form.querySelector('button');
      btn.disabled = true;
      try {
        const r = await api('api/instancias.php', { acao: 'comentar', id: Number(form.dataset.formComentario), texto });
        const lista = document.querySelector('[data-comentarios]');
        lista.querySelector('[data-sem-comentarios]')?.remove();
        lista.insertAdjacentHTML('beforeend', `<div class="comentario">${r.avatar}<div class="bolha"><small>${esc(r.nome)} · agora</small>${esc(texto)}</div></div>`);
        ta.value = '';
      } catch (err) { toast(err.message, 'erro'); }
      btn.disabled = false;
    });
    form.querySelector('textarea').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) form.requestSubmit();
    });
  });

  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-instancia-acao]');
    if (!b) return;
    const id = Number(b.dataset.id);
    if (b.dataset.instanciaAcao === 'cancelar') {
      modal({
        titulo: 'Cancelar processo',
        corpo: '<p style="margin-top:0">As tarefas abertas serão encerradas e o processo não poderá ser retomado.</p><div class="campo"><label for="motivo">Motivo</label><textarea id="motivo" maxlength="400" placeholder="Opcional"></textarea></div>',
        botoes: [{ texto: 'Voltar' }, {
          texto: 'Cancelar processo', classe: 'btn-perigo',
          acao: async (f, corpo) => {
            await api('api/instancias.php', { acao: 'cancelar', id, motivo: corpo.querySelector('textarea').value });
            location.reload();
          },
        }],
      });
    }
    if (b.dataset.instanciaAcao === 'repetir') {
      if (!(await confirmar('Reexecutar as etapas que deram erro? Faça isso depois de corrigir a causa (ex.: configuração do e-mail ou da API).', { titulo: 'Reexecutar etapas' }))) return;
      try {
        await api('api/instancias.php', { acao: 'repetir', id });
        location.reload();
      } catch (err) { toast(err.message, 'erro'); }
    }
  });
})();
