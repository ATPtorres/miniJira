<?php
/**
 * Sistema Universitario
 * app/config/database.php
 *
 * Conexion PDO unica (singleton) a MySQL/MariaDB.
 * Requiere que app/bootstrap.php haya definido config().
 */

declare(strict_types=1);

/**
 * Devuelve la conexion PDO (una sola por peticion).
 *
 * @throws PDOException si no puede conectar (el error global lo oculta al usuario)
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $c = config('db');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'],
            (int) $c['port'],
            $c['database'],
            $c['charset']
        );

        $pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // consultas preparadas reales (anti SQLi)
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    }

    return $pdo;
}