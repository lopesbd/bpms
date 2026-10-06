<?php
/*
 * Processa temporizadores vencidos dos fluxos.
 * Hostinger › Avançado › Cron Jobs, a cada 5 minutos:
 *   /usr/bin/php /home/SEU-USUARIO/domains/SEU-DOMINIO/cron/processar.php
 * (Sem cron, os temporizadores ainda andam quando alguém navega no sistema, a cada minuto.)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/public_html/app/nucleo.php';

$n = processar_timers(200);
salvar_cfg_banco('timers_ultimo', (string) time());
echo date('Y-m-d H:i:s') . " — $n temporizador(es) processado(s)\n";
