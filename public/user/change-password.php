<?php
/**
 * Sistema Universitario
 * public/user/change-password.php — Cambio manual de contrasena propia.
 *
 * Requiere sesion iniciada y el permiso password.change.own.
 * El administrador tambien puede usarla (esta en su matriz de permisos).
 */

declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
requirePermission('password.change.own');

if (is_post()) {
    AuthController::changeOwnPassword();
}

$pageTitle = 'Cambiar contrasena';
$errors = form_errors();
$back = can('admin.access') ? 'admin/dashboard.php' : 'user/dashboard.php';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head">
  <div>
    <h1>Cambiar contrasena</h1>
    <p class="text-muted">Actualiza tu contrasena para tener mayor control y seguridad sobre tu cuenta.</p>
  </div>
</section>

<div class="auth-card">
  <h1>Nueva contrasena</h1>
  <p class="text-muted">Introduce tu contrasena actual y elige una nueva.</p>

  <form method="post" action="<?= e(base_url('user/change-password.php')) ?>" novalidate data-validate="change">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="current_password">Contrasena actual</label>
      <div class="password-field">
        <input type="password" id="current_password" name="current_password"
               autocomplete="current-password" required>
        <button type="button" class="password-toggle" data-password-toggle aria-controls="current_password" aria-label="Mostrar contrasena" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <span class="visually-hidden">Mostrar contrasena</span>
        </button>
      </div>
      <span class="error-msg" id="current_password-error" aria-live="polite"><?= e($errors['current_password'] ?? '') ?></span>
    </div>

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
      <label for="password_confirmation">Confirmar nueva contrasena</label>
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

    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-block">Guardar nueva contrasena</button>
      <a class="btn btn-outline btn-block" href="<?= e(base_url($back)) ?>">Cancelar</a>
    </div>
  </form>
</div>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
