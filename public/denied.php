<?php
/**
 * Sistema Universitario
 * public/denied.php — Acceso denegado (403).
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

// Estado real de acceso denegado (los middleware de rutas tambien lo usan).
http_response_code(403);

$pageTitle = 'Acceso denegado';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="http-error">
  <span class="error-code" aria-hidden="true">403</span>
  <h1>Acceso denegado</h1>
  <p>
    No tienes permisos para ver esta pagina.
    Si crees que es un error, contacta al administrador del sistema.
  </p>
  <p>
    <a class="btn btn-primary" href="<?= e(base_url(is_logged_in() ? 'user/dashboard.php' : 'login.php')) ?>">
      <?= is_logged_in() ? 'Ir a mi panel' : 'Iniciar sesion' ?>
    </a>
  </p>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>