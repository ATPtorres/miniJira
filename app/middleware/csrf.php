<?php
/**
 * Sistema Universitario
 * app/middleware/csrf.php
 *
 * Proteccion CSRF: toda peticion POST debe llevar un token valido
 * de la sesion. Sin excepcion.
 */

declare(strict_types=1);

/**
 * Valida el token CSRF del POST actual; si falta o no coincide,
 * responde 419 y vuelve a login (la sesion puede haber expirado).
 */
function requireCsrf(): void
{
    if (!csrf_verify()) {
        http_error_response(419, 'Page Expired');
        flash_set('error', 'La sesion expiro o la solicitud no es valida. Intentalo de nuevo.');
        redirect('login.php');
    }
}