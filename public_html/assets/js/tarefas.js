/* Caixa de tarefas: visão em quadro (kanban, com arrastar-e-soltar) ou em lista */
(function () {
  'use strict';
  const { api, toast, esc, lerJSON, carregando } = window.App;
  const kanban = document.querySelector('[data-kanban]');
  if (!kanban) return;

  const tarefas = lerJSON('tarefas-lista') || [];
  const porId = new Map(tarefas.map((t) => [t.id, t]));
  const busca = document.querySelector('[data-filtro-busca]');
  const proc = document.querySelector('[data-filtro-proc]');
  const lista = document.querySelector('[data-lista-tarefas]');
  const linhas = lista.querySelector('[data-linhas]');
  let visao = 'quadro';
  let situacao = '';
  let ordem = { campo: 'prazo', asc: true };

  /* ---------- preferências do usuário (só neste navegador) ---------- */
  function lerPref(chave, padrao) {
    try { return localStorage.getItem('fluxa.tarefas.' + chave) || padrao; } catch (e) { return padrao; }
  }
  function gravarPref(chave, valor) {
    try { localStorage.setItem('fluxa.tarefas.' + chave, valor); } catch (e) { /* navegação privada */ }
  }

  /* ---------- filtros (valem para as duas visões) ---------- */
  function passaFiltro(t) {
    const termo = busca.value.trim().toLowerCase();
    return (!termo || t.busca.includes(termo)) && (!proc.value || t.processo === proc.value);
  }
  function filtrarQuadro() {
    kanban.querySelectorAll('.cartao-tarefa').forEach((c) => {
      const t = porId.get(Number(c.dataset.tarefa));
      c.hidden = t ? !passaFiltro(t) : false;
    });
  }
  function aplicarFiltros() {
    if (visao === 'quadro') filtrarQuadro(); else renderLista();
  }
  busca.addEventListener('input', aplicarFiltros);
  proc.addEventListener('change', aplicarFiltros);

  /* ---------- alternar visão ---------- */
  function mostrarVisao(v) {
    visao = v === 'lista' ? 'lista' : 'quadro';
    document.querySelectorAll('[data-visao]').forEach((b) => {
      const ativo = b.dataset.visao === visao;
      b.classList.toggle('ativo', ativo);
      b.setAttribute('aria-pressed', String(ativo));
    });
    kanban.hidden = visao !== 'quadro';
    lista.hidden = visao !== 'lista';
    gravarPref('visao', visao);
    aplicarFiltros();
  }
  document.querySelector('[data-alternador]').addEventListener('click', (e) => {
    const b = e.target.closest('[data-visao]');
    if (b) mostrarVisao(b.dataset.visao);
  });

  /* ---------- visão em lista ---------- */
  const SITUACOES = {
    grupo: ['Disponível', 'info'],
    minhas: ['Comigo', 'ok'],
    concluidas: ['Concluída', 'neutro'],
  };

  function comparar(a, b) {
    let va = a[ordem.campo];
    let vb = b[ordem.campo];
    if (ordem.campo === 'nome') { va = va.toLowerCase(); vb = vb.toLowerCase(); }
    // prazo não importa mais para tarefa concluída
    if (ordem.campo === 'prazo') {
      if (a.situacao === 'concluidas') va = null;
      if (b.situacao === 'concluidas') vb = null;
    }
    // tarefas sem prazo vão sempre para o fim
    if (va === null && vb === null) return 0;
    if (va === null) return 1;
    if (vb === null) return -1;
    const r = va < vb ? -1 : va > vb ? 1 : 0;
    return ordem.asc ? r : -r;
  }

  function renderLista() {
    const visiveis = tarefas.filter(passaFiltro);
    lista.querySelectorAll('[data-conta-sit]').forEach((s) => {
      const sit = s.dataset.contaSit;
      s.textContent = visiveis.filter((t) => !sit || t.situacao === sit).length;
    });
    const itens = visiveis.filter((t) => !situacao || t.situacao === situacao).sort(comparar);
    lista.querySelector('[data-lista-vazia]').hidden = itens.length > 0;
    linhas.innerHTML = itens.map((t) => {
      const [rotulo, tipo] = SITUACOES[t.situacao];
      let prazo = '<span class="sub">Sem prazo</span>';
      if (t.situacao === 'concluidas') prazo = `<span class="sub">Concluída em ${esc(t.concluida_txt)}</span>`;
      else if (t.prazo) prazo = `<span class="prazo ${esc(t.prazo_cls)}" title="${esc(t.prazo_data)}">${esc(t.prazo_txt)}</span><span class="sub">${esc(t.prazo_data)}</span>`;
      let acoes = '';
      if (t.situacao === 'grupo') acoes = `<button type="button" class="btn btn-sm" data-acao-lista="assumir" data-id="${t.id}">Assumir</button>`;
      if (t.situacao === 'minhas' && t.grupo) acoes = `<button type="button" class="btn btn-sm" data-acao-lista="devolver" data-id="${t.id}">Devolver</button>`;
      acoes += ` <a class="btn btn-sm ${t.situacao === 'concluidas' ? '' : 'btn-primario'}" href="tarefa.php?id=${t.id}">${t.situacao === 'concluidas' ? 'Ver' : 'Abrir'}</a>`;
      return `<tr data-href="tarefa.php?id=${t.id}" class="linha-tarefa" style="--cor:${esc(t.cor)}">
        <td><strong>${esc(t.nome)}</strong>${t.urgente && t.situacao !== 'concluidas' ? ' <span class="prio-alta">Urgente</span>' : ''}${t.grupo ? `<span class="sub">Fila: ${esc(t.grupo)}</span>` : ''}</td>
        <td data-rotulo="Processo"><b class="cod-proc">${esc(t.codigo)}</b> · ${esc(t.titulo)}<span class="sub">${esc(t.processo)}</span></td>
        <td><span class="badge badge-${tipo}">${rotulo}</span></td>
        <td data-rotulo="Prazo">${prazo}</td>
        <td data-rotulo="Recebida">${esc(t.recebida_txt)}</td>
        <td class="acoes">${acoes}</td>
      </tr>`;
    }).join('');
  }

  lista.querySelector('[data-situacoes]').addEventListener('click', (e) => {
    const b = e.target.closest('[data-situacao]');
    if (!b) return;
    situacao = b.dataset.situacao;
    lista.querySelectorAll('[data-situacao]').forEach((x) => x.classList.toggle('ativo', x === b));
    gravarPref('situacao', situacao);
    renderLista();
  });

  lista.querySelector('thead').addEventListener('click', (e) => {
    const b = e.target.closest('[data-ordem]');
    if (!b) return;
    ordem = { campo: b.dataset.ordem, asc: ordem.campo === b.dataset.ordem ? !ordem.asc : true };
    lista.querySelectorAll('[data-ordem]').forEach((x) => {
      x.classList.toggle('ativo', x === b);
      x.classList.toggle('desc', x === b && !ordem.asc);
    });
    renderLista();
  });

  linhas.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-acao-lista]');
    if (!b) return;
    const id = Number(b.dataset.id);
    const acao = b.dataset.acaoLista;
    carregando(b, true);
    try {
      await api('api/tarefas.php', { acao, id });
      mudarSituacao(id, acao === 'assumir' ? 'minhas' : 'grupo');
      toast(acao === 'assumir' ? 'Tarefa assumida. Ela agora é sua.' : 'Tarefa devolvida ao grupo.');
      renderLista();
    } catch (err) {
      toast(err.message, 'erro');
      carregando(b, false);
    }
  });

  /* ---------- sincroniza o quadro e a lista ---------- */
  function atualizarContagens() {
    kanban.querySelectorAll('.coluna').forEach((col) => {
      const n = col.querySelectorAll('.cartao-tarefa').length;
      const c = col.querySelector('[data-conta]');
      if (c) c.textContent = n;
      col.querySelector('[data-dica]').hidden = n > 0;
      const k = document.querySelector(`[data-kpi="${col.dataset.coluna}"]`);
      if (k) k.textContent = n;
    });
  }
  function mudarSituacao(id, nova) {
    const t = porId.get(id);
    if (t) t.situacao = nova;
    const cartao = kanban.querySelector(`.cartao-tarefa[data-tarefa="${id}"]`);
    const col = kanban.querySelector(`[data-coluna="${nova}"] .coluna-lista`);
    if (cartao && col && !col.contains(cartao)) col.insertBefore(cartao, col.querySelector('[data-dica]'));
    atualizarContagens();
  }

  /* ---------- visão em quadro: arrastar e soltar ---------- */
  let arrastado = null;
  let origem = null;

  kanban.addEventListener('dragstart', (e) => {
    const c = e.target.closest('.cartao-tarefa[draggable=true]');
    if (!c) return;
    arrastado = c;
    origem = c.closest('.coluna').dataset.coluna;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', c.dataset.tarefa);
    requestAnimationFrame(() => c.classList.add('arrastando'));
    kanban.querySelectorAll('.coluna').forEach((col) => {
      if (!destinoValido(col.dataset.coluna)) col.classList.add('proibido');
    });
  });
  kanban.addEventListener('dragend', () => {
    arrastado?.classList.remove('arrastando');
    kanban.querySelectorAll('.coluna').forEach((col) => col.classList.remove('alvo', 'proibido'));
    arrastado = null;
  });

  function destinoValido(destino) {
    if (!arrastado || destino === origem) return false;
    if (origem === 'grupo') return destino === 'minhas' || destino === 'concluidas';
    if (origem === 'minhas') return destino === 'concluidas' || (destino === 'grupo' && arrastado.dataset.grupo === '1');
    return false;
  }

  kanban.querySelectorAll('.coluna').forEach((col) => {
    col.addEventListener('dragover', (e) => {
      if (!destinoValido(col.dataset.coluna)) return;
      e.preventDefault();
      col.classList.add('alvo');
    });
    col.addEventListener('dragleave', (e) => { if (!col.contains(e.relatedTarget)) col.classList.remove('alvo'); });
    col.addEventListener('drop', async (e) => {
      e.preventDefault();
      col.classList.remove('alvo');
      const destino = col.dataset.coluna;
      if (!destinoValido(destino)) return;
      const cartao = arrastado;
      const id = Number(cartao.dataset.tarefa);
      if (destino === 'concluidas') {
        // concluir exige preencher o formulário: abre a tarefa
        if (origem === 'grupo') {
          try { await api('api/tarefas.php', { acao: 'assumir', id }); } catch (err) { toast(err.message, 'erro'); return; }
        }
        location.href = 'tarefa.php?id=' + id;
        return;
      }
      const listaCol = col.querySelector('.coluna-lista');
      const antes = cartao.parentElement;
      const proximo = cartao.nextSibling;
      listaCol.insertBefore(cartao, listaCol.querySelector('[data-dica]'));
      atualizarContagens();
      try {
        await api('api/tarefas.php', { acao: destino === 'minhas' ? 'assumir' : 'devolver', id });
        const t = porId.get(id);
        if (t) t.situacao = destino;
        toast(destino === 'minhas' ? 'Tarefa assumida. Ela agora é sua.' : 'Tarefa devolvida ao grupo.');
      } catch (err) {
        antes.insertBefore(cartao, proximo);
        atualizarContagens();
        toast(err.message, 'erro');
      }
    });
  });

  /* ---------- início: volta para a última visão escolhida ---------- */
  situacao = lerPref('situacao', '');
  if (!['', 'grupo', 'minhas', 'concluidas'].includes(situacao)) situacao = '';
  lista.querySelectorAll('[data-situacao]').forEach((x) => x.classList.toggle('ativo', x.dataset.situacao === situacao));
  mostrarVisao(lerPref('visao', 'quadro'));
})();
