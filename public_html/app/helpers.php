<?php
declare(strict_types=1);

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function agora(): string
{
    return date('Y-m-d H:i:s');
}

function url(string $caminho = ''): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    return $base . '/' . ltrim($caminho, '/');
}

function redirecionar(string $caminho): void
{
    header('Location: ' . url($caminho));
    exit;
}

function data_br(?string $dt, bool $hora = true): string
{
    if (!$dt) {
        return '—';
    }
    $t = strtotime($dt);
    return $hora ? date('d/m/Y H:i', $t) : date('d/m/Y', $t);
}

function tempo_relativo(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $dif = time() - strtotime($dt);
    $futuro = $dif < 0;
    $dif = abs($dif);
    if ($dif < 60) {
        $txt = 'instantes';
    } elseif ($dif < 3600) {
        $n = intdiv($dif, 60);
        $txt = $n . ' min';
    } elseif ($dif < 86400) {
        $n = intdiv($dif, 3600);
        $txt = $n . ' h';
    } elseif ($dif < 86400 * 30) {
        $n = intdiv($dif, 86400);
        $txt = $n . ($n === 1 ? ' dia' : ' dias');
    } else {
        return data_br($dt, false);
    }
    return $futuro ? 'em ' . $txt : 'há ' . $txt;
}

function duracao(int $segundos): string
{
    if ($segundos < 3600) {
        return max(1, intdiv($segundos, 60)) . ' min';
    }
    if ($segundos < 86400) {
        return number_format($segundos / 3600, 1, ',', '') . ' h';
    }
    return number_format($segundos / 86400, 1, ',', '') . ' dias';
}

function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $ini = mb_substr($partes[0] ?? '?', 0, 1);
    if (count($partes) > 1) {
        $ini .= mb_substr(end($partes), 0, 1);
    }
    return mb_strtoupper($ini);
}

function cor_avatar(string $semente): string
{
    $cores = ['#00995d', '#0b7a75', '#7db61c', '#49479d', '#c2410c', '#0369a1', '#a21caf', '#b45309'];
    return $cores[abs(crc32($semente)) % count($cores)];
}

function avatar(string $nome, string $classe = ''): string
{
    return '<span class="avatar ' . e($classe) . '" style="background:' . e(cor_avatar($nome)) . '" title="' . e($nome) . '">' . e(iniciais($nome)) . '</span>';
}

function json_decode_arr(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

function json_enc($v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

/** Dados para JS sem script inline (CSP): vão num <script type="application/json">. */
function dados_js(string $id, $dados): string
{
    $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<script type="application/json" id="' . e($id) . '">' . $json . '</script>';
}

function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function texto_limpo($v, int $max = 500): string
{
    $s = trim((string) $v);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr($s, 0, $max);
}

/** Substitui {{variavel}} pelos valores do processo. */
function modelo_texto(string $modelo, array $vars): string
{
    return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/u', function ($m) use ($vars) {
        $v = $vars[$m[1]] ?? '';
        if (is_array($v)) {
            $v = isset($v['nome']) ? $v['nome'] : implode(', ', array_map('strval', $v));
        }
        if (is_bool($v)) {
            $v = $v ? 'Sim' : 'Não';
        }
        if (is_float($v)) {
            // inteiros são gravados como int; float aqui é valor monetário ou decimal
            $v = number_format($v, 2, ',', '.');
        }
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $v = date('d/m/Y', strtotime($v));
        }
        return (string) $v;
    }, $modelo) ?? $modelo;
}

function status_instancia(string $s): array
{
    return [
        'andamento' => ['Em andamento', 'info'],
        'concluida' => ['Concluído', 'ok'],
        'cancelada' => ['Cancelado', 'neutro'],
        'erro' => ['Com erro', 'erro'],
    ][$s] ?? [$s, 'neutro'];
}

function badge(string $texto, string $tipo): string
{
    return '<span class="badge badge-' . e($tipo) . '">' . e($texto) . '</span>';
}

function notificar(int $usuarioId, string $titulo, string $texto = '', string $link = ''): void
{
    inserir('notificacoes', [
        'usuario_id' => $usuarioId,
        'titulo' => mb_substr($titulo, 0, 160),
        'texto' => mb_substr($texto, 0, 500),
        'link' => $link,
    ]);
}

function icone(string $nome, int $tam = 20): string
{
    static $svg = [
        'tarefas' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'iniciar' => '<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
        'processos' => '<path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/>',
        'painel' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'fluxo' => '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="15" width="6" height="6" rx="1"/><path d="M6 9v3a3 3 0 0 0 3 3h6"/><path d="M15 12l3 3-3 3"/>',
        'config' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'sino' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'busca' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
        'mais' => '<path d="M12 5v14M5 12h14"/>',
        'relogio' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'usuario' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'grupo' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'sair' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'clipe' => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'compra' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'documento' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'ferias' => '<path d="M17 18a5 5 0 0 0-10 0"/><path d="M12 2v7M4.22 10.22l1.42 1.42M1 18h2M21 18h2M18.36 11.64l1.42-1.42M23 22H1M8 6l4-4 4 4"/>',
        'dinheiro' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'pessoa' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'chamado' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'contrato' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'caminhao' => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'escudo' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'seta' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'lixo' => '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'editar' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'copiar' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'olho' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'alerta' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'menu' => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'raio' => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
    ];
    $d = $svg[$nome] ?? $svg['fluxo'];
    return '<svg class="ic" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

function icones_processo(): array
{
    return ['fluxo', 'compra', 'documento', 'ferias', 'dinheiro', 'pessoa', 'chamado', 'contrato', 'caminhao', 'escudo'];
}

/** Estilo de um botão com a cor escolhida no modelador (texto claro ou escuro conforme o contraste). */
function cor_botao($cor): ?string
{
    if (!is_string($cor) || !preg_match('/^#([0-9a-f]{6})$/i', $cor, $m)) {
        return null;
    }
    [$r, $g, $b] = array_map('hexdec', str_split($m[1], 2));
    $luz = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    $texto = $luz > 0.62 ? '#142b30' : '#ffffff';
    return "background:$cor;border-color:$cor;color:$texto";
}
