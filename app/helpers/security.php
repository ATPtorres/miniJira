<?php
/**
 * Sistema Universitario
 * app/helpers/security.php
 *
 * Funciones de seguridad de base: escapado, CSRF, sesion segura,
 * mensajes flash, logs y manejo global de errores.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Escapado de salida (anti XSS)                                       */
/* ------------------------------------------------------------------ */

/**
 * Escapa texto para HTML. Usar SIEMPRE antes de imprimir datos dinÃ¡micos.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Inserta saltos de linea cada N palabras (por defecto 10).
 * Escapa HTML. Devuelve string con <br> para HTML.
 */
function wrap_words(?string $text, int $wordsPerLine = 10): string
{
    if ($text === null || trim($text) === '') {
        return '';
    }
    $words = preg_split('/\s+/', trim($text));
    if ($words === false || $words === []) {
        return e($text);
    }
    $chunks = array_chunk($words, $wordsPerLine);
    $lines = array_map(fn(array $chunk): string => e(implode(' ', $chunk)), $chunks);
    return implode('<br>', $lines);
}

/* ------------------------------------------------------------------ */
/* Solicitudes                                                         */
/* ------------------------------------------------------------------ */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_https_request(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ------------------------------------------------------------------ */
/* Redirecciones                                                       */
/* ------------------------------------------------------------------ */

/**
 * Envia un codigo de estado HTTP no estandar de forma fiable.
 * (http_response_code() con codigos no comunes falla en algunos
 * modulos PHP de Windows y Apache los convierte a 500.)
 */
function http_error_response(int $code, string $reasonPhrase = ''): void
{
    http_response_code($code);
    header('HTTP/1.1 ' . $code . ' ' . $reasonPhrase, true, $code);
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function redirect_to_login(): void
{
    redirect('login.php');
}

/**
 * URL absoluta y segura para el link de una notificacion.
 * Evita 404 por rutas relativas resueltas contra la pagina anidada actual.
 */
function notification_url(?string $link): string
{
    if ($link === null || trim($link) === '') {
        return base_url('user/notifications.php');
    }
    if (preg_match('#^https?://#i', $link) === 1) {
        return $link;
    }
    return base_url($link);
}

/* ------------------------------------------------------------------ */
/* Algoritmo de hash de contrasenas                                    */
/* ------------------------------------------------------------------ */

/**
 * PASSWORD_ARGON2ID si el PHP actual lo soporta (PHP >= 7.3 con libsodium);
 * en caso contrario PASSWORD_DEFAULT (bcrypt).
 */
function password_algo(): string|int
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    return (string) ($_SESSION['csrf_token'] ?? '');
}

function csrf_field(): string
{
    $token = e(csrf_token());
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}

function csrf_verify_token(mixed $token): bool
{
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_verify(): bool
{
    return csrf_verify_token($_POST['csrf_token'] ?? null);
}

/* ------------------------------------------------------------------ */
/* Mensajes flash (una lectura)                                        */
/* ------------------------------------------------------------------ */

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function flash_display(): string
{
    $html = '';
    foreach (flash_get_all() as $f) {
        $type = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . '" role="alert">'
               . e($f['message']) . '</div>';
    }
    return $html;
}

/* ------------------------------------------------------------------ */
/* Estado de formularios (errores por campo + valores anteriores)      */
/* ------------------------------------------------------------------ */

function flash_form_errors(array $errors): void
{
    // Se conservan las claves por campo: las vistas leen $errors['email'], etc.
    $_SESSION['_form_errors'] = $errors;
}

function form_errors(): array
{
    $errors = $_SESSION['_form_errors'] ?? [];
    unset($_SESSION['_form_errors']);
    return $errors;
}

function flash_old_input(array $data): void
{
    $_SESSION['_old'] = $data;
}

function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

/* ------------------------------------------------------------------ */
/* Sesion segura                                                       */
/* ------------------------------------------------------------------ */

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // secure => true solo con HTTPS. En XAMPP local (http://localhost)
    // quedarÃ­a en false, tal como indica la especificacion.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https_request(),
        'httponly' => true,     // JS no puede leer la cookie (anti robo)
        'samesite' => 'Lax',    // mitiga CSRF de cookies
    ]);

    session_name((string) config('auth.session_name', 'sistema_uni_session'));
    session_start();

    // Token CSRF por sesion (se regenera al iniciar sesion).
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/* ------------------------------------------------------------------ */
/* Registro (logging)                                                  */
/* ------------------------------------------------------------------ */

function log_error(string $message, string $file = 'app.log'): void
{
    $dir = APP_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = sprintf(
        "[%s] %s%s",
        date('Y-m-d H:i:s'),
        $message,
        PHP_EOL
    );

    @file_put_contents($dir . '/' . basename($file), $line, FILE_APPEND | LOCK_EX);
}

/* ------------------------------------------------------------------ */
/* Manejo global de errores: detalles al log, mensaje amigable al user */
/* ------------------------------------------------------------------ */

function register_error_handlers(): void
{
    // Convierte warnings/notices en excepciones para tratarlos igual.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler('exception_to_response');
}

function exception_to_response(Throwable $e): void
{
    // 1) Detalle interno SOLO al log de la aplicacion.
    log_error(
        get_class($e) . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine()
    );

    // 2) CLI (scripts como database/create_user.php): detalle a consola.
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    // 3) Respuesta amigable sin detalles internos.
    if (headers_sent()) {
        echo 'Error interno del servidor.';
        exit(1);
    }

    http_response_code(500);

    $is_api = stripos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/') !== false
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

    if ($is_api) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Error interno del servidor']);
        exit(1);
    }

    $home = htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Error del servidor</title></head><body style="font-family:sans-serif;max-width:640px;margin:40px auto;padding:0 16px">'
       . '<h1>Error del servidor</h1>'
       . '<p>Ha ocurrido un problema inesperado. El detalle tecnico se registro en el log '
       . 'de la aplicacion (storage/logs/app.log) y no se muestra en esta pagina por seguridad.</p>'
       . '<p><a href="' . $home . '">Volver al inicio</a></p>'
       . '</body></html>';
    exit(1);
}