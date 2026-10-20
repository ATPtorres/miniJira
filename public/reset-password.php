<?php
/**
 * Sistema Universitario
 * public/reset-password.php — Restablecimiento con token (un solo uso).
 *
 * Uso: reset-password.php?token=XXXX
 */

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect_to_panel();
}

$token = (string) ($_POST['token'] ?? ($_GET['token'] ?? ''));

if ($token === '') {
    flash_set('error', 'El enlace de recuperacion no es valido. Solicita uno nuevo.');
    redirect('forgot-password.php');
}

// Validacion temprana: si el enlace que se abre ya no es valido
// (expiro, fue usado o no existe), se informa de inmediato.
if (!is_post() && PasswordResetToken::findValid(PasswordResetToken::hashToken($token)) === null) {
    flash_set('error', 'El enlace de recuperacion no es valido, expiro o ya fue utilizado. Solicita uno nuevo.');
    redirect('forgot-password.php');
}

if (is_post()) {
    PasswordResetController::reset();
}

$pageTitle = 'Restablecer contrasena';
$errors = form_errors();
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<div class="auth-card">
  <span class="auth-logo" aria-hidden="true"><?= e(mb_substr(trim(strip_tags(config('app.name'))), 0, 1, 'UTF-8')) ?></span>
  <h1>Nueva contrasena</h1>
  <p class="text-muted">Elige una contrasena nueva. Solo se puede usar este enlace una vez.</p>

  <form method="post" action="<?= e(base_url('reset-password.php')) ?>" novalidate data-validate="reset">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="form-group">
      <label for="password">Nueva contrasena</label>
      <div class="password-field">
        <input type="password" id="password" name="password"
               autocomplete="new-password" required
               aria-describedby="password-hint">
        <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Mostrar contrasena" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <span class="visually-hidden">Mostrar contrasena</span>
        </button>
      </div>
      <span class="error-msg" id="password-error" aria-live="polite"><?= e($errors['password'] ?? '') ?></span>
      <small class="hint" id="password-hint"><?= e(password_policy_hint()) ?></small>
    </div>

    <div class="form-group">
      <label for="password_confirmation">Confirmar contrasena</label>
      <div class="password-field">
        <input type="password" id="password_confirmation" name="password_confirmation"
               autocomplete="new-password" required>
        <button type="button" class="password-toggle" data-password-toggle aria-controls="password_confirmation" aria-label="Mostrar contrasena" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <span class="visually-hidden">Mostrar contrasena</span>
        </button>
      </div>
      <span class="error-msg" id="password_confirmation-error" aria-live="polite"><?= e($errors['password_confirmation'] ?? '') ?></span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Guardar nueva contrasena</button>
  </form>
</div>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>