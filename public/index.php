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
    <span class="eyebrow">Portal institucional</span>
    <h1>Bienvenido al Sistema Universitario</h1>
    <p class="lead">
      El espacio digital que conecta a estudiantes, docentes y administracion
      en un solo lugar. Accede a tu informacion y gestiona tu vida universitaria
      de forma segura y sencilla.
    </p>
    <div class="hero-actions">
      <a class="btn btn-light" href="<?= e(base_url('login.php')) ?>">Iniciar sesion</a>
    </div>
  </div>
</section>

<section class="features" aria-label="Informacion del portal">
  <article class="feature">
    <span class="feature-icon" aria-hidden="true">E</span>
    <h2>Para estudiantes</h2>
    <p>Un punto unico para tu vida academica: cuentas, acceso y comunicacion con la institucion.</p>
  </article>
  <article class="feature">
    <span class="feature-icon" aria-hidden="true">D</span>
    <h2>Para docentes</h2>
    <p>Un espacio profesional para la gestion academica y la relacion con tus estudiantes.</p>
  </article>
  <article class="feature">
    <span class="feature-icon" aria-hidden="true">A</span>
    <h2>Para administracion</h2>
    <p>Herramientas centralizadas para gestionar usuarios, roles y el control del portal.</p>
  </article>
</section>

<section class="landing-cta">
  <h2>¿Listo para comenzar?</h2>
  <p>
    Ingresa con tus credenciales institucionales para acceder a tu panel.
    Si no tienes cuenta, contacta al administrador del sistema.
  </p>
  <a class="btn btn-primary" href="<?= e(base_url('login.php')) ?>">Iniciar sesion</a>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>