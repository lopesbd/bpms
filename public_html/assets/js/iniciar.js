/* Catálogo de processos e formulário inicial */
(function () {
  'use strict';
  const { api, toast, carregando, coletarFormulario, prepararUploads } = window.App;

  const busca = document.querySelector('[data-busca-proc]');
  if (busca) {
    busca.addEventListener('input', () => {
      const t = busca.value.trim().toLowerCase();
      document.querySelectorAll('[data-grupo-cat]').forEach((g) => {
        let visiveis = 0;
        g.querySelectorAll('.cartao-proc').forEach((c) => {
          const ok = !t || c.dataset.busca.includes(t);
          c.hidden = !ok;
          if (ok) visiveis++;
        });
        g.hidden = !visiveis;
      });
    });
  }

  const card = document.querySelector('[data-iniciar]');
  if (!card) return;
  prepararUploads(card);
  card.querySelector('[data-iniciar-btn]').addEventListener('click', async (e) => {
    const b = e.currentTarget;
    const { dados, faltando } = coletarFormulario(card);
    if (faltando.length) { toast('Preencha os campos obrigatórios destacados.', 'erro'); return; }
    carregando(b, true);
    try {
      const r = await api('api/instancias.php', { acao: 'iniciar', processo_id: Number(card.dataset.iniciar), dados });
      toast('Processo ' + r.codigo + ' iniciado!');
      setTimeout(() => { location.href = r.proxima ? 'tarefa.php?id=' + r.proxima : 'processo.php?id=' + r.id; }, 700);
    } catch (err) {
      toast(err.message, 'erro');
      carregando(b, false);
    }
  });
})();
