/* Lista de fluxos (administração) */
(function () {
  'use strict';
  const { api, toast, confirmar } = window.App;

  document.addEventListener('click', async (e) => {
    if (e.target.closest('[data-novo-fluxo]')) {
      window.FluxoInfo.abrir(null, (r) => { location.href = 'modelador.php?id=' + r.id; });
      return;
    }
    const info = e.target.closest('[data-editar-info]');
    if (info) {
      window.FluxoInfo.abrir(JSON.parse(info.dataset.editarInfo), () => location.reload());
      return;
    }
    const dup = e.target.closest('[data-duplicar]');
    if (dup) {
      try {
        const r = await api('api/fluxos.php', { acao: 'duplicar', id: Number(dup.dataset.duplicar) });
        location.href = 'modelador.php?id=' + r.id;
      } catch (err) { toast(err.message, 'erro'); }
      return;
    }
    const ex = e.target.closest('[data-excluir]');
    if (ex) {
      if (!(await confirmar(`Excluir o fluxo "${ex.dataset.nome}" e todas as suas versões?`, { titulo: 'Excluir fluxo', texto: 'Excluir', perigo: true }))) return;
      try {
        await api('api/fluxos.php', { acao: 'excluir', id: Number(ex.dataset.excluir) });
        ex.closest('tr').remove();
        toast('Fluxo excluído.');
      } catch (err) { toast(err.message, 'erro'); }
    }
  });

  document.querySelectorAll('[data-ativo]').forEach((cb) => cb.addEventListener('change', async () => {
    try {
      await api('api/fluxos.php', { acao: 'ativo', id: Number(cb.dataset.ativo), ativo: cb.checked });
      toast(cb.checked ? 'Processo disponível para iniciar.' : 'Processo desativado: ninguém poderá iniciá-lo.');
    } catch (err) { cb.checked = !cb.checked; toast(err.message, 'erro'); }
  }));
})();
