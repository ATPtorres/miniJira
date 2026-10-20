<?php
/**
 * Sistema Universitario
 * app/middleware/permissions.php
 *
 * Autorizacion por rol y por permiso, siempre en el servidor.
 */

declare(strict_types=1);

function access_denied(): void
{
    http_response_code(403);
    redirect('denied.php');
}

/**
 * Requiere un rol exacto, p.ej. requireRole('admin').
 */
function requireRole(string $role): void
{
    if (current_role_code() !== $role) {
        access_denied();
    }
}

/**
 * Requiere un permiso de la matriz, p.ej. requirePermission('users.view').
 */
function requirePermission(string $permission): void
{
    if (!can($permission)) {
        access_denied();
    }
}