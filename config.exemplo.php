<?php
/*
 * Copie este arquivo para "config.php" na pasta ACIMA do public_html
 * (na Hostinger: ~/domains/SEU-DOMINIO/config.php) e preencha os dados.
 * Assim a senha do banco nunca fica acessível pela web.
 */
return [
    'db' => [
        'host' => 'localhost',
        'porta' => 3306,
        'nome' => 'u000000000_bpms',
        'usuario' => 'u000000000_bpms',
        'senha' => 'TROQUE-AQUI',
    ],
    'fuso' => 'America/Sao_Paulo',
    // endereço público, usado nos links dos e-mails
    'url_publica' => 'https://seudominio.com.br',
    // pasta dos anexos (fora do public_html). Padrão: ../storage
    // 'storage' => __DIR__ . '/storage',
    'sessao_minutos' => 120,
    // true grava os e-mails em storage/emails.log em vez de enviar (útil em testes)
    'email_simulado' => false,
    'debug' => false,
];
