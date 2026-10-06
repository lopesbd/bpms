/* Modal com as propriedades gerais de um processo (nome, sigla, cor, ícone, quem inicia) */
(function () {
  'use strict';
  const { api, modal, esc, lerJSON } = window.App;
  const CORES = ['#00995d', '#00ae9d', '#0b7a75', '#7db61c', '#0369a1', '#49479d', '#a21caf', '#c2410c', '#b45309', '#0c2c34'];

  function abrir(dados, aoSalvar) {
    const grupos = lerJSON('grupos') || [];
    const icones = lerJSON('icones') || {};
    const d = Object.assign({ nome: '', sigla: '', descricao: '', categoria: '', cor: CORES[0], icone: 'fluxo', titulo_modelo: '', ativo: true, iniciadores: [] }, dados || {});
    const novo = !d.id;
    const corpo = document.createElement('div');
    corpo.innerHTML = `
      <div class="form-grid">
        <div class="campo metade"><label for="fi-nome">Nome do processo <span class="obrig">obrigatório</span></label><input id="fi-nome" maxlength="120" value="${esc(d.nome)}" placeholder="Ex.: Solicitação de compra"></div>
        <div class="campo metade"><label for="fi-sigla">Sigla <span class="obrig">usada no código</span></label><input id="fi-sigla" maxlength="8" value="${esc(d.sigla)}" placeholder="Ex.: COMP" style="text-transform:uppercase"></div>
        <div class="campo metade"><label for="fi-cat">Categoria</label><input id="fi-cat" maxlength="80" value="${esc(d.categoria || '')}" placeholder="Ex.: Financeiro"></div>
        <div class="campo metade"><label for="fi-titulo">Título de cada solicitação</label><input id="fi-titulo" maxlength="200" value="${esc(d.titulo_modelo || '')}" placeholder="Ex.: Compra de {{assunto}}"><small class="ajuda">Use {{variavel}} para montar com dados do formulário inicial.</small></div>
        <div class="campo"><label for="fi-desc">Descrição</label><textarea id="fi-desc" maxlength="500" rows="2" placeholder="Aparece no catálogo de processos">${esc(d.descricao || '')}</textarea></div>
        <div class="campo metade"><label>Cor</label><div class="opcoes-radio" data-cores>${CORES.map((c) => `<label class="radio-pill"><input type="radio" name="fi-cor" value="${c}"${c === d.cor ? ' checked' : ''}><span style="background:${c};border-color:${c};width:30px;height:30px;padding:0;display:block;box-shadow:${c === d.cor ? '0 0 0 3px #fff inset' : 'none'}"></span></label>`).join('')}</div></div>
        <div class="campo metade"><label>Ícone</label><div class="opcoes-radio">${Object.entries(icones).map(([k, svg]) => `<label class="radio-pill" title="${k}"><input type="radio" name="fi-icone" value="${k}"${k === d.icone ? ' checked' : ''}><span style="padding:6px 8px;display:grid">${svg}</span></label>`).join('')}</div></div>
        <div class="campo"><label>Quem pode iniciar</label>
          <div class="opcoes-radio">${grupos.length ? grupos.map((g) => `<label class="check-linha" style="border:1px solid var(--borda);border-radius:999px;padding:5px 12px"><input type="checkbox" value="${g.id}"${d.iniciadores.includes(g.id) ? ' checked' : ''} data-iniciador> ${esc(g.nome)}</label>`).join('') : '<small class="ajuda">Nenhum grupo cadastrado.</small>'}</div>
          <small class="ajuda">Nenhum marcado = todos os usuários podem iniciar.</small></div>
        ${novo ? '' : `<div class="campo"><label class="switch-linha"><input type="checkbox" id="fi-ativo"${d.ativo ? ' checked' : ''}><span class="switch"></span><span>Disponível para iniciar</span></label></div>`}
      </div>`;
    corpo.querySelector('[data-cores]').addEventListener('change', () => {
      corpo.querySelectorAll('[data-cores] span').forEach((s) => { s.style.boxShadow = s.previousElementSibling.checked ? '0 0 0 3px #fff inset' : 'none'; });
    });
    const nome = corpo.querySelector('#fi-nome');
    const sigla = corpo.querySelector('#fi-sigla');
    nome.addEventListener('input', () => {
      if (sigla.dataset.mexeu) return;
      const s = nome.value.normalize('NFD').replace(/[̀-ͯ]/g, '').split(/\s+/).filter((w) => w.length > 2).map((w) => w[0]).join('').toUpperCase();
      sigla.value = (s.length >= 2 ? s : nome.value.normalize('NFD').replace(/[^A-Za-z]/g, '').slice(0, 4)).toUpperCase().slice(0, 8);
    });
    sigla.addEventListener('input', () => { sigla.dataset.mexeu = '1'; });

    modal({
      titulo: novo ? 'Novo fluxo de processo' : 'Propriedades do processo',
      corpo,
      largo: true,
      botoes: [{ texto: 'Cancelar' }, {
        texto: novo ? 'Criar e abrir o modelador' : 'Salvar', classe: 'btn-primario',
        acao: async () => {
          const payload = {
            acao: novo ? 'criar' : 'info',
            id: d.id,
            nome: nome.value,
            sigla: sigla.value,
            categoria: corpo.querySelector('#fi-cat').value,
            titulo_modelo: corpo.querySelector('#fi-titulo').value,
            descricao: corpo.querySelector('#fi-desc').value,
            cor: corpo.querySelector('[name=fi-cor]:checked')?.value,
            icone: corpo.querySelector('[name=fi-icone]:checked')?.value,
            iniciadores: Array.from(corpo.querySelectorAll('[data-iniciador]:checked')).map((c) => Number(c.value)),
            ativo: novo ? true : corpo.querySelector('#fi-ativo').checked,
          };
          const r = await api('api/fluxos.php', payload);
          aoSalvar(r, payload);
        },
      }],
    });
  }

  window.FluxoInfo = { abrir };
})();
