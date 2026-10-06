<?php
require dirname(__DIR__) . '/app/nucleo.php';
[$u, $in] = api_inicio(true);

switch ($in['acao'] ?? '') {
    case 'salvar':
        $remetente = trim((string) ($in['email_remetente'] ?? ''));
        if ($remetente !== '' && !filter_var($remetente, FILTER_VALIDATE_EMAIL)) {
            falhar('E-mail remetente inválido.');
        }
        $formatos = implode(',', array_filter(array_map(
            fn($x) => preg_replace('/[^a-z0-9]/', '', strtolower($x)),
            explode(',', (string) ($in['formatos_anexo'] ?? ''))
        )));
        salvar_cfg_banco('empresa', texto_limpo($in['empresa'] ?? '', 100) ?: 'Minha Empresa');
        salvar_cfg_banco('email_remetente', $remetente);
        salvar_cfg_banco('email_tarefas', ($in['email_tarefas'] ?? '0') === '1' ? '1' : '0');
        salvar_cfg_banco('formatos_anexo', $formatos ?: 'pdf');
        $hora = fn($v, $p) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $v) ? (string) $v : $p;
        $ini = $hora($in['expediente_inicio'] ?? '', '08:00');
        $fim = $hora($in['expediente_fim'] ?? '', '18:00');
        if ($fim <= $ini) {
            falhar('O fim do expediente precisa ser depois do início.');
        }
        preg_match_all('#\b\d{2}/\d{2}(?:/\d{4})?\b#', (string) ($in['feriados'] ?? ''), $datas);
        salvar_cfg_banco('expediente_inicio', $ini);
        salvar_cfg_banco('expediente_fim', $fim);
        salvar_cfg_banco('feriados', implode(' ', array_unique($datas[0])));
        salvar_cfg_banco('tamanho_max_mb', (string) max(1, min(100, (int) ($in['tamanho_max_mb'] ?? 10))));
        responder(['ok' => true]);
        // no break
    case 'testar_email':
        enviar_email($u['email'], 'Teste de e-mail do Fluxa BPMS', "Olá {$u['nome']},\n\nSe você recebeu esta mensagem, o envio de e-mails do sistema está funcionando.");
        responder(['ok' => true, 'mensagem' => config('email_simulado') ? 'Modo simulado: o e-mail foi gravado em storage/emails.log.' : 'E-mail enviado para ' . $u['email'] . '. Confira a caixa de entrada (e o spam).']);
        // no break
    default:
        falhar('Ação desconhecida.');
}
