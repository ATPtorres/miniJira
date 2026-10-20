<?php
/**
 * Sistema Universitario
 * app/bootstrap.php
 *
 * Punto de entrada comun para todas las paginas public/: carga configuracion,
 * establece manejadores de error, inicia la sesion segura y carga la capa
 * de aplicacion (helpers, middleware, modelos, servicios, controladores).
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Ruta raiz del proyecto                                              */
/* ------------------------------------------------------------------ */
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__));
}

/* ------------------------------------------------------------------ */
/* Acceso directo: la ruta raiz nunca debe ejecutarse por URL          */
/* (el .htaccess ya lo impide; esta es una capa extra)                 */
/* ------------------------------------------------------------------ */
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'bootstrap.php') {
    http_response_code(403);
    exit('Acceso denegado');
}

/* ------------------------------------------------------------------ */
/* Configuracion (cargada una sola vez)                                */
/* ------------------------------------------------------------------ */
function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config/config.php';
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/* ------------------------------------------------------------------ */
/* Helpers base de seguridad                                           */
/* ------------------------------------------------------------------ */
require_once __DIR__ . '/helpers/security.php';

date_default_timezone_set((string) config('app.timezone', 'UTC'));

// Los errores PHP van al log de la aplicacion, nunca al navegador.
register_error_handlers();

// Sesion segura (HttpOnly, SameSite=Lax, secure solo en HTTPS) + token CSRF.
session_start_secure();

/* ------------------------------------------------------------------ */
/* Capa de datos y aplicacion                                          */
/* ------------------------------------------------------------------ */
require_once __DIR__ . '/config/database.php';      // db()
require_once __DIR__ . '/helpers/validation.php';   // validaciones de entrada
require_once __DIR__ . '/models/Role.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/PasswordResetToken.php';
require_once __DIR__ . '/models/Permission.php';
require_once __DIR__ . '/models/Project.php';
require_once __DIR__ . '/models/Task.php';
require_once __DIR__ . '/models/AcademicPeriod.php';
require_once __DIR__ . '/models/Career.php';
require_once __DIR__ . '/models/Subject.php';
require_once __DIR__ . '/models/StudyPlan.php';
require_once __DIR__ . '/models/AcademicGroup.php';
require_once __DIR__ . '/models/TeachingAssignment.php';
require_once __DIR__ . '/models/Enrollment.php';
require_once __DIR__ . '/models/CourseEnrollment.php';
require_once __DIR__ . '/models/ProjectEvent.php';
require_once __DIR__ . '/models/ProjectFile.php';
require_once __DIR__ . '/models/ProjectComment.php';
require_once __DIR__ . '/models/Notification.php';
require_once __DIR__ . '/services/EncryptionService.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/helpers/auth_helpers.php'; // sesion + can() + matriz de permisos
require_once __DIR__ . '/middleware/auth.php';      // requireAuth()
require_once __DIR__ . '/middleware/csrf.php';      // requireCsrf()
require_once __DIR__ . '/middleware/permissions.php'; // requireRole() / requirePermission()
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/RoleController.php';
require_once __DIR__ . '/controllers/ProjectController.php';
require_once __DIR__ . '/controllers/PasswordResetController.php';
require_once __DIR__ . '/controllers/AcademicController.php';
require_once __DIR__ . '/controllers/FileController.php';
require_once __DIR__ . '/controllers/CommentController.php';

/* ------------------------------------------------------------------ */
/* Utilidades de URL (para vistas)                                     */
/* ------------------------------------------------------------------ */

/**
 * URL base de la aplicacion, p.ej. http://localhost/sistema-universitario/login.php
 */
function base_url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * URL de un recurso dentro de /assets/.
 */
function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}
