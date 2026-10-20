<?php
/**
 * Sistema Universitario
 * app/helpers/validation.php
 *
 * Validaciones de entrada (server-side). Devuelven cadena vacia si es
 * valido, o un mensaje de error en espanol.
 */

declare(strict_types=1);

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function validate_required(?string $value, string $label): string
{
    if ($value === null || trim($value) === '') {
        return 'El campo ' . $label . ' es obligatorio.';
    }
    return '';
}

function validate_length(string $value, string $label, int $min, int $max): string
{
    $len = mb_strlen($value);
    if ($len < $min) {
        return 'El campo ' . $label . ' debe tener al menos ' . $min . ' caracteres.';
    }
    if ($len > $max) {
        return 'El campo ' . $label . ' no debe superar los ' . $max . ' caracteres.';
    }
    return '';
}

/**
 * Normaliza un correo: recorta y pasa a minusculas.
 */
function normalize_email(?string $email): string
{
    return strtolower(trim((string) $email));
}

function validate_email_format(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El correo electronico no tiene un formato valido.';
    }
    return '';
}

/**
 * Politica de contrasena segura:
 *  - minimo 8 caracteres
 *  - al menos una letra mayuscula
 *  - al menos una letra minuscula
 *  - al menos un numero
 *  - al menos un caracter especial (no alfanumerico)
 */
function validate_password_strength(string $password): string
{
    if (mb_strlen($password) < 8) {
        return 'La contrasena debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'La contrasena debe incluir al menos una letra mayuscula.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'La contrasena debe incluir al menos una letra minuscula.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'La contrasena debe incluir al menos un numero.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'La contrasena debe incluir al menos un caracter especial (por ejemplo: ! @ # $ %).';
    }
    return '';
}

function password_policy_hint(): string
{
    return 'Minimo 8 caracteres, con al menos una mayuscula, una minuscula, un numero y un caracter especial.';
}

function validate_password_confirmation(string $password, string $confirmation): string
{
    if ($confirmation === '') {
        return 'Debes confirmar la contrasena.';
    }
    if ($password !== $confirmation) {
        return 'Las contrasenas no coinciden.';
    }
    return '';
}

/**
 * Genera una contrasena segura aleatoria (8+ caracteres, mayuscula,
 * minuscula, numero y caracter especial). Usada por el admin al crear
 * usuarios cuando no especifica contrasena.
 */
function generate_secure_password(int $length = 12): string
{
    $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower  = 'abcdefghjkmnpqrstuvwxyz';
    $digits = '23456789';
    $special = '!@#$%&*?.-';

    $password  = $upper[random_int(0, strlen($upper) - 1)];
    $password .= $lower[random_int(0, strlen($lower) - 1)];
    $password .= $digits[random_int(0, strlen($digits) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];

    $all = $upper . $lower . $digits . $special;
    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    // Barajar la contrasena para que los caracteres fijos no esten en orden predecible
    $arr = str_split($password);
    for ($i = count($arr) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
    }

    return implode('', $arr);
}