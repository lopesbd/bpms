<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db.host', 'localhost'),
            (int) config('db.porta', 3306),
            config('db.nome')
        );
        $pdo = new PDO($dsn, (string) config('db.usuario'), (string) config('db.senha'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // MariaDB da Hostinger não fica no fuso de Brasília: alinha com o PHP
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function linha(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function linhas(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function valor(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function inserir(string $tabela, array $dados): int
{
    $cols = array_keys($dados);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $tabela,
        implode(',', $cols),
        implode(',', array_fill(0, count($cols), '?'))
    );
    q($sql, array_values($dados));
    return (int) db()->lastInsertId();
}

function transacao(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function cfg_banco(string $chave, string $padrao = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (linhas('SELECT chave, valor FROM configuracoes') as $r) {
            $cache[$r['chave']] = (string) $r['valor'];
        }
    }
    return $cache[$chave] ?? $padrao;
}

function salvar_cfg_banco(string $chave, string $valor): void
{
    q('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$chave, $valor]);
}
