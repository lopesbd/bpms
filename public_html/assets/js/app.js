/* Fluxa BPMS — utilidades comuns a todas as páginas */
(function () {
  'use strict';

  const csrf = document.querySelector('meta[name=csrf]')?.content || '';

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  async function api(url, dados, metodo) {
    const opts = { method: metodo || (dados === undefined ? 'GET' : 'POST'), headers: { 'X-CSRF': csrf, Accept: 'application/json' }, credentials: 'same-origin' };
    if (dados instanceof FormData) {
      opts.body = dados;
    } else if (dados !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(dados);
    }
    let resp;
    try {
      resp = await fetch(url, opts);
    } catch (e) {
      throw new Error('Sem conexão com o servidor. Verifique sua internet.');
    }
    let json = null;
    try { json = await resp.json(); } catch (e) { /* resposta não-JSON */ }
    if (!resp.ok || !json || json.ok === false) {
      if (resp.status === 401) setTimeout(() => location.reload(), 1800);
      throw new Error(json?.erro || 'Erro inesperado (' + resp.status + ').');
    }
    return json;
  }

  function toast(msg, tipo) {
    const box = document.querySelector('[data-toasts]');
    if (!box) return alert(msg);
    const t = document.createElement('div');
    t.className = 'toast' + (tipo === 'erro' ? ' erro' : '');
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => { t.classList.add('saindo'); setTimeout(() => t.remove(), 220); }, tipo === 'erro' ? 6000 : 3500);
  }

  /** Modal genérico. botoes: [{texto, classe, acao(fechar) -> bool|Promise}] */
  function modal({ titulo, corpo, botoes = [], largo = false, classe = "", aoAbrir }) {
    const fundo = document.createElement('div');
    fundo.className = 'modal-fundo';
    fundo.innerHTML = `<div class="modal${largo ? ' largo' : ''} ${esc(classe)}" role="dialog" aria-modal="true">
      <div class="modal-cab"><h3>${esc(titulo)}</h3><button type="button" class="btn-icone" data-fechar aria-label="Fechar">✕</button></div>
      <div class="modal-corpo"></div>
      ${botoes.length ? '<div class="modal-rodape"></div>' : ''}
    </div>`;
    const corpoEl = fundo.querySelector('.modal-corpo');
    if (typeof corpo === 'string') corpoEl.innerHTML = corpo; else if (corpo) corpoEl.appendChild(corpo);
    const fechar = () => { fundo.remove(); document.removeEventListener('keydown', tecla); };
    const tecla = (e) => { if (e.key === 'Escape') fechar(); };
    document.addEventListener('keydown', tecla);
    fundo.addEventListener('mousedown', (e) => { if (e.target === fundo) fechar(); });
    fundo.querySelector('[data-fechar]').addEventListener('click', fechar);
    const rod = fundo.querySelector('.modal-rodape');
    botoes.forEach((b) => {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'btn ' + (b.classe || '');
      el.textContent = b.texto;
      el.addEventListener('click', async () => {
        if (!b.acao) return fechar();
        carregando(el, true);
        try {
          const r = await b.acao(fechar, corpoEl);
          if (r !== false) fechar();
        } catch (err) {
          toast(err.message, 'erro');
        } finally {
          carregando(el, false);
        }
      });
      rod.appendChild(el);
    });
    document.body.appendChild(fundo);
    const foco = corpoEl.querySelector('[autofocus]') || corpoEl.querySelector('input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]),textarea');
    if (foco) setTimeout(() => foco.focus(), 50);
    if (aoAbrir) aoAbrir(corpoEl, fechar);
    return { fechar, corpo: corpoEl };
  }

  function confirmar(mensagem, { titulo = 'Confirmar', texto = 'Confirmar', perigo = false } = {}) {
    return new Promise((resolve) => {
      let ok = false;
      const m = modal({
        titulo,
        corpo: `<p style="margin:0">${esc(mensagem)}</p>`,
        botoes: [
          { texto: 'Cancelar' },
          { texto, classe: perigo ? 'btn-perigo' : 'btn-primario', acao: () => { ok = true; } },
        ],
      });
      const obs = new MutationObserver(() => { if (!document.body.contains(m.corpo)) { obs.disconnect(); resolve(ok); } });
      obs.observe(document.body, { childList: true });
    });
  }

  function carregando(btn, sim) {
    if (!btn) return;
    if (sim) {
      btn.dataset.html = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="carregando"></span>' + btn.innerHTML;
    } else if (btn.dataset.html !== undefined) {
      btn.disabled = false;
      btn.innerHTML = btn.dataset.html;
      delete btn.dataset.html;
    }
  }

  /* ---------- Formulários dinâmicos (tarefas e início de processo) ---------- */

  function moedaParaNumero(s) {
    s = String(s || '').trim();
    if (!s) return '';
    return s.replace(/\./g, '').replace(',', '.');
  }

  function coletarFormulario(raiz) {
    const dados = {};
    const faltando = [];
    raiz.querySelectorAll('.campo[data-campo]').forEach((campo) => {
      const nome = campo.dataset.campo;
      const tipo = campo.dataset.tipo;
      campo.classList.remove('invalido');
      let valor;
      if (tipo === 'checkbox') {
        const cb = campo.querySelector('input[type=checkbox]');
        if (!cb) return;
        valor = cb.checked;
      } else if (tipo === 'radio') {
        const r = campo.querySelector('input[type=radio]:checked');
        if (!campo.querySelector('input[type=radio]')) return;
        valor = r ? r.value : '';
      } else if (tipo === 'arquivo') {
        valor = Array.from(campo.querySelectorAll('.upload-lista li[data-id]')).map((li) => Number(li.dataset.id));
        if (campo.querySelector('[data-obrigatorio]') && !valor.length) faltando.push(campo);
      } else {
        const el = campo.querySelector('input,select,textarea');
        if (!el) return;
        valor = el.hasAttribute('data-moeda') ? moedaParaNumero(el.value) : el.value;
      }
      const req = campo.querySelector('[required]');
      if (req && (valor === '' || valor === false)) faltando.push(campo);
      dados[nome] = valor;
    });
    faltando.forEach((c) => c.classList.add('invalido'));
    if (faltando.length) faltando[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    return { dados, faltando };
  }

  function prepararUploads(raiz) {
    raiz.querySelectorAll('[data-upload]').forEach((box) => {
      const input = box.querySelector('input[type=file]');
      const lista = box.querySelector('.upload-lista');
      const enviar = (arquivos) => Array.from(arquivos).forEach(async (arq) => {
        const li = document.createElement('li');
        li.className = 'enviando';
        li.innerHTML = `<span>${esc(arq.name)}</span><span class="barra"><i></i></span>`;
        lista.appendChild(li);
        const fd = new FormData();
        fd.append('arquivo', arq);
        try {
          const r = await enviarComProgresso('api/anexos.php', fd, (p) => { li.querySelector('.barra i').style.width = p + '%'; });
          li.className = '';
          li.dataset.id = r.anexo.id;
          li.innerHTML = `<a href="arquivo.php?id=${r.anexo.id}" target="_blank" rel="noopener">${esc(r.anexo.nome)}</a><button type="button" class="btn-icone" data-remover-anexo title="Remover">✕</button>`;
          box.closest('.campo')?.classList.remove('invalido');
        } catch (e) {
          li.remove();
          toast(arq.name + ': ' + e.message, 'erro');
        }
      });
      input.addEventListener('change', () => { enviar(input.files); input.value = ''; });
      ['dragenter', 'dragover'].forEach((ev) => box.addEventListener(ev, (e) => { e.preventDefault(); box.classList.add('arrastando'); }));
      ['dragleave', 'drop'].forEach((ev) => box.addEventListener(ev, (e) => { e.preventDefault(); box.classList.remove('arrastando'); }));
      box.addEventListener('drop', (e) => enviar(e.dataTransfer.files));
      lista.addEventListener('click', (e) => { if (e.target.closest('[data-remover-anexo]')) e.target.closest('li').remove(); });
    });
    raiz.querySelectorAll('[data-moeda]').forEach((el) => {
      el.addEventListener('blur', () => {
        const n = parseFloat(moedaParaNumero(el.value));
        if (!isNaN(n)) el.value = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      });
    });
  }

  function enviarComProgresso(url, fd, progresso) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url);
      xhr.setRequestHeader('X-CSRF', csrf);
      xhr.upload.onprogress = (e) => { if (e.lengthComputable) progresso(Math.round((e.loaded / e.total) * 100)); };
      xhr.onload = () => {
        let j = null;
        try { j = JSON.parse(xhr.responseText); } catch (e) { /* */ }
        if (xhr.status >= 200 && xhr.status < 300 && j && j.ok) resolve(j); else reject(new Error(j?.erro || 'Falha no envio.'));
      };
      xhr.onerror = () => reject(new Error('Falha de conexão.'));
      xhr.send(fd);
    });
  }

  /* ---------- Topo: menus e notificações ---------- */

  function alternar(painel, gatilho) {
    const abrir = painel.hidden;
    document.querySelectorAll('[data-notif-painel],[data-usuario-painel]').forEach((p) => { p.hidden = true; });
    painel.hidden = !abrir;
    if (abrir) {
      const fora = (e) => { if (!painel.contains(e.target) && !gatilho.contains(e.target)) { painel.hidden = true; document.removeEventListener('click', fora); } };
      setTimeout(() => document.addEventListener('click', fora), 0);
    }
    return abrir;
  }

  async function carregarNotificacoes() {
    const lista = document.querySelector('[data-notif-lista]');
    try {
      const r = await api('api/notificacoes.php');
      atualizarContador(r.nao_lidas);
      if (!r.itens.length) { lista.innerHTML = '<div class="vazio-mini">Nenhuma notificação por aqui.</div>'; return; }
      lista.innerHTML = r.itens.map((n) => `<a class="notif-item${n.lida ? '' : ' nao-lida'}" href="${esc(n.link || '#')}" data-notif-id="${n.id}">
        <span class="ponto"></span><span><strong>${esc(n.titulo)}</strong>${n.texto ? `<p>${esc(n.texto)}</p>` : ''}<small>${esc(n.quando)}</small></span></a>`).join('');
    } catch (e) {
      lista.innerHTML = `<div class="vazio-mini">${esc(e.message)}</div>`;
    }
  }

  function atualizarContador(n) {
    const c = document.querySelector('[data-notif-contador]');
    if (!c) return;
    c.textContent = n > 99 ? '99+' : n;
    c.classList.toggle('oculto', !n);
  }

  document.addEventListener('click', async (e) => {
    const bN = e.target.closest('[data-abrir-notificacoes]');
    if (bN) {
      if (alternar(document.querySelector('[data-notif-painel]'), bN)) carregarNotificacoes();
      return;
    }
    const bU = e.target.closest('[data-abrir-usuario]');
    if (bU) { alternar(document.querySelector('[data-usuario-painel]'), bU); return; }
    if (e.target.closest('[data-ler-todas]')) {
      await api('api/notificacoes.php', { acao: 'ler_todas' }).catch(() => {});
      carregarNotificacoes();
      return;
    }
    const item = e.target.closest('[data-notif-id]');
    if (item) {
      e.preventDefault();
      api('api/notificacoes.php', { acao: 'ler', id: Number(item.dataset.notifId) }).catch(() => {}).finally(() => { location.href = item.getAttribute('href'); });
      return;
    }
    if (e.target.closest('[data-abrir-menu]')) {
      document.querySelector('[data-menu-lateral]')?.classList.toggle('aberto');
      return;
    }
    const tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a,button,input,select')) location.href = tr.dataset.href;
  });

  // contador de notificações atualizado a cada minuto
  if (document.querySelector('[data-notif-contador]')) {
    setInterval(() => {
      if (document.hidden) return;
      api('api/notificacoes.php?contar=1').then((r) => atualizarContador(r.nao_lidas)).catch(() => {});
    }, 60000);
  }

  // mensagem deixada pelo servidor após redirecionamento
  const flash = document.getElementById('flash');
  if (flash) {
    try { const f = JSON.parse(flash.textContent); toast(f.mensagem, f.tipo); } catch (e) { /* */ }
  }

  function lerJSON(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    try { return JSON.parse(el.textContent); } catch (e) { return null; }
  }

  // anima barras/colunas de gráficos quando aparecem
  requestAnimationFrame(() => {
    document.querySelectorAll('[data-largura]').forEach((el) => { el.style.width = el.dataset.largura + '%'; });
    document.querySelectorAll('[data-altura]').forEach((el) => { el.style.height = el.dataset.altura + '%'; });
  });

  window.App = { api, toast, modal, confirmar, carregando, esc, coletarFormulario, prepararUploads, lerJSON };
})();
