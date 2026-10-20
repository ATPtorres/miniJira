<?php
/**
 * Migra correos existentes a AES-256-GCM y elimina el valor en texto plano.
 * Ejecutar una vez despues de 20260901_sensitive_data_and_roles.sql:
 *   C:\xampp\php\php.exe database\migrate_sensitive_data.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$rows = db()->query(
    "SELECT id, email FROM users WHERE email IS NOT NULL AND email <> '' AND email_encrypted IS NULL"
)->fetchAll();

$update = db()->prepare(
    'UPDATE users SET email = NULL, email_encrypted = ?, email_lookup_hash = ? WHERE id = ?'
);

foreach ($rows as $row) {
    $email = normalize_email((string) $row['email']);
    $update->execute([
        EncryptionService::encrypt($email),
        EncryptionService::emailLookupHash($email),
        (int) $row['id'],
    ]);
}

db()->exec('ALTER TABLE users MODIFY email_encrypted TEXT NOT NULL');
db()->exec('ALTER TABLE users MODIFY email_lookup_hash CHAR(64) NOT NULL');

fwrite(STDOUT, 'Migracion completada: ' . count($rows) . " correo(s) cifrado(s).\n");
