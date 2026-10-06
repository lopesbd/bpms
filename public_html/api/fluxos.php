<?php
require dirname(__DIR__) . '/app/nucleo.php';
[$u, $in] = api_inicio(true);

$id = (int) ($in['id'] ?? 0);

function dados_processo(array $in): array
{
    $nome = texto_limpo($in['nome'] ?? '', 120);
    if ($nome === '') {
        throw new ErroNegocio('Informe o nome do processo.');
    }
    $sigla = sigla_valida((string) ($in['sigla'] ?? '')) ?: sigla_valida(mb_substr(preg_replace('/[^A-Za-z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: 'PRC'), 0, 3));
    $cor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($in['cor'] ?? '')) ? $in['cor'] : '#00995d';
    $icone = in_array($in['icone'] ?? '', icones_processo(), true) ? $in['icone'] : 'fluxo';
    return [
        'nome' => $nome,
        'sigla' => $sigla ?: 'PRC',
        'descricao' => texto_limpo($in['descricao'] ?? '', 500) ?: null,
        'categoria' => texto_limpo($in['categoria'] ?? '', 80) ?: null,
        'cor' => $cor,
        'icone' => $icone,
        'titulo_modelo' => texto_limpo($in['titulo_modelo'] ?? '', 200) ?: null,
    ];
}

function salvar_iniciadores(int $id, $grupos): void
{
    q('DELETE FROM processo_iniciadores WHERE processo_id = ?', [$id]);
    foreach (array_unique(array_map('intval', is_array($grupos) ? $grupos : [])) as $g) {
        if ($g > 0 && valor('SELECT 1 FROM grupos WHERE id = ?', [$g])) {
            inserir('processo_iniciadores', ['processo_id' => $id, 'grupo_id' => $g]);
        }
    }
}

switch ($in['acao'] ?? '') {
    case 'criar':
        $d = dados_processo($in);
        $novo = transacao(function () use ($d, $u, $in) {
            $pid = inserir('processos', $d + ['criado_por' => $u['id']]);
            inserir('processo_versoes', [
                'processo_id' => $pid, 'numero' => 1, 'xml' => xml_inicial($d['nome']),
                'config' => json_enc(config_inicial()), 'status' => 'rascunho', 'criado_por' => $u['id'],
            ]);
            salvar_iniciadores($pid, $in['iniciadores'] ?? []);
            return $pid;
        });
        responder(['ok' => true, 'id' => $novo]);
        // no break

    case 'info':
        if (!valor('SELECT 1 FROM processos WHERE id = ?', [$id])) {
            falhar('Processo não encontrado.', 404);
        }
        $d = dados_processo($in);
        $d['ativo'] = !empty($in['ativo']) ? 1 : 0;
        transacao(function () use ($d, $id, $in) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($d)));
            q("UPDATE processos SET $sets WHERE id = ?", array_merge(array_values($d), [$id]));
            salvar_iniciadores($id, $in['iniciadores'] ?? []);
        });
        responder(['ok' => true]);
        // no break

    case 'ativo':
        q('UPDATE processos SET ativo = ? WHERE id = ?', [!empty($in['ativo']) ? 1 : 0, $id]);
        responder(['ok' => true]);
        // no break

    case 'salvar':
        if (!valor('SELECT 1 FROM processos WHERE id = ?', [$id])) {
            falhar('Processo não encontrado.', 404);
        }
        $v = salvar_rascunho($id, (string) ($in['xml'] ?? ''), is_array($in['config'] ?? null) ? $in['config'] : [], $u);
        $erros = modelo_validar($v['xml'], json_decode_arr($v['config']));
        responder(['ok' => true, 'versao' => (int) $v['numero'], 'status' => $v['status'], 'avisos' => $erros]);
        // no break

    case 'validar':
        $erros = modelo_validar((string) ($in['xml'] ?? ''), is_array($in['config'] ?? null) ? $in['config'] : []);
        responder(['ok' => true, 'erros' => $erros]);
        // no break

    case 'publicar':
        if (isset($in['xml'])) {
            salvar_rascunho($id, (string) $in['xml'], is_array($in['config'] ?? null) ? $in['config'] : [], $u);
        }
        $v = publicar_rascunho($id, $u);
        responder(['ok' => true, 'versao' => (int) $v['numero']]);
        // no break

    case 'descartar':
        $r = versao_rascunho($id);
        $temPublicada = valor("SELECT 1 FROM processo_versoes WHERE processo_id = ? AND status <> 'rascunho'", [$id]);
        if (!$r || !$temPublicada) {
            falhar('Não há rascunho para descartar (a primeira versão não pode ser descartada).');
        }
        q('DELETE FROM processo_versoes WHERE id = ?', [$r['id']]);
        responder(['ok' => true]);
        // no break

    case 'duplicar':
        $p = linha('SELECT * FROM processos WHERE id = ?', [$id]);
        if (!$p) {
            falhar('Processo não encontrado.', 404);
        }
        $v = versao_para_editar($id);
        $novo = transacao(function () use ($p, $v, $u, $id) {
            $pid = inserir('processos', [
                'nome' => mb_substr($p['nome'] . ' (cópia)', 0, 120), 'sigla' => $p['sigla'], 'descricao' => $p['descricao'],
                'categoria' => $p['categoria'], 'cor' => $p['cor'], 'icone' => $p['icone'], 'titulo_modelo' => $p['titulo_modelo'],
                'criado_por' => $u['id'],
            ]);
            inserir('processo_versoes', ['processo_id' => $pid, 'numero' => 1, 'xml' => $v['xml'], 'config' => $v['config'], 'status' => 'rascunho', 'criado_por' => $u['id']]);
            q('INSERT INTO processo_iniciadores (processo_id, grupo_id) SELECT ?, grupo_id FROM processo_iniciadores WHERE processo_id = ?', [$pid, $id]);
            return $pid;
        });
        responder(['ok' => true, 'id' => $novo]);
        // no break

    case 'excluir':
        if (valor('SELECT 1 FROM instancias WHERE processo_id = ? LIMIT 1', [$id])) {
            falhar('Este processo já tem execuções e não pode ser excluído. Desative-o para que ninguém mais o inicie.');
        }
        transacao(function () use ($id) {
            q('UPDATE processos SET versao_publicada_id = NULL WHERE id = ?', [$id]);
            q('DELETE FROM processos WHERE id = ?', [$id]);
        });
        responder(['ok' => true]);
        // no break

    default:
        falhar('Ação desconhecida.');
}
