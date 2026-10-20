<?php
/**
 * Sistema Universitario
 * app/helpers/auth_helpers.php
 *
 * Gestion de sesion de autenticacion, usuario actual, matriz de permisos
 * y la funcion central de autorizacion can($permiso).
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Usuario actual                                                      */
/* ------------------------------------------------------------------ */

/**
 * Usuario autenticado (datos frescos de BD), o null.
 * La sesion guarda solo user_id y role_code; nunca la contrasena.
 */
function current_user(): ?array
{
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    $id = (int) ($_SESSION['user_id'] ?? 0);

    if ($id <= 0) {
        return null;
    }

    $user = User::findById($id);

    // Usuario dado de baja durante su sesion -> cerrar inmediatamente.
    if ($user !== null && (int) $user['is_active'] !== 1) {
        logout_user();
        $user = null;
        return null;
    }

    // Mantener la sesion sincronizada con el rol actual de la BD.
    if ($user !== null && ($_SESSION['role_code'] ?? null) !== $user['role_code']) {
        $_SESSION['role_code'] = $user['role_code'];
    }

    return $user;
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_role_code(): ?string
{
    $user = current_user();
    return $user['role_code'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/* ------------------------------------------------------------------ */
/* Login / logout                                                      */
/* ------------------------------------------------------------------ */

/**
 * Registra al usuario en la sesion:
 * regenera el ID de sesion (anti session fixation), guarda datos minimos
 * y actualiza last_login_at.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['role_code'] = (string) $user['role_code'];

    // Nuevo token CSRF ligado a la sesion recien creada.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    User::updateLastLogin((int) $user['id']);

    clear_login_attempts();
}

/**
 * Cierra la sesion por completo: limpia variables, borra la cookie
 * del navegador y destruye la sesion del servidor.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            (bool) $p['secure'],
            (bool) $p['httponly']
        );
    }

    session_destroy();
}

function redirect_to_panel(?string $roleCode = null): void
{
    $role = $roleCode ?? current_role_code();
    redirect($role !== null && can('admin.access', $role) ? 'admin/dashboard.php' : 'user/dashboard.php');
}

/* ------------------------------------------------------------------ */
/* Limitacion de intentos de login (por sesion, entorno local)         */
/* ------------------------------------------------------------------ */

function failed_attempts(): int
{
    return (int) ($_SESSION['login_attempts'] ?? 0);
}

function is_login_locked(): bool
{
    return isset($_SESSION['login_locked_until']) && time() < (int) $_SESSION['login_locked_until'];
}

function lockout_seconds_left(): int
{
    if (!is_login_locked()) {
        return 0;
    }
    return (int) $_SESSION['login_locked_until'] - time();
}

function register_failed_attempt(): void
{
    if (is_login_locked()) {
        return;
    }

    $_SESSION['login_attempts'] = failed_attempts() + 1;

    $max = (int) config('auth.max_login_attempts', 5);
    if ($_SESSION['login_attempts'] >= $max) {
        $_SESSION['login_locked_until'] = time() + (int) config('auth.lockout_minutes', 15) * 60;
    }
}

function clear_login_attempts(): void
{
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
}

/* ------------------------------------------------------------------ */
/* Matriz de permisos (fuente de verdad del sistema)                */
/* La misma matriz existe en BD (tablas permissions/role_permissions). */
/* ------------------------------------------------------------------ */

function permission_matrix(): array
{
    $matrix = [];
    foreach (Role::all() as $role) {
        $matrix[$role['code']] = Role::permissionCodesForCode((string) $role['code']);
    }
    return $matrix;
}

/**
 * Autorizacion central: el servidor decide, no el navegador.
 */
function can(string $permission, ?string $roleCode = null): bool
{
    $role = $roleCode ?? current_role_code();

    if ($role === null) {
        return false;
    }

    return in_array($permission, Role::permissionCodesForCode($role), true);
}
