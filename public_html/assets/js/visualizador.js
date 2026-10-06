/* Visualizador BPMN (bpmn-js) com o andamento da instância pintado no diagrama */
(function () {
  'use strict';
  if (!window.BpmnJS) return;
  const destaque = window.App.lerJSON('tarefa-destaque');

  document.querySelectorAll('[data-visualizador]').forEach(async (box) => {
    const dados = window.App.lerJSON(box.dataset.visualizador);
    if (!dados || !dados.xml) return;
    const viewer = new window.BpmnJS({ container: box });
    try {
      await viewer.importXML(dados.xml);
    } catch (e) {
      box.insertAdjacentHTML('beforeend', '<div class="vazio-mini">Não foi possível desenhar o fluxo.</div>');
      return;
    }
    const canvas = viewer.get('canvas');
    const registro = viewer.get('elementRegistry');
    Object.entries(dados.situacao || {}).forEach(([id, sit]) => {
      if (registro.get(id)) canvas.addMarker(id, 'sit-' + sit);
    });
    (dados.caminho || []).forEach((id) => { if (registro.get(id)) canvas.addMarker(id, 'sit-caminho'); });
    canvas.zoom('fit-viewport', 'auto');
    if (destaque && registro.get(destaque) && canvas.zoom() > 1.1) canvas.zoom(1.1);

    box.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {
      const z = canvas.zoom();
      if (b.dataset.zoom === 'mais') canvas.zoom(z * 1.2);
      else if (b.dataset.zoom === 'menos') canvas.zoom(z / 1.2);
      else canvas.zoom('fit-viewport', 'auto');
    }));
    let t;
    window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => canvas.zoom('fit-viewport', 'auto'), 200); });
  });
})();
