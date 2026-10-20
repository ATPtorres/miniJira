<?php
/**
 * Sistema Universitario
 * public/forgot-password.php — Solicitud de recuperacion de contrasena.
 *
 * La respuesta es generica (no revela si el correo existe). En modo
 * local, el enlace se escribe en storage/logs/password_reset.log.
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect_to_panel();
}

if (is_post()) {
    PasswordResetController::request();
}

$pageTitle = 'Recuperar contrasena';
$errors = form_errors();
$email = old('email');
clear_old_input();
// Enlace de desarrollo (solo existe si app.env=local y el correo era valido).
// Se muestra una sola vez y luego se elimina de la sesion.
$devResetLink = (string) ($_SESSION['_dev_reset_link'] ?? '');
unset($_SESSION['_dev_reset_link']);
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<div class="auth-card">
  <span class="auth-logo" aria-hidden="true"><?= e(mb_substr(trim(strip_tags(config('app.name'))), 0, 1, 'UTF-8')) ?></span>
  <h1>Recuperar contrasena</h1>
  <p class="text-muted">
    Escribe el correo de tu cuenta. Si esta registrado, recibiras un enlace
    para restablecer la contrasena.
  </p>

  <form method="post" action="<?= e(base_url('forgot-password.php')) ?>" novalidate data-validate="forgot">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="email">Correo electronico</label>
      <input type="email" id="email" name="email"
             value="<?= e($email) ?>"
             autocomplete="email" maxlength="190" required>
      <span class="error-msg" id="email-error" aria-live="polite"><?= e($errors['email'] ?? '') ?></span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Enviar enlace de recuperacion</button>
  </form>

  <?php if ($devResetLink !== ''): ?>
  <div class="alert alert-info" role="alert" style="margin-top:16px;word-break:break-all">
    <p style="margin:0 0 8px"><strong>Modo local:</strong> copia este enlace para restablecer tu contrasena (valido 60 min, un solo uso):</p>
    <p style="margin:0 0 8px"><a href="<?= e($devResetLink) ?>"><?= e($devResetLink) ?></a></p>
    <button type="button" class="btn btn-sm btn-outline-dark" data-copy-link="<?= e($devResetLink) ?>">Copiar enlace</button>
  </div>
  <script>
  document.querySelector('[data-copy-link]')?.addEventListener('click', function () {
    const link = this.getAttribute('data-copy-link');
    if (navigator.clipboard) {
      navigator.clipboard.writeText(link).then(() => { this.textContent = 'Copiado'; });
    } else {
      prompt('Copia el enlace:', link);
    }
  });
  </script>
  <?php endif; ?>

  <div class="auth-footer-links">
    <p class="text-center"><a href="<?= e(base_url('login.php')) ?>">Volver al inicio de sesion</a></p>
  </div>
</div>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>