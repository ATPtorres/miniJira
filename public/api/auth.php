<?php
/**
 * Sistema Universitario
 * public/api/auth.php — Endpoint JSON auxiliar (fetch()).
 *
 * Acciones:
 *   POST { action: "check-email", email, csrf_token }
 *        -> { ok: true, available: bool }  (unicidad de correo en el registro)
 *
 * La validacion final SIEMPRE la hace el servidor en el controlador;
 * este endpoint solo mejora la experiencia de usuario.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!is_post()) {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    if (!csrf_verify_token($payload['csrf_token'] ?? null)) {
        http_error_response(419, 'Page Expired');
        echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido']);
        exit;
    }

    $action = (string) ($payload['action'] ?? '');

    if ($action === 'check-email') {
        $email = normalize_email((string) ($payload['email'] ?? ''));

        if ($email === '' || validate_email_format($email) !== '') {
            echo json_encode(['ok' => true, 'available' => null]); // sin validar
            exit;
        }

        echo json_encode(['ok' => true, 'available' => !User::existsByEmail($email)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Accion desconocida']);
} catch (Throwable $e) {
    log_error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor']);
}