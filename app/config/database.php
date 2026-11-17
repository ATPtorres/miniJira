<?php
declare(strict_types=1);

/**
 * PostgreSQL connection for Render and local development.
 * Render's DATABASE_URL is supported; explicit SISTEMA_DB_* variables
 * take precedence to keep credentials configurable without code changes.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $url = getenv('DATABASE_URL') ?: '';
    $parts = $url !== '' ? parse_url($url) : false;
    $host = getenv('SISTEMA_DB_HOST') ?: ($parts['host'] ?? '127.0.0.1');
    $port = (int) (getenv('SISTEMA_DB_PORT') ?: ($parts['port'] ?? 5432));
    $name = getenv('SISTEMA_DB_DATABASE') ?: (isset($parts['path']) ? ltrim($parts['path'], '/') : 'sistema_universitario');
    $user = getenv('SISTEMA_DB_USERNAME') ?: ($parts['user'] ?? 'postgres');
    $pass = getenv('SISTEMA_DB_PASSWORD') ?: (isset($parts['pass']) ? rawurldecode($parts['pass']) : '');
    $sslmode = getenv('SISTEMA_DB_SSLMODE') ?: (getenv('RENDER') ? 'require' : 'prefer');

    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $host, $port, $name, $sslmode);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec("SET TIME ZONE 'America/La_Paz'");
    return $pdo;
}
