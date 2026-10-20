<?php
/**
 * Sistema Universitario
 * app/middleware/auth.php
 *
 * Proteccion de rutas privadas (requiere sesion iniciada).
 */

declare(strict_types=1);

/**
 * Requiere usuario autenticado. Redirige a login con mensaje si no lo esta.
 */
function requireAuth(): void
{
    if (!is_logged_in()) {
        flash_set('error', 'Debes iniciar sesion para acceder a esta pagina.');
        redirect('login.php');
    }
}