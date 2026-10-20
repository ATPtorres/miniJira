<?php
/**
 * Sistema Universitario
 * public/error.php — Pagina de error controlado (500).
 *
 * El detalle tecnico de los errores queda registrado internamente;
 * esta pagina nunca muestra detalles de implementacion.
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

http_response_code(500);

$pageTitle = 'Error del servidor';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="http-error">
  <span class="error-code" aria-hidden="true">500</span>
  <h1>Ha ocurrido un error</h1>
  <p>
    Lo sentimos, se presento un problema inesperado en el sistema.
    Por favor intenta de nuevo en unos momentos.
  </p>
  <p>
    <a class="btn btn-primary" href="<?= e(base_url()) ?>">Volver al inicio</a>
  </p>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>