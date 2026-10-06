/* Modelador BPMN (bpmn-js) + painel de propriedades que configura a execução */
(function () {
  'use strict';
  const { api, toast, modal, confirmar, carregando, esc, lerJSON } = window.App;
  const D = lerJSON('dados-modelador');
  if (!D || !window.BpmnJS) return;

  let config = (D.config && typeof D.config === 'object' && !Array.isArray(D.config)) ? D.config : {};
  let versao = D.versao;
  let temPublicada = D.temPublicada;
  let sujo = false;
  let selecionado = null;

  /* ---------- Tradução da interface do bpmn-js ---------- */
  const T = {
    'Activate hand tool': 'Mover a tela (mão)',
    'Activate the hand tool': 'Mover a tela (mão)',
    'Activate lasso tool': 'Selecionar vários (laço)',
    'Activate the lasso tool': 'Selecionar vários (laço)',
    'Activate create/remove space tool': 'Abrir/fechar espaço',
    'Activate the create/remove space tool': 'Abrir/fechar espaço',
    'Activate global connect tool': 'Conectar elementos',
    'Activate the global connect tool': 'Conectar elementos',
    'Create start event': 'Evento de início',
    'Create StartEvent': 'Evento de início',
    'Create intermediate/boundary event': 'Evento intermediário (temporizador)',
    'Create Intermediate/Boundary Event': 'Evento intermediário (temporizador)',
    'Create end event': 'Evento de fim',
    'Create EndEvent': 'Evento de fim',
    'Create gateway': 'Gateway (decisão / paralelo)',
    'Create Gateway': 'Gateway (decisão / paralelo)',
    'Create task': 'Tarefa',
    'Create Task': 'Tarefa',
    'Create user task': 'Tarefa humana',
    'Create expanded sub-process': 'Subprocesso',
    'Create expanded SubProcess': 'Subprocesso',
    'Create pool/participant': 'Piscina / raias',
    'Create Pool/Participant': 'Piscina / raias',
    'Create group': 'Agrupar visualmente',
    'Create Group': 'Agrupar visualmente',
    'Create data object reference': 'Objeto de dados',
    'Create data store reference': 'Repositório de dados',
    'Create DataObjectReference': 'Objeto de dados',
    'Create DataStoreReference': 'Repositório de dados',
    'Append end event': 'Adicionar fim',
    'Append EndEvent': 'Adicionar fim',
    'Append gateway': 'Adicionar gateway',
    'Append Gateway': 'Adicionar gateway',
    'Append task': 'Adicionar tarefa',
    'Append Task': 'Adicionar tarefa',
    'Append user task': 'Adicionar tarefa humana',
    'Append intermediate/boundary event': 'Adicionar evento intermediário',
    'Append Intermediate/Boundary Event': 'Adicionar evento intermediário',
    'Append text annotation': 'Adicionar anotação',
    'Add text annotation': 'Adicionar anotação',
    'Append TextAnnotation': 'Adicionar anotação',
    'Change type': 'Trocar o tipo',
    'Change element': 'Trocar o tipo',
    'Delete': 'Excluir',
    'Remove': 'Excluir',
    'Connect using sequence/message flow or association': 'Conectar (arraste até o destino)',
    'Connect using association': 'Conectar com associação',
    'Connect using DataInputAssociation': 'Conectar dados',
    'Connect to other element': 'Conectar a outro elemento',
    'Add lane above': 'Adicionar raia acima',
    'Add lane below': 'Adicionar raia abaixo',
    'Add Lane above': 'Adicionar raia acima',
    'Add Lane below': 'Adicionar raia abaixo',
    'Divide into two lanes': 'Dividir em 2 raias',
    'Divide into three lanes': 'Dividir em 3 raias',
    'Task': 'Tarefa',
    'User task': 'Tarefa humana',
    'User Task': 'Tarefa humana',
    'Service task': 'Automação (serviço)',
    'Service Task': 'Automação (serviço)',
    'Send task': 'Enviar mensagem / e-mail',
    'Send Task': 'Enviar mensagem / e-mail',
    'Receive task': 'Aguardar confirmação',
    'Receive Task': 'Aguardar confirmação',
    'Manual task': 'Tarefa manual',
    'Manual Task': 'Tarefa manual',
    'Business rule task': 'Regra de negócio',
    'Business Rule Task': 'Regra de negócio',
    'Script task': 'Script (automação)',
    'Script Task': 'Script (automação)',
    'Call activity': 'Chamar outro processo',
    'Call Activity': 'Chamar outro processo',
    'Sub-process (collapsed)': 'Subprocesso (recolhido)',
    'Sub-process (expanded)': 'Subprocesso (expandido)',
    'Exclusive gateway': 'Decisão (exclusivo)',
    'Exclusive Gateway': 'Decisão (exclusivo)',
    'Parallel gateway': 'Paralelo (todos os caminhos)',
    'Parallel Gateway': 'Paralelo (todos os caminhos)',
    'Inclusive gateway': 'Inclusivo (um ou mais caminhos)',
    'Inclusive Gateway': 'Inclusivo (um ou mais caminhos)',
    'Complex gateway': 'Complexo (não suportado)',
    'Complex Gateway': 'Complexo (não suportado)',
    'Event-based gateway': 'Baseado em eventos (não suportado)',
    'Event based Gateway': 'Baseado em eventos (não suportado)',
    'Start event': 'Início',
    'Start Event': 'Início',
    'End event': 'Fim',
    'End Event': 'Fim',
    'Terminate end event': 'Fim que encerra tudo',
    'Terminate End Event': 'Fim que encerra tudo',
    'Intermediate throw event': 'Evento intermediário',
    'Intermediate Throw Event': 'Evento intermediário',
    'Timer intermediate catch event': 'Temporizador (esperar)',
    'Timer Intermediate Catch Event': 'Temporizador (esperar)',
    'Timer start event': 'Início por tempo (não suportado)',
    'Message start event': 'Início por mensagem (não suportado)',
    'Sequence flow': 'Seta de fluxo',
    'Default flow': 'Caminho padrão',
    'Conditional flow': 'Caminho com condição',
    'Search in diagram': 'Buscar no diagrama',
    'Search': 'Buscar',
    'Gateways': 'Gateways',
    'Tasks': 'Tarefas',
    'Events': 'Eventos',
    'Sub-processes': 'Subprocessos',
    'Data': 'Dados',
    'Participants': 'Participantes',
    'Other': 'Outros',
  };
  function traduzir(template, subst) {
    subst = subst || {};
    const t = T[template] || template;
    return t.replace(/{([^}]+)}/g, (_, k) => (k in subst ? subst[k] : '{' + k + '}'));
  }

  const container = document.getElementById('mod-canvas');
  const modeler = new window.BpmnJS({
    container,
    additionalModules: [{ translate: ['value', traduzir] }],
  });
  const canvas = modeler.get('canvas');
  const registro = modeler.get('elementRegistry');
  const modeling = modeler.get('modeling');
  const selection = modeler.get('selection');
  const moddle = modeler.get('moddle');
  const painel = document.getElementById('mod-painel');

  /* ---------- Classificação dos elementos (igual ao motor PHP) ---------- */
  const CATEGORIAS = {
    'bpmn:StartEvent': 'inicio', 'bpmn:EndEvent': 'fim',
    'bpmn:Task': 'humana', 'bpmn:UserTask': 'humana', 'bpmn:ManualTask': 'humana', 'bpmn:ReceiveTask': 'humana',
    'bpmn:ServiceTask': 'automatica', 'bpmn:SendTask': 'automatica', 'bpmn:ScriptTask': 'automatica', 'bpmn:BusinessRuleTask': 'automatica',
    'bpmn:ExclusiveGateway': 'gw_exclusivo', 'bpmn:ParallelGateway': 'gw_paralelo', 'bpmn:InclusiveGateway': 'gw_inclusivo',
    'bpmn:IntermediateCatchEvent': 'intermediario', 'bpmn:IntermediateThrowEvent': 'passagem',
    'bpmn:SequenceFlow': 'fluxo',
  };
  const NOMES_TIPO = {
    inicio: 'Evento de início', fim: 'Evento de fim', humana: 'Tarefa humana', automatica: 'Automação',
    gw_exclusivo: 'Decisão (exclusivo)', gw_paralelo: 'Gateway paralelo', gw_inclusivo: 'Gateway inclusivo',
    intermediario: 'Evento intermediário', passagem: 'Evento intermediário', fluxo: 'Seta de fluxo',
  };
  const categoria = (el) => CATEGORIAS[el.businessObject?.$type || el.type] || null;
  const ehTimer = (el) => (el.businessObject.eventDefinitions || []).some((d) => d.$type === 'bpmn:TimerEventDefinition');
  const ehTerminate = (el) => (el.businessObject.eventDefinitions || []).some((d) => d.$type === 'bpmn:TerminateEventDefinition');
  const nomeEl = (el) => (el.businessObject?.name || '').trim() || NOMES_TIPO[categoria(el)] || el.id;

  function cfg(id) { return config[id] || {}; }
  function cfgEditavel(id) {
    if (!config[id] || typeof config[id] !== 'object' || Array.isArray(config[id])) config[id] = {};
    return config[id];
  }
  function definirCaminho(obj, caminho, valor) {
    const partes = caminho.split('.');
    let o = obj;
    partes.slice(0, -1).forEach((p) => { if (!o[p] || typeof o[p] !== 'object') o[p] = {}; o = o[p]; });
    o[partes[partes.length - 1]] = valor;
  }
  function lerCaminho(obj, caminho) {
    return caminho.split('.').reduce((o, p) => (o && typeof o === 'object' ? o[p] : undefined), obj);
  }

  /* ---------- Estado / barra superior ---------- */
  function marcarSujo(v = true) {
    sujo = v;
    renderStatus();
  }
  function renderStatus() {
    const st = document.querySelector('[data-mod-status]');
    const badge = versao.status === 'rascunho'
      ? `<span class="badge badge-alerta">Rascunho v${versao.numero}</span>`
      : `<span class="badge badge-ok">Publicada v${versao.numero}</span>`;
    st.innerHTML = badge + (sujo ? '<span class="sujo">● alterações não salvas</span>' : '<span>tudo salvo</span>');
    document.querySelectorAll('[data-so-rascunho]').forEach((b) => { b.hidden = !(versao.status === 'rascunho' && temPublicada); });
  }

  /* ---------- Pendências pintadas no diagrama ---------- */
  function problemasDe(el) {
    if (!el.businessObject || el.type === 'label' || el === canvas.getRootElement()) return [];
    const cat = categoria(el);
    const c = cfg(el.id);
    const p = [];
    if (cat === 'humana') {
      const t = c.atribuicao?.tipo;
      if (!t) p.push('Defina quem executa esta tarefa.');
      else if (t === 'usuario' && !pessoasDe(c.atribuicao).length) p.push('Escolha uma ou mais pessoas.');
      else if (t === 'grupo' && !c.atribuicao.grupo_id) p.push('Escolha o grupo responsável.');
      else if (t === 'executor' && !c.atribuicao.elemento_ref) p.push('Escolha a etapa de origem do executor.');
    }
    if (cat === 'automatica' && !c.acao) p.push('Escolha o que esta automação faz.');
    if (cat === 'intermediario' && !ehTimer(el)) p.push('Transforme este evento em temporizador.');
    if (cat === 'intermediario' && ehTimer(el) && !(Number(c.timer?.valor) > 0)) p.push('Informe o tempo de espera.');
    if (cat === 'gw_exclusivo' && (el.outgoing || []).length > 1) {
      const def = el.businessObject.default;
      const sem = el.outgoing.filter((f) => !(cfg(f.id).regras || []).some((r) => r.variavel) && def !== f.businessObject);
      if (sem.length) p.push('Toda saída precisa de condição ou ser o caminho padrão.');
    }
    if (cat === null && el.type !== 'label' && !['bpmn:Process', 'bpmn:Collaboration', 'bpmn:Participant', 'bpmn:Lane', 'bpmn:TextAnnotation', 'bpmn:Association', 'bpmn:Group', 'bpmn:MessageFlow'].includes(el.type)) {
      p.push('Este elemento não é executado pelo motor.');
    }
    if (cat && cat !== 'fluxo') {
      if (cat !== 'inicio' && !(el.incoming || []).some((f) => f.type === 'bpmn:SequenceFlow')) p.push('Nenhuma seta chega aqui.');
      if (cat !== 'fim' && !(el.outgoing || []).some((f) => f.type === 'bpmn:SequenceFlow')) p.push('Nenhuma seta sai daqui.');
    }
    return p;
  }
  function atualizarPendentes() {
    registro.getAll().forEach((el) => {
      if (el.type === 'label' || el.waypoints) return;
      const tem = problemasDe(el).length > 0;
      if (tem) canvas.addMarker(el.id, 'mod-pendente'); else canvas.removeMarker(el.id, 'mod-pendente');
    });
  }

  /* ---------- Variáveis conhecidas ---------- */
  function variaveisConhecidas() {
    const mapa = new Map();
    const add = (nome, rotulo, origem) => { if (nome && !mapa.has(nome)) mapa.set(nome, { nome, rotulo, origem }); };
    add('codigo', 'Código do processo', 'sistema');
    add('processo', 'Nome do processo', 'sistema');
    add('iniciador_nome', 'Nome de quem iniciou', 'sistema');
    add('iniciador_email', 'E-mail de quem iniciou', 'sistema');
    add('data_inicio', 'Data de início', 'sistema');
    registro.getAll().forEach((el) => {
      const c = cfg(el.id);
      const nome = el.businessObject ? nomeEl(el) : el.id;
      (c.formulario || []).forEach((f) => { if (f.variavel && !['titulo', 'info'].includes(f.tipo)) add(f.variavel, f.rotulo, nome); });
      if ((c.decisoes || []).length) add(c.decisao_variavel || 'decisao', 'Decisão: ' + nome, nome);
      if (c.acao === 'variavel') (c.variaveis || []).forEach((v) => add(v.nome, 'Definida por automação', nome));
      if (c.acao === 'webhook' && c.webhook?.salvar_em) add(c.webhook.salvar_em, 'Resposta da API', nome);
    });
    return Array.from(mapa.values());
  }
  function opcoesDecisao(variavel) {
    const out = new Set();
    registro.getAll().forEach((el) => {
      const c = cfg(el.id);
      if ((c.decisoes || []).length && (c.decisao_variavel || 'decisao') === variavel) c.decisoes.forEach((d) => out.add(d));
      (c.formulario || []).forEach((f) => { if (f.variavel === variavel && (f.opcoes || []).length) f.opcoes.forEach((o) => out.add(o)); if (f.variavel === variavel && f.tipo === 'checkbox') { out.add('sim'); out.add('não'); } });
    });
    return Array.from(out);
  }

  /* ---------- Painel de propriedades ---------- */
  const ICONES = {
    humana: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    automatica: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
    inicio: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>',
    fim: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><circle cx="12" cy="12" r="8"/></svg>',
    gateway: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l10 10-10 10L2 12z"/></svg>',
    timer: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
    fluxo: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>',
    processo: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="15" width="6" height="6" rx="1"/><path d="M6 9v3a3 3 0 0 0 3 3h6"/></svg>',
  };
  const classeIcone = (cat) => ({ humana: 'humana', automatica: 'automatica', inicio: 'inicio', fim: 'fim', gw_exclusivo: 'gateway', gw_paralelo: 'gateway', gw_inclusivo: 'gateway', intermediario: 'timer', passagem: 'timer', fluxo: 'fluxo' }[cat] || '');

  function cabecalhoPainel(el, cat) {
    const cls = classeIcone(cat);
    const tipo = cat === 'humana' && el.businessObject.$type !== 'bpmn:UserTask' ? 'Tarefa (' + traduzir(el.businessObject.$type.replace('bpmn:', '').replace(/([A-Z])/g, ' $1').trim()) + ')' : NOMES_TIPO[cat] || el.type.replace('bpmn:', '');
    return `<div class="pp-cab"><span class="pp-ic ${cls}">${ICONES[cls] || ICONES.processo}</span><div><strong>${esc(tipo)}</strong><small>ID: ${esc(el.id)}</small></div></div>`;
  }
  function blocoProblemas(el) {
    const p = problemasDe(el);
    return p.length ? `<div class="pp-secao" style="padding-bottom:4px">${p.map((x) => `<div class="pp-alerta">⚠ ${esc(x)}</div>`).join('')}</div>` : '';
  }
  function campoNome(el, rotulo = 'Nome') {
    return `<div class="campo"><label>${rotulo}</label><input type="text" data-nome value="${esc(el.businessObject.name || '')}" maxlength="150" placeholder="Ex.: Aprovar pedido"></div>`;
  }
  function chipsVariaveis(alvo) {
    const vars = variaveisConhecidas();
    return `<div class="campo"><label>Inserir variável <span class="obrig">clique para inserir</span></label><div class="chips">${vars.map((v) => `<button type="button" class="chip-var" data-inserir-var="${esc(v.nome)}" data-alvo="${alvo || ''}" title="${esc(v.rotulo)}">{{${esc(v.nome)}}}</button>`).join('')}</div></div>`;
  }
  function selectOpcoes(lista, atual, vazio = 'Selecione…') {
    return `<option value="">${vazio}</option>` + lista.map((o) => `<option value="${esc(o.id)}"${String(o.id) === String(atual ?? '') ? ' selected' : ''}>${esc(o.nome)}</option>`).join('');
  }
  function resumoFormulario(el) {
    const campos = cfg(el.id).formulario || [];
    return `<div class="pp-secao"><h4>Formulário <span class="tag">${campos.length} campos</span></h4>
      ${campos.length ? `<ul class="lista-mini">${campos.slice(0, 8).map((c) => `<li>${esc(c.tipo === 'info' ? 'Texto informativo' : c.rotulo)}${c.obrigatorio ? ' <span style="color:var(--erro)">*</span>' : ''}<small>${esc(D.tiposCampo[c.tipo] || c.tipo)}</small></li>`).join('')}${campos.length > 8 ? `<li>… e mais ${campos.length - 8}</li>` : ''}</ul>` : '<p class="ajuda">Nenhum campo. Quem executar só precisa concluir.</p>'}
      <button type="button" class="btn btn-sm btn-primario" style="margin-top:10px;width:100%" data-editar-form>✎ Montar formulário (arrastar e soltar)</button></div>`;
  }

  /** Pessoas escolhidas na atividade (aceita o formato antigo, com um único usuario_id). */
  function pessoasDe(at) {
    if (!at) return [];
    if (Array.isArray(at.usuario_ids)) return at.usuario_ids.map(Number).filter(Boolean);
    return at.usuario_id ? [Number(at.usuario_id)] : [];
  }
  const NEGATIVA = /reprov|recus|negar|neg[ao]|cancel|devolv|rejeit/i;
  const corPadrao = (texto) => (NEGATIVA.test(texto) ? '#c62828' : '#00995d');
  function corTexto(hex) {
    const n = parseInt(hex.slice(1), 16);
    const luz = (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
    return luz > 0.62 ? '#142b30' : '#ffffff';
  }
  function estiloBotao(hex) { return `background:${hex};border-color:${hex};color:${corTexto(hex)}`; }

  function painelHumana(el) {
    const c = cfg(el.id);
    // prazo antigo (só em horas) passa para o formato novo
    if (c.prazo_horas && !c.prazo_valor) {
      const ce = cfgEditavel(el.id);
      ce.prazo_valor = Number(c.prazo_horas);
      ce.prazo_unidade = 'horas';
      delete ce.prazo_horas;
    }
    const at = c.atribuicao || {};
    const pessoas = pessoasDe(at);
    const decisoes = c.decisoes || [];
    const cores = c.cores_botoes || {};
    const outras = registro.filter((x) => categoria(x) === 'humana' && x.id !== el.id).map((x) => ({ id: x.id, nome: nomeEl(x) }));
    const bo = el.businessObject.$type;
    return `
      <div class="pp-secao"><h4>Geral</h4>${campoNome(el)}
        <div class="campo"><label>Tipo de tarefa</label><div class="pp-tipos">
          <button type="button" data-trocar="bpmn:UserTask" class="${bo === 'bpmn:UserTask' || bo === 'bpmn:Task' ? 'ativo' : ''}">👤 Humana</button>
          <button type="button" data-trocar="bpmn:ServiceTask">⚡ Automação</button>
          <button type="button" data-trocar="bpmn:SendTask">✉ E-mail</button>
          <button type="button" data-trocar="bpmn:ManualTask" class="${bo === 'bpmn:ManualTask' ? 'ativo' : ''}">✋ Manual</button>
        </div></div></div>
      <div class="pp-secao"><h4>Responsável</h4>
        <div class="campo"><label>Quem executa</label><select data-cfg="atribuicao.tipo" data-rerender>
          ${selectOpcoes([
            { id: 'usuario', nome: 'Uma ou mais pessoas' }, { id: 'grupo', nome: 'Um grupo (quem assumir primeiro)' },
            { id: 'iniciador', nome: 'Quem iniciou o processo' }, { id: 'gestor', nome: 'Gestor de quem iniciou' },
            { id: 'executor', nome: 'Quem executou outra etapa' }], at.tipo)}</select></div>
        ${at.tipo === 'usuario' ? `<div class="campo"><label>Pessoas <span class="obrig">${pessoas.length > 1 ? 'quem assumir primeiro fica com a tarefa' : 'uma ou mais'}</span></label>
          <div class="chips" style="margin-bottom:8px">${pessoas.map((id) => { const u = D.usuarios.find((x) => Number(x.id) === id); return `<span class="chip">${esc(u ? u.nome : 'Usuário removido')}<button type="button" data-rem-pessoa="${id}" title="Remover">✕</button></span>`; }).join('') || '<small class="ajuda">Nenhuma pessoa escolhida.</small>'}</div>
          <select data-add-pessoa><option value="">+ Adicionar pessoa…</option>${D.usuarios.filter((u) => !pessoas.includes(Number(u.id))).map((u) => `<option value="${u.id}">${esc(u.nome)}</option>`).join('')}</select>
          ${pessoas.length > 1 ? '<small class="ajuda">A tarefa aparece em “Disponíveis” para todas essas pessoas; quem assumir primeiro fica com ela.</small>' : ''}</div>` : ''}
        ${at.tipo === 'grupo' ? `<div class="campo"><label>Grupo</label><select data-cfg="atribuicao.grupo_id" data-num>${selectOpcoes(D.grupos, at.grupo_id)}</select>${D.grupos.length ? '' : '<small class="ajuda">Cadastre grupos em Configurações › Grupos.</small>'}</div>` : ''}
        ${at.tipo === 'executor' ? `<div class="campo"><label>Mesma pessoa que executou</label><select data-cfg="atribuicao.elemento_ref">${selectOpcoes(outras, at.elemento_ref)}</select></div>` : ''}
        ${at.tipo === 'gestor' ? '<p class="ajuda" style="margin-top:8px">O gestor é definido no cadastro do usuário. Sem gestor, a tarefa volta para quem iniciou.</p>' : ''}
        <div class="regra-linha" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px">
          <div class="campo" style="margin:0"><label>Prazo</label><div style="display:grid;grid-template-columns:1fr 96px;gap:6px"><input type="number" min="0" step="1" data-cfg="prazo_valor" data-num value="${esc(c.prazo_valor ?? '')}" placeholder="Sem prazo"><select data-cfg="prazo_unidade">${selectOpcoes([{ id: 'minutos', nome: 'minutos' }, { id: 'horas', nome: 'horas' }], c.prazo_unidade || 'horas', 'horas')}</select></div></div>
          <div class="campo" style="margin:0"><label>Prioridade</label><select data-cfg="prioridade">${selectOpcoes([{ id: 'baixa', nome: 'Baixa' }, { id: 'normal', nome: 'Normal' }, { id: 'alta', nome: 'Urgente' }], c.prioridade || 'normal', 'Normal')}</select></div>
        </div>
        <div class="prazo-rapido">${[[30, 'minutos', '30 min'], [1, 'horas', '1h'], [4, 'horas', '4h'], [8, 'horas', '8h'], [24, 'horas', '24h'], [48, 'horas', '48h']].map(([v, u, r]) => `<button type="button" data-prazo="${v}" data-unidade="${u}">${r}</button>`).join('')}</div>
        <div class="campo" style="margin-top:10px"><label class="switch-linha"><input type="checkbox" data-cfg="prazo_uteis" data-rerender-check${c.prazo_uteis ? ' checked' : ''}><span class="switch"></span><span>Contar só horas úteis</span></label>
          <small class="ajuda">${c.prazo_uteis ? `Conta de segunda a sexta, das ${esc(D.expediente.ini)} às ${esc(D.expediente.fim)}, sem feriados (ajuste em Config. › Geral).` : 'Desligado: o prazo corre direto, inclusive à noite e no fim de semana.'}</small></div>
      </div>
      <div class="pp-secao"><h4>Instruções</h4>
        <div class="campo"><textarea data-cfg="instrucoes" id="pp-instrucoes" rows="3" placeholder="Explique o que a pessoa deve fazer. Aceita {{variavel}}.">${esc(c.instrucoes || '')}</textarea></div>
        ${chipsVariaveis('pp-instrucoes')}</div>
      <div class="pp-secao"><h4>Botões de decisão</h4>
        <p class="ajuda" style="margin-bottom:10px">Cada decisão vira um botão na tarefa (ex.: Aprovar / Reprovar). A escolha fica gravada na variável e pode ser usada nas condições das setas.</p>
        <div class="chips">${decisoes.map((d, i) => `<span class="chip chip-cor"><input type="color" data-cor-decisao="${esc(d)}" value="${esc(cores[d] || corPadrao(d))}" title="Cor do botão">${esc(d)}<button type="button" data-rem-decisao="${i}" title="Remover">✕</button></span>`).join('') || '<small class="ajuda">Sem decisões: haverá apenas o botão “Concluir”.</small>'}</div>
        <div class="linha-add"><input type="text" data-nova-decisao placeholder="Nova decisão…" maxlength="40"><button type="button" class="btn btn-sm" data-add-decisao>Adicionar</button></div>
        <div class="campo" style="margin-top:12px"><label>Cor dos botões <span class="obrig">${decisoes.length ? 'clique na bolinha de cada decisão' : 'botão Concluir'}</span></label>
          ${decisoes.length ? '' : `<label class="chip chip-cor" style="cursor:pointer"><input type="color" data-cfg="cor_concluir" value="${esc(c.cor_concluir || '#00995d')}"> Concluir tarefa</label>`}
          <div class="previa-botoes" data-previa-botoes>${(decisoes.length ? decisoes : ['Concluir tarefa']).map((d) => `<span class="btn btn-sm" data-previa="${esc(d)}" style="${estiloBotao(decisoes.length ? (cores[d] || corPadrao(d)) : (c.cor_concluir || '#00995d'))}">${esc(d)}</span>`).join('')}</div></div>
        ${decisoes.length ? `<div class="campo" style="margin-top:10px"><label>Gravar na variável</label><input type="text" data-cfg="decisao_variavel" value="${esc(c.decisao_variavel || 'decisao')}" style="font-family:ui-monospace,Consolas,monospace"></div>` : ''}
      </div>
      ${resumoFormulario(el)}`;
  }

  function painelDestinatario(base, c) {
    const pt = c.para_tipo || 'iniciador';
    return `<div class="campo"><label>Para quem</label><select data-cfg="${base}.para_tipo" data-rerender>${selectOpcoes([
      { id: 'iniciador', nome: 'Quem iniciou o processo' }, { id: 'gestor', nome: 'Gestor de quem iniciou' }, { id: 'usuario', nome: 'Um usuário' },
      { id: 'grupo', nome: 'Todos de um grupo' }, ...(base === 'email' ? [{ id: 'fixo', nome: 'E-mail(s) fixo(s)' }, { id: 'variavel', nome: 'E-mail informado no formulário' }] : [])], pt, 'Quem iniciou o processo')}</select></div>
      ${pt === 'usuario' ? `<div class="campo"><label>Usuário</label><select data-cfg="${base}.usuario_id" data-num>${selectOpcoes(D.usuarios, c.usuario_id)}</select></div>` : ''}
      ${pt === 'grupo' ? `<div class="campo"><label>Grupo</label><select data-cfg="${base}.grupo_id" data-num>${selectOpcoes(D.grupos, c.grupo_id)}</select></div>` : ''}
      ${pt === 'fixo' ? `<div class="campo"><label>E-mails</label><input type="text" data-cfg="${base}.para" value="${esc(c.para || '')}" placeholder="financeiro@empresa.com, outro@..."></div>` : ''}
      ${pt === 'variavel' ? `<div class="campo"><label>Variável com o e-mail</label><select data-cfg="${base}.para_variavel">${selectOpcoes(variaveisConhecidas().map((v) => ({ id: v.nome, nome: v.rotulo + ' (' + v.nome + ')' })), c.para_variavel)}</select></div>` : ''}`;
  }

  function painelAutomatica(el) {
    const c = cfg(el.id);
    const acao = c.acao || (el.businessObject.$type === 'bpmn:SendTask' ? '' : '');
    let detalhe = '';
    if (acao === 'email') {
      const e = c.email || {};
      detalhe = `<div class="pp-secao"><h4>E-mail</h4>${painelDestinatario('email', e)}
        <div class="campo"><label>Assunto</label><input type="text" id="pp-assunto" data-cfg="email.assunto" value="${esc(e.assunto || '')}" placeholder="Processo {{codigo}} atualizado"></div>
        <div class="campo"><label>Mensagem</label><textarea id="pp-corpo" data-cfg="email.corpo" rows="6" placeholder="Olá {{iniciador_nome}}, ...">${esc(e.corpo || '')}</textarea></div>
        ${chipsVariaveis('pp-corpo')}</div>`;
    } else if (acao === 'notificacao') {
      const n = c.notificacao || {};
      detalhe = `<div class="pp-secao"><h4>Notificação no sistema</h4>${painelDestinatario('notificacao', n)}
        <div class="campo"><label>Título</label><input type="text" data-cfg="notificacao.titulo" value="${esc(n.titulo || '')}" placeholder="Seu pedido {{codigo}} foi aprovado"></div>
        <div class="campo"><label>Texto</label><textarea id="pp-notif" data-cfg="notificacao.texto" rows="3">${esc(n.texto || '')}</textarea></div>
        ${chipsVariaveis('pp-notif')}</div>`;
    } else if (acao === 'variavel') {
      const lista = c.variaveis || [];
      detalhe = `<div class="pp-secao"><h4>Definir variáveis</h4>
        <p class="ajuda" style="margin-bottom:10px">Use texto, {{variavel}} ou contas simples: <code>{{valor}} * 1.1</code></p>
        ${lista.map((v, i) => `<div class="regra"><button type="button" class="btn-icone perigo" data-rem-var="${i}">✕</button>
          <div class="regra-linha"><input type="text" data-lista="variaveis.${i}.nome" value="${esc(v.nome || '')}" placeholder="variavel" style="font-family:ui-monospace,Consolas,monospace"><input type="text" data-lista="variaveis.${i}.valor" value="${esc(v.valor || '')}" placeholder="valor"></div></div>`).join('')}
        <button type="button" class="btn btn-sm" style="margin-top:10px" data-add-var>+ Adicionar variável</button>
        <div style="margin-top:12px">${chipsVariaveis('')}</div></div>`;
    } else if (acao === 'webhook') {
      const w = c.webhook || {};
      detalhe = `<div class="pp-secao"><h4>Chamada de API</h4>
        <div class="regra-linha" style="display:grid;grid-template-columns:100px 1fr;gap:8px"><select data-cfg="webhook.metodo">${['POST', 'GET', 'PUT', 'PATCH'].map((m) => `<option${(w.metodo || 'POST') === m ? ' selected' : ''}>${m}</option>`).join('')}</select>
        <input type="url" data-cfg="webhook.url" value="${esc(w.url || '')}" placeholder="https://api.exemplo.com/pedidos"></div>
        <div class="campo"><label>Cabeçalho Authorization</label><input type="text" data-cfg="webhook.autorizacao" value="${esc(w.autorizacao || '')}" placeholder="Bearer seu-token (opcional)"></div>
        <div class="campo"><label>Corpo JSON</label><textarea id="pp-json" data-cfg="webhook.corpo" rows="5" style="font-family:ui-monospace,Consolas,monospace;font-size:12px" placeholder='{"codigo": "{{codigo}}"}  — vazio envia todas as variáveis'>${esc(w.corpo || '')}</textarea></div>
        ${chipsVariaveis('pp-json')}
        <div class="campo"><label>Guardar resposta na variável</label><input type="text" data-cfg="webhook.salvar_em" value="${esc(w.salvar_em || '')}" placeholder="resposta_api" style="font-family:ui-monospace,Consolas,monospace"><small class="ajuda">Campos do JSON de resposta viram resposta_api_campo.</small></div></div>`;
    }
    return `<div class="pp-secao"><h4>Geral</h4>${campoNome(el)}
        <div class="campo"><label>Tipo de tarefa</label><div class="pp-tipos">
          <button type="button" data-trocar="bpmn:UserTask">👤 Humana</button>
          <button type="button" data-trocar="bpmn:ServiceTask" class="${el.businessObject.$type === 'bpmn:ServiceTask' ? 'ativo' : ''}">⚡ Automação</button>
          <button type="button" data-trocar="bpmn:SendTask" class="${el.businessObject.$type === 'bpmn:SendTask' ? 'ativo' : ''}">✉ E-mail</button>
          <button type="button" data-trocar="bpmn:ScriptTask" class="${el.businessObject.$type === 'bpmn:ScriptTask' ? 'ativo' : ''}">⚙ Script</button>
        </div></div>
        <div class="campo"><label>O que fazer</label><select data-cfg="acao" data-rerender>${selectOpcoes(Object.entries(D.acoes).map(([id, nome]) => ({ id, nome })), acao)}</select></div>
        <p class="ajuda" style="margin-top:8px">Automações rodam sozinhas, sem ninguém precisar agir. Se falharem, o processo para e os administradores são avisados.</p></div>${detalhe}`;
  }

  function painelGateway(el, cat) {
    const textos = {
      gw_exclusivo: 'Segue por <b>um único caminho</b>: o primeiro cuja condição for verdadeira. Configure as condições clicando em cada seta de saída.',
      gw_paralelo: 'Na saída, segue por <b>todos os caminhos ao mesmo tempo</b>. Na junção (várias setas chegando), <b>espera todos</b> terminarem.',
      gw_inclusivo: 'Segue por <b>todos os caminhos cuja condição for verdadeira</b> (setas sem condição sempre seguem). Na junção, espera os caminhos ativos.',
    };
    const saidas = (el.outgoing || []).filter((f) => f.type === 'bpmn:SequenceFlow');
    const def = el.businessObject.default;
    const lista = cat !== 'gw_paralelo' && saidas.length ? `<div class="pp-secao"><h4>Caminhos de saída</h4><ul class="lista-mini">${saidas.map((f) => {
      const regras = (cfg(f.id).regras || []).filter((r) => r.variavel);
      const res = def === f.businessObject ? '<small style="color:var(--verde)">padrão</small>' : regras.length ? `<small>${regras.length} condição(ões)</small>` : '<small style="color:var(--erro)">sem condição</small>';
      return `<li class="clicavel" data-selecionar="${esc(f.id)}">→ ${esc(f.businessObject.name || nomeEl(f.target))}${res}</li>`;
    }).join('')}</ul>
      <div class="campo" style="margin-top:12px"><label>Caminho padrão <span class="obrig">quando nenhuma condição bater</span></label><select data-padrao>${selectOpcoes(saidas.map((f) => ({ id: f.id, nome: '→ ' + (f.businessObject.name || nomeEl(f.target)) })), def ? saidas.find((f) => f.businessObject === def)?.id : '', 'Nenhum')}</select></div></div>` : '';
    return `<div class="pp-secao"><h4>Geral</h4>${campoNome(el, 'Pergunta / nome')}<p class="pp-texto" style="margin-top:10px">${textos[cat]}</p></div>${lista}`;
  }

  function painelFluxo(el) {
    const c = cfg(el.id);
    const origem = el.source;
    const catOrigem = origem ? categoria(origem) : null;
    const saidasOrigem = origem ? (origem.outgoing || []).filter((f) => f.type === 'bpmn:SequenceFlow').length : 0;
    const condicional = ['gw_exclusivo', 'gw_inclusivo'].includes(catOrigem) || (saidasOrigem > 1 && catOrigem !== 'gw_paralelo');
    const vars = variaveisConhecidas();
    const regras = c.regras && c.regras.length ? c.regras : [];
    const ops = [['igual', 'é igual a'], ['diferente', 'é diferente de'], ['maior', 'é maior que'], ['menor', 'é menor que'], ['maior_igual', 'é maior ou igual a'], ['menor_igual', 'é menor ou igual a'], ['contem', 'contém'], ['vazio', 'está vazio'], ['nao_vazio', 'está preenchido']];
    const ehPadrao = origem && origem.businessObject.default === el.businessObject;
    let cond = '';
    if (condicional) {
      cond = `<div class="pp-secao"><h4>Condição para seguir por aqui</h4>
        ${ehPadrao ? '<p class="ajuda" style="margin-bottom:10px">Este é o caminho padrão: usado quando nenhuma outra condição for verdadeira.</p>' : ''}
        ${regras.map((r, i) => {
          const sugestoes = opcoesDecisao(r.variavel);
          return `${i ? `<div class="juncao">${c.juncao === 'ou' ? 'ou' : 'e'}</div>` : ''}<div class="regra"><button type="button" class="btn-icone perigo" data-rem-regra="${i}">✕</button>
          <select data-lista="regras.${i}.variavel" data-rerender>${selectOpcoes(vars.map((v) => ({ id: v.nome, nome: v.rotulo + ' (' + v.nome + ')' })), r.variavel, 'Variável…')}</select>
          <div class="regra-linha"><select data-lista="regras.${i}.operador" data-rerender>${ops.map(([k, t]) => `<option value="${k}"${(r.operador || 'igual') === k ? ' selected' : ''}>${t}</option>`).join('')}</select>
          ${['vazio', 'nao_vazio'].includes(r.operador) ? '<span></span>' : `<input type="text" data-lista="regras.${i}.valor" value="${esc(r.valor ?? '')}" placeholder="valor" list="sug-${i}"><datalist id="sug-${i}">${sugestoes.map((s) => `<option value="${esc(s)}">`).join('')}</datalist>`}</div></div>`;
        }).join('')}
        <button type="button" class="btn btn-sm" style="margin-top:10px" data-add-regra>+ Adicionar condição</button>
        ${regras.length > 1 ? `<div class="campo" style="margin-top:12px"><label>Combinar condições</label><select data-cfg="juncao">${selectOpcoes([{ id: 'e', nome: 'Todas precisam ser verdadeiras (E)' }, { id: 'ou', nome: 'Basta uma ser verdadeira (OU)' }], c.juncao || 'e', 'Todas precisam ser verdadeiras (E)')}</select></div>` : ''}
        ${catOrigem !== 'gw_paralelo' && origem ? `<div class="campo" style="margin-top:12px"><label class="switch-linha"><input type="checkbox" data-eh-padrao${ehPadrao ? ' checked' : ''}><span class="switch"></span><span>Caminho padrão</span></label></div>` : ''}
        ${regras.some((r) => r.variavel) ? '<button type="button" class="btn btn-sm" style="margin-top:10px;width:100%" data-rotulo-condicao>Usar a condição como rótulo da seta</button>' : ''}
      </div>`;
    }
    return `<div class="pp-secao"><h4>Geral</h4>${campoNome(el, 'Rótulo da seta')}
      <p class="pp-texto" style="margin-top:10px">De <b>${esc(origem ? nomeEl(origem) : '?')}</b> para <b>${esc(el.target ? nomeEl(el.target) : '?')}</b>.</p>
      ${!condicional ? '<p class="ajuda" style="margin-top:8px">Setas saindo de gateways de decisão (ou de atividades com mais de uma saída) podem ter condições.</p>' : ''}</div>${cond}`;
  }

  function painelInicio(el) {
    const c = cfg(el.id);
    return `<div class="pp-secao"><h4>Geral</h4>${campoNome(el)}<p class="pp-texto" style="margin-top:10px">O formulário abaixo é preenchido por quem <b>inicia</b> o processo. Os campos viram variáveis usadas no resto do fluxo.</p></div>
      <div class="pp-secao"><h4>Instruções para quem inicia</h4><textarea data-cfg="instrucoes" rows="2" placeholder="Opcional">${esc(c.instrucoes || '')}</textarea></div>
      ${resumoFormulario(el)}`;
  }

  function painelIntermediario(el) {
    const c = cfg(el.id);
    if (!ehTimer(el)) {
      return `<div class="pp-secao"><h4>Geral</h4>${campoNome(el)}<p class="pp-texto" style="margin:10px 0">O motor executa eventos intermediários do tipo <b>temporizador</b> (esperar um tempo antes de seguir).</p>
        <button type="button" class="btn btn-sm btn-primario" data-virar-timer>Transformar em temporizador</button></div>`;
    }
    const t = c.timer || {};
    return `<div class="pp-secao"><h4>Geral</h4>${campoNome(el)}</div>
      <div class="pp-secao"><h4>Esperar</h4><div class="regra-linha" style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <input type="number" min="0" step="0.5" data-cfg="timer.valor" data-num value="${esc(t.valor ?? '')}" placeholder="Quanto">
        <select data-cfg="timer.unidade">${selectOpcoes([{ id: 'minutos', nome: 'minutos' }, { id: 'horas', nome: 'horas' }, { id: 'dias', nome: 'dias' }], t.unidade || 'horas', 'horas')}</select></div>
        <p class="ajuda" style="margin-top:8px">O processo fica parado aqui e segue sozinho depois do tempo. Na Hostinger, configure o cron (veja o README) para precisão de minutos.</p></div>`;
  }

  function painelFim(el) {
    return `<div class="pp-secao"><h4>Geral</h4>${campoNome(el)}<p class="pp-texto" style="margin-top:10px">${ehTerminate(el)
      ? 'Fim que <b>encerra tudo</b>: cancela os outros caminhos paralelos ainda abertos.'
      : 'Quando todos os caminhos chegam a um fim, o processo é concluído e quem iniciou é avisado.'}</p></div>`;
  }

  function painelProcesso() {
    const vars = variaveisConhecidas();
    const qtd = (cat) => registro.filter((e) => categoria(e) === cat).length;
    const pend = registro.filter((e) => e.type !== 'label' && problemasDe(e).length);
    return `<div class="pp-cab"><span class="pp-ic">${ICONES.processo}</span><div><strong>${esc(D.processo.nome)}</strong><small>Clique num elemento para configurá-lo</small></div></div>
      <div class="pp-secao"><h4>Resumo</h4><ul class="lista-mini">
        <li>👤 Tarefas humanas<small>${qtd('humana')}</small></li><li>⚡ Automações<small>${qtd('automatica')}</small></li>
        <li>◆ Gateways<small>${qtd('gw_exclusivo') + qtd('gw_paralelo') + qtd('gw_inclusivo')}</small></li></ul></div>
      ${pend.length ? `<div class="pp-secao"><h4>Pendências <span class="tag">${pend.length}</span></h4><ul class="lista-mini">${pend.map((e) => `<li class="clicavel" data-selecionar="${esc(e.id)}" style="border-color:#fecdd3">⚠ ${esc(nomeEl(e))}<small>${esc(problemasDe(e)[0])}</small></li>`).join('')}</ul></div>` : '<div class="pp-secao"><div class="alerta alerta-ok" style="margin:0">✓ Nenhuma pendência. Pronto para publicar.</div></div>'}
      <div class="pp-secao"><h4>Variáveis do processo</h4><table class="var-tabela">${vars.map((v) => `<tr><td><code>${esc(v.nome)}</code></td><td>${esc(v.rotulo)}</td></tr>`).join('')}</table></div>
      <div class="pp-secao"><h4>Dicas</h4><p class="pp-texto">• Arraste elementos da paleta para o canvas.<br>• Passe o mouse num elemento e use os atalhos ao lado dele para conectar ou adicionar o próximo passo.<br>• A <b>chave inglesa</b> troca o tipo (ex.: tarefa → automação).<br>• Elementos com contorno vermelho tracejado precisam de configuração.<br>• Ctrl+Z desfaz, Ctrl+S salva.</p></div>`;
  }

  function renderPainel() {
    const el = selecionado;
    if (!el) { painel.innerHTML = painelProcesso(); return; }
    const cat = categoria(el);
    let corpo = '';
    if (cat === 'humana') corpo = painelHumana(el);
    else if (cat === 'automatica') corpo = painelAutomatica(el);
    else if (cat === 'inicio') corpo = painelInicio(el);
    else if (cat === 'fim') corpo = painelFim(el);
    else if (cat === 'fluxo') corpo = painelFluxo(el);
    else if (cat && cat.startsWith('gw_')) corpo = painelGateway(el, cat);
    else if (cat === 'intermediario') corpo = painelIntermediario(el);
    else if (cat === 'passagem') corpo = `<div class="pp-secao"><h4>Geral</h4>${campoNome(el)}<p class="pp-texto" style="margin-top:10px">Evento apenas informativo: o processo passa direto por ele.</p></div>`;
    else if (el.businessObject?.name !== undefined || el.type === 'bpmn:Participant' || el.type === 'bpmn:Lane') corpo = `<div class="pp-secao"><h4>Geral</h4>${campoNome(el)}<p class="pp-texto" style="margin-top:10px">${el.type === 'bpmn:Lane' || el.type === 'bpmn:Participant' ? 'Piscinas e raias organizam o desenho visualmente (ex.: uma raia por área). Quem executa cada tarefa é definido na própria tarefa.' : 'Este elemento não é executado pelo motor.'}</p></div>`;
    else corpo = '<div class="pp-secao"><p class="pp-texto">Elemento visual, sem configuração.</p></div>';
    painel.innerHTML = cabecalhoPainel(el, cat) + blocoProblemas(el) + corpo;
  }

  /* ---------- Eventos do painel ---------- */
  let tNome;
  painel.addEventListener('input', (e) => {
    const t = e.target;
    if (t.matches('[data-nome]') && selecionado) {
      clearTimeout(tNome);
      const el = selecionado;
      tNome = setTimeout(() => modeling.updateLabel(el, t.value), 350);
      return;
    }
    if (t.matches('[data-cor-decisao]') && selecionado) {
      const ce = cfgEditavel(selecionado.id);
      ce.cores_botoes = ce.cores_botoes || {};
      ce.cores_botoes[t.dataset.corDecisao] = t.value;
      const pv = Array.from(painel.querySelectorAll('[data-previa]')).find((x) => x.dataset.previa === t.dataset.corDecisao);
      if (pv) pv.setAttribute('style', estiloBotao(t.value));
      marcarSujo();
      return;
    }
    const alvo = t.closest('[data-cfg],[data-lista]');
    if (!alvo || !selecionado) return;
    let v = alvo.type === 'checkbox' ? alvo.checked : alvo.value;
    if (alvo.hasAttribute('data-num') && v !== '') v = Number(v);
    const c = cfgEditavel(selecionado.id);
    definirCaminho(c, alvo.dataset.cfg || alvo.dataset.lista, v);
    marcarSujo();
    atualizarPendentes();
    if (alvo.dataset.lista && alvo.dataset.lista.startsWith('regras.')) agendarCondicaoXml(selecionado);
    if (alvo.hasAttribute('data-rerender') && t.tagName === 'SELECT') renderPainel();
    if (alvo.hasAttribute('data-rerender-check')) renderPainel();
    if (alvo.dataset.cfg === 'cor_concluir') {
      const pv = painel.querySelector('[data-previa]');
      if (pv) pv.setAttribute('style', estiloBotao(alvo.value));
    }
    if (alvo.dataset.cfg && alvo.dataset.cfg.startsWith('prazo_')) delete cfgEditavel(selecionado.id).prazo_horas;
  });

  painel.addEventListener('click', (e) => {
    const t = e.target;
    const el = selecionado;
    const sel = t.closest('[data-selecionar]');
    if (sel) { const alvo = registro.get(sel.dataset.selecionar); if (alvo) { selection.select(alvo); canvas.scrollToElement?.(alvo); } return; }
    if (!el) return;
    const c = () => cfgEditavel(el.id);

    if (t.closest('[data-editar-form]')) { abrirFormulario(el); return; }
    const troca = t.closest('[data-trocar]');
    if (troca) { trocarTipo(el, troca.dataset.trocar); return; }
    const pz = t.closest('[data-prazo]');
    if (pz) { c().prazo_valor = Number(pz.dataset.prazo); c().prazo_unidade = pz.dataset.unidade; delete c().prazo_horas; marcarSujo(); renderPainel(); return; }
    if (t.closest('[data-add-decisao]')) {
      const inp = painel.querySelector('[data-nova-decisao]');
      const v = inp.value.trim();
      if (!v) return;
      c().decisoes = Array.from(new Set([...(c().decisoes || []), v]));
      if (!c().decisao_variavel) c().decisao_variavel = 'decisao';
      marcarSujo(); renderPainel();
      painel.querySelector('[data-nova-decisao]')?.focus();
      return;
    }
    const rd = t.closest('[data-rem-decisao]');
    if (rd) {
      const [removida] = c().decisoes.splice(Number(rd.dataset.remDecisao), 1);
      if (c().cores_botoes) delete c().cores_botoes[removida];
      marcarSujo(); renderPainel(); return;
    }
    const rp = t.closest('[data-rem-pessoa]');
    if (rp) {
      const at = c().atribuicao || (c().atribuicao = { tipo: 'usuario' });
      at.usuario_ids = pessoasDe(at).filter((id) => id !== Number(rp.dataset.remPessoa));
      delete at.usuario_id;
      marcarSujo(); atualizarPendentes(); renderPainel(); return;
    }
    if (t.closest('[data-add-var]')) { (c().variaveis = c().variaveis || []).push({ nome: '', valor: '' }); marcarSujo(); renderPainel(); return; }
    const rv = t.closest('[data-rem-var]');
    if (rv) { c().variaveis.splice(Number(rv.dataset.remVar), 1); marcarSujo(); renderPainel(); return; }
    if (t.closest('[data-add-regra]')) { (c().regras = c().regras || []).push({ variavel: '', operador: 'igual', valor: '' }); marcarSujo(); renderPainel(); return; }
    const rr = t.closest('[data-rem-regra]');
    if (rr) { c().regras.splice(Number(rr.dataset.remRegra), 1); marcarSujo(); atualizarPendentes(); agendarCondicaoXml(el); renderPainel(); return; }
    if (t.closest('[data-rotulo-condicao]')) { modeling.updateLabel(el, resumoRegras(c())); renderPainel(); return; }
    if (t.closest('[data-virar-timer]')) {
      const novo = modeler.get('bpmnReplace').replaceElement(el, { type: 'bpmn:IntermediateCatchEvent', eventDefinitionType: 'bpmn:TimerEventDefinition' });
      moverConfig(el.id, novo.id);
      selection.select(novo);
      return;
    }
    const iv = t.closest('[data-inserir-var]');
    if (iv) {
      const alvoId = iv.dataset.alvo;
      const campo = alvoId ? document.getElementById(alvoId) : ultimoCampoTexto;
      const texto = '{{' + iv.dataset.inserirVar + '}}';
      if (campo && painel.contains(campo)) {
        const ini = campo.selectionStart ?? campo.value.length;
        const fim = campo.selectionEnd ?? campo.value.length;
        campo.value = campo.value.slice(0, ini) + texto + campo.value.slice(fim);
        campo.focus();
        campo.setSelectionRange(ini + texto.length, ini + texto.length);
        campo.dispatchEvent(new Event('input', { bubbles: true }));
      } else {
        navigator.clipboard?.writeText(texto);
        toast('Copiado: ' + texto);
      }
    }
  });
  let ultimoCampoTexto = null;
  painel.addEventListener('focusin', (e) => { if (e.target.matches('input[type=text],textarea')) ultimoCampoTexto = e.target; });

  painel.addEventListener('change', (e) => {
    const t = e.target;
    const el = selecionado;
    if (!el) return;
    if (t.matches('[data-padrao]')) {
      const f = t.value ? registro.get(t.value) : null;
      modeling.updateProperties(el, { default: f ? f.businessObject : undefined });
      atualizarPendentes();
      renderPainel();
    }
    if (t.matches('[data-eh-padrao]') && el.source) {
      modeling.updateProperties(el.source, { default: t.checked ? el.businessObject : undefined });
      atualizarPendentes();
      renderPainel();
    }
    if (t.matches('[data-lista][data-rerender]')) renderPainel();
    if (t.matches('[data-add-pessoa]') && t.value) {
      const ce = cfgEditavel(el.id);
      const at = ce.atribuicao || (ce.atribuicao = { tipo: 'usuario' });
      at.usuario_ids = Array.from(new Set([...pessoasDe(at), Number(t.value)]));
      delete at.usuario_id;
      marcarSujo();
      atualizarPendentes();
      renderPainel();
    }
  });
  painel.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.matches('[data-nova-decisao]')) { e.preventDefault(); painel.querySelector('[data-add-decisao]').click(); }
  });

  function resumoRegras(c) {
    const opsTxt = { igual: '=', diferente: '≠', maior: '>', menor: '<', maior_igual: '≥', menor_igual: '≤', contem: 'contém', vazio: 'vazio', nao_vazio: 'preenchido' };
    const regras = (c.regras || []).filter((r) => r.variavel);
    if (regras.length === 1 && (regras[0].operador || 'igual') === 'igual' && /^decis/.test(regras[0].variavel)) return String(regras[0].valor);
    return regras.map((r) => `${r.variavel} ${opsTxt[r.operador || 'igual']}${['vazio', 'nao_vazio'].includes(r.operador) ? '' : ' ' + r.valor}`).join(c.juncao === 'ou' ? ' ou ' : ' e ');
  }

  // guarda um resumo legível da condição no XML (documentação / interoperabilidade)
  let tCond;
  function agendarCondicaoXml(el) {
    if (!el || el.type !== 'bpmn:SequenceFlow') return;
    clearTimeout(tCond);
    tCond = setTimeout(() => {
      const c = cfg(el.id);
      const resumo = resumoRegras(c);
      const origemGw = el.source && /Gateway$/.test(el.source.type);
      if (!resumo) {
        if (el.businessObject.conditionExpression) modeling.updateProperties(el, { conditionExpression: undefined });
        return;
      }
      if (origemGw || el.source) {
        modeling.updateProperties(el, { conditionExpression: moddle.create('bpmn:FormalExpression', { body: resumo }) });
      }
    }, 600);
  }

  function moverConfig(de, para) {
    if (de !== para && config[de]) { config[para] = config[de]; delete config[de]; }
  }
  function trocarTipo(el, tipo) {
    if (el.businessObject.$type === tipo) return;
    const novo = modeler.get('bpmnReplace').replaceElement(el, { type: tipo });
    moverConfig(el.id, novo.id);
    if (CATEGORIAS[tipo] === 'automatica' && !cfg(novo.id).acao) cfgEditavel(novo.id).acao = tipo === 'bpmn:SendTask' ? 'email' : '';
    selection.select(novo);
    atualizarPendentes();
  }

  function abrirFormulario(el) {
    const outras = registro.filter((x) => x.id !== el.id && (cfg(x.id).formulario || []).length)
      .map((x) => ({ nome: nomeEl(x), campos: cfg(x.id).formulario }));
    window.ConstrutorFormulario.abrir({
      titulo: 'Formulário · ' + nomeEl(el),
      campos: cfg(el.id).formulario || [],
      tipos: D.tiposCampo,
      outrasEtapas: outras,
      aoSalvar: (campos) => {
        cfgEditavel(el.id).formulario = campos;
        marcarSujo();
        renderPainel();
        toast('Formulário aplicado. Lembre-se de salvar o fluxo.');
      },
    });
  }

  /* ---------- Eventos do bpmn-js ---------- */
  modeler.on('selection.changed', (e) => {
    const s = e.newSelection;
    let el = s.length === 1 ? s[0] : null;
    if (el && el.type === 'label') el = el.labelTarget;
    selecionado = el;
    renderPainel();
    document.querySelector('[data-mod-dica]')?.classList.add('sumir');
  });
  modeler.on('commandStack.changed', () => {
    marcarSujo();
    clearTimeout(modeler._tPend);
    modeler._tPend = setTimeout(() => {
      atualizarPendentes();
      // mantém o painel coerente (nome alterado direto no canvas, elemento removido...)
      if (selecionado && !registro.get(selecionado.id)) { selecionado = null; renderPainel(); }
      else if (selecionado) {
        const inp = painel.querySelector('[data-nome]');
        if (inp && document.activeElement !== inp) inp.value = selecionado.businessObject.name || '';
        if (!painel.contains(document.activeElement)) renderPainel();
      } else renderPainel();
    }, 120);
  });
  // tarefas novas já nascem como tarefa humana atribuída a quem iniciou (ajustável)
  modeler.on('shape.added', (e) => {
    const el = e.element;
    if (!carregado || !el.businessObject) return;
    const cat = categoria(el);
    if (cat === 'humana' && !cfg(el.id).atribuicao) cfgEditavel(el.id).atribuicao = { tipo: 'iniciador' };
    if (cat === 'automatica' && el.businessObject.$type === 'bpmn:SendTask' && !cfg(el.id).acao) cfgEditavel(el.id).acao = 'email';
  });

  /* ---------- Barra de ações ---------- */
  async function xmlAtual() {
    const { xml } = await modeler.saveXML({ format: true });
    return xml;
  }
  function configLimpa() {
    const ids = new Set(registro.getAll().map((e) => e.id));
    const out = {};
    Object.entries(config).forEach(([k, v]) => { if (ids.has(k) && v && typeof v === 'object' && Object.keys(v).length) out[k] = v; });
    return out;
  }
  async function salvar(botao) {
    carregando(botao, true);
    try {
      const r = await api('api/fluxos.php', { acao: 'salvar', id: D.processo.id, xml: await xmlAtual(), config: configLimpa() });
      versao = { numero: r.versao, status: r.status };
      marcarSujo(false);
      toast(`Rascunho v${r.versao} salvo.` + (r.avisos.length ? ` ${r.avisos.length} pendência(s) antes de publicar.` : ' Pronto para publicar.'));
      return true;
    } catch (err) {
      toast(err.message, 'erro');
      return false;
    } finally {
      carregando(botao, false);
    }
  }
  function mostrarErros(erros) {
    modal({
      titulo: erros.length ? `${erros.length} ponto(s) para corrigir` : 'Fluxo válido',
      corpo: erros.length
        ? `<div class="alerta alerta-alerta" style="margin-bottom:12px">Corrija os itens abaixo antes de publicar. Os elementos com problema estão contornados em vermelho.</div><ul style="margin:0;padding-left:18px;display:grid;gap:6px">${erros.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>`
        : '<div class="alerta alerta-ok" style="margin:0">✓ O fluxo está pronto para ser publicado.</div>',
      botoes: [{ texto: 'Entendi', classe: 'btn-primario' }],
    });
  }

  document.querySelector('.mod-barra').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-mod]');
    if (!b) return;
    const acao = b.dataset.mod;
    const menu = document.querySelector('[data-mod-menu]');
    if (acao !== 'arquivo') menu.hidden = true;

    if (acao === 'arquivo') { menu.hidden = !menu.hidden; return; }
    if (acao === 'salvar') { salvar(b); return; }
    if (acao === 'validar') {
      carregando(b, true);
      try {
        const r = await api('api/fluxos.php', { acao: 'validar', xml: await xmlAtual(), config: configLimpa() });
        atualizarPendentes();
        mostrarErros(r.erros);
      } catch (err) { toast(err.message, 'erro'); } finally { carregando(b, false); }
      return;
    }
    if (acao === 'publicar') {
      const r0 = await api('api/fluxos.php', { acao: 'validar', xml: await xmlAtual(), config: configLimpa() }).catch((er) => { toast(er.message, 'erro'); return null; });
      if (!r0) return;
      if (r0.erros.length) { atualizarPendentes(); mostrarErros(r0.erros); return; }
      if (!(await confirmar('A nova versão passa a valer para os próximos processos iniciados. Os que já estão em andamento continuam na versão antiga.', { titulo: 'Publicar fluxo', texto: 'Publicar agora' }))) return;
      carregando(b, true);
      try {
        const r = await api('api/fluxos.php', { acao: 'publicar', id: D.processo.id, xml: await xmlAtual(), config: configLimpa() });
        versao = { numero: r.versao, status: 'publicado' };
        temPublicada = true;
        marcarSujo(false);
        toast(`Versão ${r.versao} publicada! Já pode ser iniciada pelos usuários.`);
      } catch (err) { toast(err.message, 'erro'); } finally { carregando(b, false); }
      return;
    }
    if (acao === 'propriedades') {
      window.FluxoInfo.abrir(D.processo, (r, p) => {
        Object.assign(D.processo, p);
        document.querySelector('[data-mod-nome]').textContent = p.nome;
        document.querySelector('[data-mod-cor]').style.background = p.cor;
        const ic = (lerJSON('icones') || {})[p.icone];
        if (ic) document.querySelector('[data-mod-cor]').innerHTML = ic;
        toast('Propriedades salvas.');
        if (!selecionado) renderPainel();
      });
      return;
    }
    if (acao === 'exportar-bpmn') { baixar(await xmlAtual(), slugNome() + '.bpmn', 'application/xml'); return; }
    if (acao === 'exportar-svg') { const { svg } = await modeler.saveSVG(); baixar(svg, slugNome() + '.svg', 'image/svg+xml'); return; }
    if (acao === 'importar') { document.querySelector('[data-mod-arquivo]').click(); return; }
    if (acao === 'descartar') {
      if (!(await confirmar('Descartar todas as alterações do rascunho e voltar para a versão publicada?', { titulo: 'Descartar rascunho', texto: 'Descartar', perigo: true }))) return;
      try { await api('api/fluxos.php', { acao: 'descartar', id: D.processo.id }); sujo = false; location.reload(); } catch (err) { toast(err.message, 'erro'); }
    }
  });
  document.addEventListener('click', (e) => {
    const menu = document.querySelector('[data-mod-menu]');
    if (menu && !menu.hidden && !e.target.closest('.mod-menu')) menu.hidden = true;
  });

  document.querySelector('[data-mod-arquivo]').addEventListener('change', async (e) => {
    const f = e.target.files[0];
    e.target.value = '';
    if (!f) return;
    try {
      await modeler.importXML(await f.text());
      canvas.zoom('fit-viewport', 'auto');
      selecionado = null;
      marcarSujo();
      atualizarPendentes();
      renderPainel();
      toast('Diagrama importado. Configure os elementos e salve.');
    } catch (err) {
      toast('Arquivo BPMN inválido: ' + err.message, 'erro');
    }
  });

  function slugNome() { return D.processo.nome.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-zA-Z0-9]+/g, '-').toLowerCase(); }
  function baixar(conteudo, nome, tipo) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([conteudo], { type: tipo }));
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
  }

  container.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {
    const z = canvas.zoom();
    if (b.dataset.zoom === 'mais') canvas.zoom(z * 1.2);
    else if (b.dataset.zoom === 'menos') canvas.zoom(z / 1.2);
    else canvas.zoom('fit-viewport', 'auto');
  }));

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      salvar(document.querySelector('[data-mod=salvar]'));
    }
  });
  window.addEventListener('beforeunload', (e) => { if (sujo) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- Início ---------- */
  let carregado = false;
  (async () => {
    try {
      await modeler.importXML(D.xml);
    } catch (err) {
      toast('Não foi possível abrir o diagrama: ' + err.message, 'erro');
    }
    canvas.zoom('fit-viewport', 'auto');
    if (canvas.zoom() > 1.2) canvas.zoom(1.2, 'auto');
    // "caminho padrão" salvo só na configuração vira o atributo default do BPMN
    const mudados = [];
    Object.keys(config).forEach((id) => {
      const f = registro.get(id);
      if (!config[id].padrao || !f || !f.source) return;
      if (!f.source.businessObject.default) {
        f.source.businessObject.default = f.businessObject;
        mudados.push(f, f.source);
      }
      delete config[id].padrao;
    });
    if (mudados.length) modeler.get('eventBus').fire('elements.changed', { elements: mudados });
    carregado = true;
    sujo = false;
    renderStatus();
    atualizarPendentes();
    renderPainel();
    setTimeout(() => document.querySelector('[data-mod-dica]')?.classList.add('sumir'), 9000);
  })();
})();
