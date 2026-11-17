<?php
declare(strict_types=1);
if (!defined('APP_PATH')) define('APP_PATH', dirname(__DIR__));
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'bootstrap.php') { http_response_code(403); exit('Acceso denegado'); }
function config(?string $key = null, mixed $default = null): mixed {
    static $config = null;
    if ($config === null) { $local = __DIR__ . '/config/config.php'; $template = __DIR__ . '/config/config.example.php'; $config = require (is_file($local) ? $local : $template); }
    if ($key === null) return $config;
    $value = $config;
    foreach (explode('.', $key) as $segment) { if (!is_array($value) || !array_key_exists($segment, $value)) return $default; $value = $value[$segment]; }
    return $value;
}
require_once __DIR__ . '/helpers/security.php';
date_default_timezone_set((string) (getenv('SISTEMA_TIMEZONE') ?: config('app.timezone', 'UTC')));
register_error_handlers(); session_start_secure();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/validation.php';
require_once __DIR__ . '/models/Role.php'; require_once __DIR__ . '/models/User.php'; require_once __DIR__ . '/models/PasswordResetToken.php'; require_once __DIR__ . '/models/Permission.php'; require_once __DIR__ . '/models/Project.php'; require_once __DIR__ . '/models/Classroom.php'; require_once __DIR__ . '/models/Task.php'; require_once __DIR__ . '/models/AcademicPeriod.php'; require_once __DIR__ . '/models/Career.php'; require_once __DIR__ . '/models/Subject.php'; require_once __DIR__ . '/models/StudyPlan.php'; require_once __DIR__ . '/models/AcademicGroup.php'; require_once __DIR__ . '/models/TeachingAssignment.php'; require_once __DIR__ . '/models/Enrollment.php'; require_once __DIR__ . '/models/CourseEnrollment.php'; require_once __DIR__ . '/models/ProjectEvent.php'; require_once __DIR__ . '/models/ProjectFile.php'; require_once __DIR__ . '/models/ProjectComment.php'; require_once __DIR__ . '/models/Notification.php';
require_once __DIR__ . '/services/ProjectMetrics.php'; require_once __DIR__ . '/services/Workload.php'; require_once __DIR__ . '/services/ProjectCharts.php'; require_once __DIR__ . '/services/ReminderService.php'; require_once __DIR__ . '/services/ProjectReport.php'; require_once __DIR__ . '/services/ConsolidatedReport.php'; require_once __DIR__ . '/services/CommentAttachments.php'; require_once __DIR__ . '/services/EncryptionService.php'; require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/helpers/auth_helpers.php'; require_once __DIR__ . '/middleware/auth.php'; require_once __DIR__ . '/middleware/csrf.php'; require_once __DIR__ . '/middleware/permissions.php';
require_once __DIR__ . '/controllers/AuthController.php'; require_once __DIR__ . '/controllers/UserController.php'; require_once __DIR__ . '/controllers/RoleController.php'; require_once __DIR__ . '/controllers/ProjectController.php'; require_once __DIR__ . '/controllers/PasswordResetController.php'; require_once __DIR__ . '/controllers/AcademicController.php'; require_once __DIR__ . '/controllers/FileController.php'; require_once __DIR__ . '/controllers/CommentController.php'; require_once __DIR__ . '/controllers/ClassroomController.php';
function base_url(string $path = ''): string { $base = rtrim((string) (getenv('SISTEMA_APP_URL') ?: config('app.url', '')), '/'); return $base . '/' . ltrim($path, '/'); }
function asset(string $path): string { return base_url('assets/' . ltrim($path, '/')); }
