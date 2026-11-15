<?php
/**
 * Sistema Universitario
 * public/index.php — Pagina de bienvenida (o redireccion al panel).
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect_to_panel();
}

$pageTitle = 'Bienvenida';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="hero">
  <div>
    <span class="eyebrow">Tareas y entregas universitarias</span>
    <h1>Tus tareas, entregas y evaluaciones en un solo lugar</h1>
    <p class="lead">
      Consulta el trabajo de tus clases, entrega tus archivos y recibe retroalimentación.
      Los docentes organizan las tareas, califican las entregas y consultan el avance de sus proyectos.
    </p>
    <div class="hero-actions">
      <a class="btn btn-light" href="<?= e(base_url('login.php')) ?>">Iniciar sesión</a>
    </div>
  </div>
</section>

<section class="features" aria-label="Cómo te ayuda MiniJira">
  <article class="feature">
    <span class="feature-icon" aria-hidden="true">E</span>
    <h2>Para estudiantes</h2>
    <p>Consulta fechas límite, sube tus entregas y revisa tus calificaciones y comentarios del docente.</p>
  </article>
  <article class="feature">
    <span class="feature-icon" aria-hidden="true">D</span>
    <h2>Para docentes</h2>
    <p>Asigna tareas a tus clases, revisa archivos y registra calificaciones y retroalimentación.</p>
  </article>
  <article class="feature">
    <span class="feature-icon" aria-hidden="true">A</span>
    <h2>Para la coordinación</h2>
    <p>Autoriza cuentas y participantes de las clases para mantener separado el trabajo de cada grupo.</p>
  </article>
</section>

<section class="landing-cta">
  <h2>¿Listo para comenzar?</h2>
  <p>
    Ingresa con la cuenta habilitada para MiniJira. Cada clase reúne a su docente y a sus participantes autorizados.
    Las inscripciones y la gestión oficial de docentes se realizan en la plataforma de tu universidad.
  </p>
  <a class="btn btn-primary" href="<?= e(base_url('login.php')) ?>">Iniciar sesión</a>
  <p><a href="<?= e(base_url('help.php')) ?>">Consultar ayuda y soporte</a></p>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>