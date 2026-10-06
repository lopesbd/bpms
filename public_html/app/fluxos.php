<?php
declare(strict_types=1);

/* Regras de gravação/publicação das definições de processo. */

function xml_inicial(string $nome): string
{
    $n = htmlspecialchars($nome, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" xmlns:di="http://www.omg.org/spec/DD/20100524/DI" id="Definicoes_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Processo_1" name="$n" isExecutable="true">
    <bpmn:startEvent id="Inicio" name="Solicitação recebida">
      <bpmn:outgoing>Fluxo_1</bpmn:outgoing>
    </bpmn:startEvent>
    <bpmn:userTask id="Tarefa_1" name="Analisar solicitação">
      <bpmn:incoming>Fluxo_1</bpmn:incoming>
      <bpmn:outgoing>Fluxo_2</bpmn:outgoing>
    </bpmn:userTask>
    <bpmn:endEvent id="Fim" name="Concluído">
      <bpmn:incoming>Fluxo_2</bpmn:incoming>
    </bpmn:endEvent>
    <bpmn:sequenceFlow id="Fluxo_1" sourceRef="Inicio" targetRef="Tarefa_1" />
    <bpmn:sequenceFlow id="Fluxo_2" sourceRef="Tarefa_1" targetRef="Fim" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="Diagrama_1">
    <bpmndi:BPMNPlane id="Plano_1" bpmnElement="Processo_1">
      <bpmndi:BPMNShape id="Inicio_di" bpmnElement="Inicio"><dc:Bounds x="152" y="142" width="36" height="36" /><bpmndi:BPMNLabel><dc:Bounds x="128" y="185" width="85" height="27" /></bpmndi:BPMNLabel></bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Tarefa_1_di" bpmnElement="Tarefa_1"><dc:Bounds x="250" y="120" width="120" height="80" /></bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Fim_di" bpmnElement="Fim"><dc:Bounds x="442" y="142" width="36" height="36" /><bpmndi:BPMNLabel><dc:Bounds x="435" y="185" width="51" height="14" /></bpmndi:BPMNLabel></bpmndi:BPMNShape>
      <bpmndi:BPMNEdge id="Fluxo_1_di" bpmnElement="Fluxo_1"><di:waypoint x="188" y="160" /><di:waypoint x="250" y="160" /></bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Fluxo_2_di" bpmnElement="Fluxo_2"><di:waypoint x="370" y="160" /><di:waypoint x="442" y="160" /></bpmndi:BPMNEdge>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>
XML;
}

function config_inicial(): array
{
    return [
        'Inicio' => ['formulario' => [
            ['id' => 'c1', 'tipo' => 'texto', 'rotulo' => 'Assunto', 'variavel' => 'assunto', 'obrigatorio' => true],
            ['id' => 'c2', 'tipo' => 'textarea', 'rotulo' => 'Descrição', 'variavel' => 'descricao'],
        ]],
        'Tarefa_1' => ['atribuicao' => ['tipo' => 'iniciador'], 'decisoes' => ['Aprovar', 'Reprovar'], 'decisao_variavel' => 'decisao'],
    ];
}

function sigla_valida(string $s): string
{
    $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s) ?? '');
    return substr($s, 0, 8);
}

function versao_rascunho(int $processoId): ?array
{
    return linha("SELECT * FROM processo_versoes WHERE processo_id = ? AND status = 'rascunho' ORDER BY numero DESC LIMIT 1", [$processoId]);
}

function versao_para_editar(int $processoId): array
{
    $r = versao_rascunho($processoId);
    if ($r) {
        return $r;
    }
    $ultima = linha('SELECT * FROM processo_versoes WHERE processo_id = ? ORDER BY numero DESC LIMIT 1', [$processoId]);
    if (!$ultima) {
        throw new ErroNegocio('Fluxo sem versões.');
    }
    return $ultima;
}

/** Sanitiza o JSON de configuração vindo do modelador. */
function limpar_config($config, string $xml): array
{
    if (!is_array($config)) {
        return [];
    }
    $modelo = modelo_ler($xml);
    $ids = array_merge(array_keys($modelo['elementos']), array_keys($modelo['fluxos']));
    $limpa = [];
    foreach ($config as $id => $c) {
        if (in_array((string) $id, $ids, true) && is_array($c) && $c) {
            $limpa[(string) $id] = $c;
        }
    }
    return $limpa;
}

function salvar_rascunho(int $processoId, string $xml, array $config, array $u): array
{
    if (strlen($xml) > 3_000_000) {
        throw new ErroNegocio('O diagrama é grande demais.');
    }
    $config = limpar_config($config, $xml);
    return transacao(function () use ($processoId, $xml, $config, $u) {
        linha('SELECT id FROM processos WHERE id = ? FOR UPDATE', [$processoId]);
        $r = versao_rascunho($processoId);
        if ($r) {
            q('UPDATE processo_versoes SET xml = ?, config = ? WHERE id = ?', [$xml, json_enc($config), $r['id']]);
            return linha('SELECT * FROM processo_versoes WHERE id = ?', [$r['id']]);
        }
        $num = (int) valor('SELECT COALESCE(MAX(numero), 0) + 1 FROM processo_versoes WHERE processo_id = ?', [$processoId]);
        $id = inserir('processo_versoes', [
            'processo_id' => $processoId,
            'numero' => $num,
            'xml' => $xml,
            'config' => json_enc($config),
            'status' => 'rascunho',
            'criado_por' => $u['id'],
        ]);
        return linha('SELECT * FROM processo_versoes WHERE id = ?', [$id]);
    });
}

function publicar_rascunho(int $processoId, array $u): array
{
    return transacao(function () use ($processoId, $u) {
        linha('SELECT id FROM processos WHERE id = ? FOR UPDATE', [$processoId]);
        $r = versao_rascunho($processoId);
        if (!$r) {
            throw new ErroNegocio('Não há alterações em rascunho para publicar.');
        }
        $erros = modelo_validar($r['xml'], json_decode_arr($r['config']));
        if ($erros) {
            throw new ErroNegocio("Corrija antes de publicar:\n• " . implode("\n• ", $erros));
        }
        q("UPDATE processo_versoes SET status = 'arquivado' WHERE processo_id = ? AND status = 'publicado'", [$processoId]);
        q("UPDATE processo_versoes SET status = 'publicado', publicado_em = NOW(), publicado_por = ? WHERE id = ?", [$u['id'], $r['id']]);
        q('UPDATE processos SET versao_publicada_id = ? WHERE id = ?', [$r['id'], $processoId]);
        return linha('SELECT * FROM processo_versoes WHERE id = ?', [$r['id']]);
    });
}
