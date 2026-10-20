<?php
/**
 * Sistema Universitario
 * public/login.php — Inicio de sesion.
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect_to_panel();
}

if (is_post()) {
    AuthController::login();
}

$pageTitle = 'Iniciar sesion';
$errors = form_errors();
clear_old_input();
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<div class="auth-card">
  <span class="auth-logo" aria-hidden="true">U</span>
  <h1>Bienvenido de vuelta</h1>
  <p class="text-muted">Ingresa con tu correo institucional y contrasena.</p>

  <form method="post" action="<?= e(base_url('login.php')) ?>" novalidate data-validate="login">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="email">Correo electronico</label>
      <input type="email" id="email" name="email"
             value="<?= e(old('email')) ?>"
             autocomplete="email" autofocus
             maxlength="190" required>
      <span class="error-msg" id="email-error" aria-live="polite"><?= e($errors['email'] ?? '') ?></span>
    </div>

    <div class="form-group">
      <label for="password">Contrasena</label>
      <div class="password-field">
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>
        <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Mostrar contrasena" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <span class="visually-hidden">Mostrar contrasena</span>
        </button>
      </div>
      <span class="error-msg" id="password-error" aria-live="polite"><?= e($errors['password'] ?? '') ?></span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
  </form>

  <div class="auth-footer-links">
    <p class="text-center"><a href="<?= e(base_url('forgot-password.php')) ?>">Olvidaste tu contrasena?</a></p>
    <p class="text-center text-muted">Si no tienes cuenta, contacta al administrador del sistema.</p>
  </div>
</div>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
