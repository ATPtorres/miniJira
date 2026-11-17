<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = config('db', []);
    $host = getenv('SISTEMA_DB_HOST') ?: ($c['host'] ?? '127.0.0.1');
    $port = (int) (getenv('SISTEMA_DB_PORT') ?: ($c['port'] ?? 3306));
    $name = getenv('SISTEMA_DB_DATABASE') ?: ($c['database'] ?? 'sistema_universitario');
    $user = getenv('SISTEMA_DB_USERNAME') ?: ($c['username'] ?? 'root');
    $pass = getenv('SISTEMA_DB_PASSWORD'); if ($pass === false) $pass = $c['password'] ?? '';
    $charset = getenv('SISTEMA_DB_CHARSET') ?: ($c['charset'] ?? 'utf8mb4');
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"];
    $ca = getenv('SISTEMA_DB_SSL_CA');
    if ($ca) { $options[PDO::MYSQL_ATTR_SSL_CA] = $ca; $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true; }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
    $pdo = new PDO($dsn, $user, $pass, $options); $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}
