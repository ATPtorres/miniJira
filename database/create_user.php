<?php
/**
 * Sistema Universitario
 * database/create_user.php
 *
 * Script CLI SEGURO para crear usuarios de desarrollo (admin, docente,
 * estudiante). Los hashes se generan en tiempo de ejecucion con
 * password_hash() usando Argon2id o bcrypt: NUNCA se escriben en SQL.
 *
 * Uso (desde la raiz del proyecto):
 *   C:\xampp\php\php.exe database\create_user.php <rol> <nombre> <apellido> <email> <password>
 *
 * Ejemplo:
 *   C:\xampp\php\php.exe database\create_user.php admin Administrador Admin admin@sistema.local "Clave.2026!"
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.' . PHP_EOL);
}

$usage = "Uso: php database/create_user.php <rol> <nombre> <apellido> <email> <password> [update]\n"
       . "Roles validos: admin | docente | estudiante\n"
       . "Opcion 'update': actualiza la contrasena (y rol) de un usuario existente.\n";

$args = array_slice($argv, 1);

if (count($args) < 5 || count($args) > 6) {
    fwrite(STDOUT, $usage);
    exit(1);
}

[$roleCode, $firstName, $lastName, $email, $password] = $args;
$updateMode = count($args) === 6 && strtolower($args[5]) === 'update';

$roleCode  = strtolower(trim($roleCode));
$firstName = trim($firstName);
$lastName  = trim($lastName);
$email     = normalize_email($email);

/* ------------------ validaciones ------------------ */

$problems = [];

if (!in_array($roleCode, ['admin', 'docente', 'estudiante'], true)) {
    $problems[] = 'Rol invalido: ' . $roleCode;
}
if (validate_required($firstName, 'nombre') !== '' || mb_strlen($firstName) < 2) {
    $problems[] = 'Nombre invalido (minimo 2 caracteres).';
}
if (validate_required($lastName, 'apellido') !== '' || mb_strlen($lastName) < 2) {
    $problems[] = 'Apellido invalido (minimo 2 caracteres).';
}
if (validate_email_format($email) !== '') {
    $problems[] = 'Correo electronico invalido.';
}
$existing = User::findByEmail($email);
if ($existing !== null && !$updateMode) {
    $problems[] = 'El correo ' . $email . ' ya esta registrado.';
}
$pwError = validate_password_strength($password);
if ($pwError !== '') {
    $problems[] = $pwError;
}

if ($problems !== []) {
    foreach ($problems as $p) {
        fwrite(STDOUT, "Error: " . $p . PHP_EOL);
    }
    exit(1);
}

/* ------------------ creacion/actualizacion segura ------------------ */

$hash = password_hash($password, password_algo());

if ($updateMode && $existing !== null) {
    $roleId = Role::idByCode($roleCode);
    if ($roleId === null) {
        fwrite(STDOUT, "Error: rol invalido.\n");
        exit(1);
    }
    User::updatePassword((int) $existing['id'], $hash);
    User::updateRole((int) $existing['id'], $roleId);

    $algo = defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'bcrypt (PASSWORD_DEFAULT)';
    fwrite(STDOUT, "OK: Usuario actualizado (contrasena y rol) correctamente.\n");
    fwrite(STDOUT, "    id=" . $existing['id'] . " rol=" . $roleCode . " email=" . $email . "\n");
    fwrite(STDOUT, "    Hash generado con password_hash() / " . $algo . "\n");
    exit(0);
}

$id = User::create([
    'first_name'    => $firstName,
    'last_name'     => $lastName,
    'email'         => $email,
    'password_hash' => $hash,
    'role_code'     => $roleCode,
]);

$algo = defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'bcrypt (PASSWORD_DEFAULT)';

fwrite(STDOUT, "OK: Usuario creado correctamente.\n");
fwrite(STDOUT, "    id=" . $id . " rol=" . $roleCode . " email=" . $email . "\n");
fwrite(STDOUT, "    Hash generado con password_hash() / " . $algo . "\n");
exit(0);