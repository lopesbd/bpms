<?php
require dirname(__DIR__) . '/app/nucleo.php';
[$u] = api_inicio();

$arq = $_FILES['arquivo'] ?? null;
if (!$arq || !is_uploaded_file($arq['tmp_name'] ?? '') || ($arq['error'] ?? 1) !== UPLOAD_ERR_OK) {
    $codigo = $arq['error'] ?? null;
    falhar(in_array($codigo, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Arquivo maior que o permitido pelo servidor.' : 'Nenhum arquivo recebido.');
}
$maxMb = max(1, (int) cfg_banco('tamanho_max_mb', '10'));
if ($arq['size'] > $maxMb * 1024 * 1024) {
    falhar("O arquivo passa de $maxMb MB.");
}
$nome = texto_limpo(basename(str_replace('\\', '/', (string) $arq['name'])), 200);
$ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
$permitidas = array_filter(array_map(fn($x) => strtolower(trim($x, " .\t")), explode(',', cfg_banco('formatos_anexo', 'pdf'))));
if ($ext === '' || !in_array($ext, $permitidas, true)) {
    falhar('Formato não permitido. Aceitos: ' . implode(', ', $permitidas) . '.');
}
$bloqueadas = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'svg'];
if (in_array($ext, $bloqueadas, true)) {
    falhar('Este tipo de arquivo não pode ser anexado por segurança.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($arq['tmp_name']) ?: 'application/octet-stream';

$pasta = STORAGE_DIR . '/anexos/' . date('Y/m');
if (!is_dir($pasta) && !mkdir($pasta, 0750, true) && !is_dir($pasta)) {
    falhar('Não foi possível gravar o arquivo no servidor.', 500);
}
$interno = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
if (!move_uploaded_file($arq['tmp_name'], STORAGE_DIR . '/anexos/' . $interno)) {
    falhar('Falha ao salvar o arquivo.', 500);
}
$id = inserir('anexos', [
    'nome_original' => $nome,
    'arquivo' => $interno,
    'mime' => mb_substr($mime, 0, 100),
    'tamanho' => (int) $arq['size'],
    'sha256' => hash_file('sha256', STORAGE_DIR . '/anexos/' . $interno),
    'enviado_por' => $u['id'],
]);
responder(['ok' => true, 'anexo' => ['id' => $id, 'nome' => $nome]]);
