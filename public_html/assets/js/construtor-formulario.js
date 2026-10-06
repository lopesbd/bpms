/* Construtor de formulários com arrastar e soltar (usado no modelador) */
(function () {
  'use strict';
  const { modal, esc, toast } = window.App;

  const SIMBOLOS = { texto: 'Aa', textarea: '¶', numero: '#', moeda: 'R$', data: '📅', email: '@', selecao: '▾', radio: '◉', checkbox: '✓', arquivo: '📎', titulo: 'H', info: 'i' };
  const COM_OPCOES = ['selecao', 'radio'];
  const SEM_VALOR = ['titulo', 'info'];

  function slug(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 50);
  }

  function abrir({ titulo, campos, tipos, outrasEtapas = [], aoSalvar }) {
    let lista = JSON.parse(JSON.stringify(campos || []));
    lista.forEach((c) => { c.id = c.id || novoId(); });
    let selId = lista[0]?.id || null;
    let previa = false;

    function novoId() { return 'c' + Math.random().toString(36).slice(2, 9); }
    function varUnica(base) {
      let v = slug(base) || 'campo';
      let n = 2;
      const usadas = new Set(lista.map((c) => c.variavel));
      const orig = v;
      while (usadas.has(v)) v = orig + '_' + n++;
      return v;
    }
    function novoCampo(tipo) {
      const rotulo = { titulo: 'Nova seção', info: 'Texto de orientação para quem preenche.' }[tipo] || tipos[tipo] || 'Campo';
      const c = { id: novoId(), tipo, rotulo, variavel: SEM_VALOR.includes(tipo) ? '' : varUnica(rotulo), obrigatorio: false, largura: 'inteira', _varAuto: true };
      if (COM_OPCOES.includes(tipo)) c.opcoes = ['Opção 1', 'Opção 2'];
      return c;
    }

    const raiz = document.createElement('div');
    raiz.className = 'fb';
    raiz.innerHTML = `
      <div class="fb-paleta">
        <h4>Arraste para o formulário</h4>
        ${Object.entries(tipos).map(([k, v]) => `<div class="fb-item" draggable="true" data-novo="${k}" title="Arraste ou clique para adicionar"><i>${SIMBOLOS[k] || '·'}</i>${esc(v)}</div>`).join('')}
        ${outrasEtapas.length ? `<h4 style="margin-top:16px">Reaproveitar</h4>
          <select data-copiar style="height:36px;font-size:12.5px;padding:0 8px"><option value="">Mostrar dados de outra etapa…</option>${outrasEtapas.map((o, i) => `<option value="${i}">${esc(o.nome)} (${o.campos.length})</option>`).join('')}</select>
          <small class="ajuda">Adiciona os campos da etapa escolhida como somente leitura.</small>` : ''}
      </div>
      <div class="fb-centro">
        <div class="fb-centro-topo"><strong data-contagem></strong>
          <label class="switch-linha"><input type="checkbox" data-previa><span class="switch"></span><span>Pré-visualizar</span></label></div>
        <div class="fb-lista" data-lista></div>
        <div class="fb-preview" data-preview hidden></div>
      </div>
      <div class="fb-props" data-props></div>`;

    const elLista = raiz.querySelector('[data-lista]');
    const elProps = raiz.querySelector('[data-props]');
    const elPrev = raiz.querySelector('[data-preview]');

    function render() {
      raiz.querySelector('[data-contagem]').textContent = lista.length + (lista.length === 1 ? ' campo' : ' campos');
      if (!lista.length) {
        elLista.innerHTML = '<div class="fb-vazio">Arraste campos da esquerda para cá<br><small>ou clique num tipo de campo para adicioná-lo</small></div>';
      } else {
        elLista.innerHTML = lista.map((c, i) => `
          <div class="fb-campo${c.id === selId ? ' sel' : ''}" draggable="true" data-idx="${i}">
            <span class="pega" title="Arraste para reordenar">⋮⋮</span>
            <div class="info"><strong>${esc(c.tipo === 'info' ? (c.rotulo || '').slice(0, 60) : c.rotulo || '(sem rótulo)')}${c.obrigatorio ? ' <span class="marca-obrig">*</span>' : ''}</strong>
              <small>${esc(tipos[c.tipo] || c.tipo)}${c.variavel ? ' · <code>' + esc(c.variavel) + '</code>' : ''}${c.somente_leitura ? ' · somente leitura' : ''}${c.largura === 'metade' ? ' · meia largura' : ''}</small></div>
            <button type="button" class="btn-icone" data-dup="${i}" title="Duplicar">⧉</button>
            <button type="button" class="btn-icone perigo" data-del="${i}" title="Remover">✕</button>
          </div>`).join('');
      }
      renderProps();
      if (previa) renderPrevia();
    }

    function campoSel() { return lista.find((c) => c.id === selId); }

    function renderProps() {
      const c = campoSel();
      if (!c) { elProps.innerHTML = '<h4>Propriedades</h4><p class="ajuda">Selecione um campo para editar.</p>'; return; }
      const temValor = !SEM_VALOR.includes(c.tipo);
      elProps.innerHTML = `
        <h4>Propriedades do campo</h4>
        <div class="campo"><label>Tipo do campo</label><select data-tipo-campo>${Object.entries(tipos).map(([k, v]) => `<option value="${k}"${k === c.tipo ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select></div>
        <div class="campo"><label>${c.tipo === 'info' ? 'Texto' : 'Rótulo'}</label>${c.tipo === 'info'
          ? `<textarea data-p="rotulo" rows="4">${esc(c.rotulo || '')}</textarea><small class="ajuda">Aceita {{variavel}}.</small>`
          : `<input data-p="rotulo" value="${esc(c.rotulo || '')}" maxlength="150">`}</div>
        ${temValor ? `
        <div class="campo"><label>Variável <span class="obrig">nome interno</span></label><input data-p="variavel" value="${esc(c.variavel || '')}" maxlength="50" style="font-family:ui-monospace,Consolas,monospace">
          <small class="ajuda">Usada nas condições e em {{${esc(c.variavel || 'variavel')}}}. Mesma variável em outra etapa = mesmo dado.</small></div>
        ${COM_OPCOES.includes(c.tipo) ? `<div class="campo"><label>Opções <span class="obrig">uma por linha</span></label><textarea data-p="opcoes" rows="5">${esc((c.opcoes || []).join('\n'))}</textarea></div>` : ''}
        ${['texto', 'textarea', 'numero', 'email', 'moeda'].includes(c.tipo) ? `<div class="campo"><label>Texto de exemplo</label><input data-p="placeholder" value="${esc(c.placeholder || '')}" maxlength="100"></div>` : ''}
        <div class="campo"><label>Ajuda</label><input data-p="ajuda" value="${esc(c.ajuda || '')}" maxlength="200" placeholder="Dica exibida abaixo do campo"></div>
        <div class="campo"><label>Largura</label><select data-p="largura"><option value="inteira"${c.largura !== 'metade' ? ' selected' : ''}>Linha inteira</option><option value="metade"${c.largura === 'metade' ? ' selected' : ''}>Meia linha</option></select></div>
        <div class="campo"><label class="switch-linha"><input type="checkbox" data-p="obrigatorio"${c.obrigatorio ? ' checked' : ''}><span class="switch"></span><span>Obrigatório</span></label></div>
        <div class="campo"><label class="switch-linha"><input type="checkbox" data-p="somente_leitura"${c.somente_leitura ? ' checked' : ''}><span class="switch"></span><span>Somente leitura</span></label><small class="ajuda">Mostra o valor já preenchido em etapa anterior, sem permitir alterar.</small></div>
        ` : ''}`;
    }

    function renderPrevia() {
      elPrev.innerHTML = '<div class="form-grid">' + lista.map((c) => {
        const larg = c.largura === 'metade' ? ' metade' : '';
        if (c.tipo === 'titulo') return `<h3 class="form-secao">${esc(c.rotulo)}</h3>`;
        if (c.tipo === 'info') return `<div class="form-info">${esc(c.rotulo)}</div>`;
        const lbl = c.tipo === 'checkbox' ? '' : `<label><span>${esc(c.rotulo)}</span>${c.obrigatorio ? '<span class="obrig">obrigatório</span>' : ''}</label>`;
        if (c.somente_leitura) return `<div class="campo${larg}">${lbl}<div class="valor-ro">valor da etapa anterior</div></div>`;
        const ph = c.placeholder ? ` placeholder="${esc(c.placeholder)}"` : '';
        let inp = `<input type="text"${ph}>`;
        if (c.tipo === 'textarea') inp = `<textarea rows="3"${ph}></textarea>`;
        if (c.tipo === 'numero') inp = `<input type="number"${ph}>`;
        if (c.tipo === 'data') inp = '<input type="date">';
        if (c.tipo === 'email') inp = `<input type="email"${ph}>`;
        if (c.tipo === 'moeda') inp = `<div class="input-prefixo"><span>R$</span><input type="text"${ph}></div>`;
        if (c.tipo === 'selecao') inp = `<select><option>Selecione...</option>${(c.opcoes || []).map((o) => `<option>${esc(o)}</option>`).join('')}</select>`;
        if (c.tipo === 'radio') inp = `<div class="opcoes-radio">${(c.opcoes || []).map((o) => `<label class="radio-pill"><input type="radio" name="pv_${c.id}"><span>${esc(o)}</span></label>`).join('')}</div>`;
        if (c.tipo === 'checkbox') inp = `<label class="switch-linha"><input type="checkbox"><span class="switch"></span><span>${esc(c.rotulo)}</span></label>`;
        if (c.tipo === 'arquivo') inp = '<label class="upload-area"><span><strong>Clique ou arraste arquivos aqui</strong></span></label>';
        return `<div class="campo${larg}">${lbl}${inp}${c.ajuda ? `<small class="ajuda">${esc(c.ajuda)}</small>` : ''}</div>`;
      }).join('') + '</div>' + (lista.length ? '' : '<p class="ajuda">Formulário vazio.</p>');
    }

    // edição de propriedades
    // troca do tipo do campo: mantém rótulo e variável, ajusta o que o novo tipo precisa
    elProps.addEventListener('change', (e) => {
      const sel = e.target.closest('[data-tipo-campo]');
      const c = campoSel();
      if (!sel || !c || sel.value === c.tipo) return;
      const novo = sel.value;
      const tinhaValor = !SEM_VALOR.includes(c.tipo);
      c.tipo = novo;
      if (SEM_VALOR.includes(novo)) {
        c.variavel = '';
        c.obrigatorio = false;
        c.somente_leitura = false;
      } else if (!tinhaValor || !c.variavel) {
        c.variavel = varUnica(c.rotulo || tipos[novo]);
        c._varAuto = true;
      }
      if (COM_OPCOES.includes(novo) && !(c.opcoes || []).length) c.opcoes = ['Opção 1', 'Opção 2'];
      if (!COM_OPCOES.includes(novo)) delete c.opcoes;
      if (!['texto', 'textarea', 'numero', 'email', 'moeda'].includes(novo)) delete c.placeholder;
      render();
    });

    elProps.addEventListener('input', (e) => {
      const el = e.target.closest('[data-p]');
      const c = campoSel();
      if (!el || !c) return;
      const p = el.dataset.p;
      if (el.type === 'checkbox') c[p] = el.checked;
      else if (p === 'opcoes') c.opcoes = el.value.split('\n').map((s) => s.trim()).filter(Boolean);
      else if (p === 'variavel') { c.variavel = slug(el.value); c._varAuto = false; }
      else c[p] = el.value;
      if (p === 'rotulo' && c._varAuto && !SEM_VALOR.includes(c.tipo)) {
        c.variavel = '';
        c.variavel = varUnica(el.value);
        const vi = elProps.querySelector('[data-p=variavel]');
        if (vi) vi.value = c.variavel;
      }
      const ativo = document.activeElement;
      const pos = ativo && ativo.selectionStart;
      const idx = lista.indexOf(c);
      // atualiza só o cartão do campo, sem perder o foco do painel
      const cartao = elLista.querySelector(`[data-idx="${idx}"] .info`);
      if (cartao) {
        cartao.querySelector('strong').innerHTML = esc(c.tipo === 'info' ? (c.rotulo || '').slice(0, 60) : c.rotulo || '(sem rótulo)') + (c.obrigatorio ? ' <span class="marca-obrig">*</span>' : '');
        cartao.querySelector('small').innerHTML = `${esc(tipos[c.tipo] || c.tipo)}${c.variavel ? ' · <code>' + esc(c.variavel) + '</code>' : ''}${c.somente_leitura ? ' · somente leitura' : ''}${c.largura === 'metade' ? ' · meia largura' : ''}`;
      }
      if (ativo && pos !== undefined && pos !== null && ativo.setSelectionRange && ativo.type !== 'checkbox') { try { ativo.setSelectionRange(pos, pos); } catch (er) { /* */ } }
      if (previa) renderPrevia();
    });

    // seleção, duplicar, remover
    elLista.addEventListener('click', (e) => {
      const dup = e.target.closest('[data-dup]');
      const del = e.target.closest('[data-del]');
      if (dup) {
        const i = Number(dup.dataset.dup);
        const copia = JSON.parse(JSON.stringify(lista[i]));
        copia.id = novoId();
        if (copia.variavel) copia.variavel = varUnica(copia.variavel);
        lista.splice(i + 1, 0, copia);
        selId = copia.id;
        render();
        return;
      }
      if (del) {
        const i = Number(del.dataset.del);
        lista.splice(i, 1);
        if (!lista.find((c) => c.id === selId)) selId = lista[Math.min(i, lista.length - 1)]?.id || null;
        render();
        return;
      }
      const card = e.target.closest('.fb-campo');
      if (card) { selId = lista[Number(card.dataset.idx)].id; render(); }
    });

    // clique na paleta adiciona no fim
    raiz.querySelector('.fb-paleta').addEventListener('click', (e) => {
      const it = e.target.closest('[data-novo]');
      if (!it) return;
      const c = novoCampo(it.dataset.novo);
      const i = lista.findIndex((x) => x.id === selId);
      lista.splice(i >= 0 ? i + 1 : lista.length, 0, c);
      selId = c.id;
      render();
      elLista.querySelector('.fb-campo.sel')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });

    const copiar = raiz.querySelector('[data-copiar]');
    if (copiar) copiar.addEventListener('change', () => {
      const o = outrasEtapas[Number(copiar.value)];
      copiar.value = '';
      if (!o) return;
      let n = 0;
      o.campos.forEach((c) => {
        if (SEM_VALOR.includes(c.tipo) || !c.variavel || lista.some((x) => x.variavel === c.variavel)) return;
        lista.push(Object.assign(JSON.parse(JSON.stringify(c)), { id: novoId(), somente_leitura: true, obrigatorio: false, _varAuto: false }));
        n++;
      });
      toast(n ? n + ' campo(s) adicionados como somente leitura.' : 'Os campos dessa etapa já estão no formulário.');
      render();
    });

    // arrastar e soltar
    let arrastando = null; // {tipo:'novo', valor} | {tipo:'mover', idx}
    const indicador = document.createElement('div');
    indicador.className = 'fb-indicador';

    raiz.addEventListener('dragstart', (e) => {
      const novo = e.target.closest('[data-novo]');
      const card = e.target.closest('.fb-campo');
      if (novo) arrastando = { tipo: 'novo', valor: novo.dataset.novo };
      else if (card) { arrastando = { tipo: 'mover', idx: Number(card.dataset.idx) }; requestAnimationFrame(() => card.classList.add('arrastando')); }
      else return;
      e.dataTransfer.effectAllowed = 'copyMove';
      e.dataTransfer.setData('text/plain', 'campo');
    });
    raiz.addEventListener('dragend', () => {
      arrastando = null;
      indicador.remove();
      elLista.classList.remove('alvo');
      elLista.querySelectorAll('.arrastando').forEach((c) => c.classList.remove('arrastando'));
    });

    function posicaoSoltar(y) {
      const cards = Array.from(elLista.querySelectorAll('.fb-campo'));
      for (let i = 0; i < cards.length; i++) {
        const r = cards[i].getBoundingClientRect();
        if (y < r.top + r.height / 2) return i;
      }
      return cards.length;
    }
    elLista.addEventListener('dragover', (e) => {
      if (!arrastando) return;
      e.preventDefault();
      elLista.classList.add('alvo');
      const pos = posicaoSoltar(e.clientY);
      const cards = elLista.querySelectorAll('.fb-campo');
      if (pos < cards.length) elLista.insertBefore(indicador, cards[pos]); else if (cards.length) elLista.appendChild(indicador);
    });
    elLista.addEventListener('dragleave', (e) => { if (!elLista.contains(e.relatedTarget)) { elLista.classList.remove('alvo'); indicador.remove(); } });
    elLista.addEventListener('drop', (e) => {
      if (!arrastando) return;
      e.preventDefault();
      let pos = posicaoSoltar(e.clientY);
      if (arrastando.tipo === 'novo') {
        const c = novoCampo(arrastando.valor);
        lista.splice(pos, 0, c);
        selId = c.id;
      } else {
        const [c] = lista.splice(arrastando.idx, 1);
        if (arrastando.idx < pos) pos--;
        lista.splice(pos, 0, c);
        selId = c.id;
      }
      arrastando = null;
      indicador.remove();
      render();
    });

    raiz.querySelector('[data-previa]').addEventListener('change', (e) => {
      previa = e.target.checked;
      elLista.hidden = previa;
      elPrev.hidden = !previa;
      if (previa) renderPrevia();
    });

    render();
    modal({
      titulo,
      corpo: raiz,
      classe: 'fb-modal',
      botoes: [{ texto: 'Cancelar' }, {
        texto: 'Aplicar formulário', classe: 'btn-primario',
        acao: () => {
          const semVar = lista.find((c) => !SEM_VALOR.includes(c.tipo) && !c.variavel);
          if (semVar) { selId = semVar.id; render(); throw new Error('O campo "' + semVar.rotulo + '" precisa de um nome de variável.'); }
          const vazio = lista.find((c) => COM_OPCOES.includes(c.tipo) && !(c.opcoes || []).length);
          if (vazio) { selId = vazio.id; render(); throw new Error('O campo "' + vazio.rotulo + '" precisa de pelo menos uma opção.'); }
          aoSalvar(lista.map((c) => { const x = Object.assign({}, c); delete x._varAuto; return x; }));
        },
      }],
    });
  }

  window.ConstrutorFormulario = { abrir };
})();
