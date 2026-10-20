<?php
/** Gestion de roles y permisos. */

declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
requirePermission('roles.manage');

if (is_post()) {
    $action = post('action');
    if ($action === 'create-role') {
        RoleController::create();
    }
    if ($action === 'update-permissions') {
        RoleController::updatePermissions();
    }
    flash_set('error', 'La accion solicitada no es valida.');
    redirect('admin/roles.php');
}

$roles = Role::all();
$permissions = Permission::all();
$permissionsByRole = Role::permissionsByRoleId();
$errors = form_errors();
$old = [
    'name' => old('name'),
    'code' => old('code'),
    'description' => old('description'),
];
clear_old_input();
$pageTitle = 'Roles y permisos';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="dash-hero">
  <div class="dash-hero-body">
    <h1>Roles y permisos</h1>
    <p class="text-muted">Define los roles del sistema y el acceso que tendra cada uno a los modulos.</p>
  </div>
  <div class="dash-hero-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/dashboard.php')) ?>">Volver al panel</a>
  </div>
</section>

<?php if ($errors !== []): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="card" style="margin-bottom:24px">
  <h2>Crear rol</h2>
  <p class="text-muted">El codigo se usa internamente y no se puede modificar despues de crear el rol.</p>
  <form method="post" action="<?= e(base_url('admin/roles.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create-role">
    <div class="form-row">
      <div class="form-group">
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" maxlength="50" required>
        <span class="error-msg" aria-live="polite"><?= e($errors['name'] ?? '') ?></span>
      </div>
      <div class="form-group">
        <label for="code">Codigo</label>
        <input type="text" id="code" name="code" value="<?= e($old['code']) ?>" maxlength="50" pattern="[a-z][a-z0-9_]{1,49}" required>
        <small class="hint">Ejemplo: coordinador_academico.</small>
        <span class="error-msg" aria-live="polite"><?= e($errors['code'] ?? '') ?></span>
      </div>
    </div>
    <div class="form-group">
      <label for="description">Descripcion</label>
      <input type="text" id="description" name="description" value="<?= e($old['description']) ?>" maxlength="255">
      <span class="error-msg" aria-live="polite"><?= e($errors['description'] ?? '') ?></span>
    </div>
    <fieldset class="permission-fieldset">
      <legend>Permisos iniciales</legend>
      <div class="permission-grid">
        <?php foreach ($permissions as $permission): ?>
        <label class="permission-option">
          <input type="checkbox" name="permissions[]" value="<?= e($permission['code']) ?>">
          <span><strong><?= e($permission['name']) ?></strong><small><?= e($permission['description']) ?></small></span>
        </label>
        <?php endforeach; ?>
      </div>
      <span class="error-msg" aria-live="polite"><?= e($errors['permissions'] ?? '') ?></span>
    </fieldset>
    <button type="submit" class="btn btn-primary">Crear rol</button>
  </form>
</section>

<section>
  <h2 class="section-title">Matriz de permisos</h2>
  <div class="role-list">
    <?php foreach ($roles as $role): ?>
    <?php $assigned = $permissionsByRole[(int) $role['id']] ?? []; ?>
    <article class="card role-card">
      <div class="role-card-heading">
        <div><h3><?= e($role['name']) ?></h3><p class="text-muted"><code><?= e($role['code']) ?></code><?= $role['description'] !== null ? ' · ' . e($role['description']) : '' ?></p></div>
      </div>
      <form method="post" action="<?= e(base_url('admin/roles.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update-permissions">
        <input type="hidden" name="role_id" value="<?= e((string) $role['id']) ?>">
        <fieldset class="permission-fieldset">
          <legend class="visually-hidden">Permisos de <?= e($role['name']) ?></legend>
          <div class="permission-grid">
            <?php foreach ($permissions as $permission): ?>
            <label class="permission-option">
              <input type="checkbox" name="permissions[]" value="<?= e($permission['code']) ?>" <?= in_array($permission['code'], $assigned, true) ? 'checked' : '' ?>>
              <span><strong><?= e($permission['name']) ?></strong><small><?= e($permission['description']) ?></small></span>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <button type="submit" class="btn btn-outline-dark">Guardar permisos</button>
      </form>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
