<?php
/**
 * Sistema Universitario
 * public/logout.php — Cierre de sesion.
 *
 * Solo acepta POST con token CSRF (segun especificacion). Un GET
 * simplemente redirige al login.
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

AuthController::logout();