<?php
/**
 * Sistema Universitario
 * public/admin/users.php — Gestion de usuarios (admin).
 */

declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
requirePermission('users.manage');

if (is_post()) {
    $action = post('action');
    if ($action === 'create-user') UserController::createUser();
    if ($action === 'change-role') UserController::changeRole();
    if ($action === 'update-user') UserController::updateUser();
    if ($action === 'toggle-active') UserController::toggleActive();
    flash_set('error', 'La accion solicitada no es valida.');
    redirect('admin/users.php');
}

$me      = current_user();
$users   = User::all();
$roles   = Role::all();
$editingId = (int) ($_GET['edit'] ?? 0);
$editing = $editingId > 0 ? User::findById($editingId) : null;
$counts  = User::countByRole();
$total   = array_sum(array_map(static fn (array $r): int => (int) $r['total'], $counts));
$initials = mb_strtoupper(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1), 'UTF-8');
$errors  = form_errors();
$old     = [
    'first_name' => old('first_name'),
    'last_name'  => old('last_name'),
    'email'      => old('email'),
    'role_code'  => old('role_code'),
];
clear_old_input();

$pageTitle = 'Gestion de usuarios';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="dash-hero">
  <span class="avatar avatar-lg" aria-hidden="true"><?= e($initials) ?></span>
  <div class="dash-hero-body">
    <h1>Gestion de usuarios <span class="role-badge">Administrador</span></h1>
    <p class="text-muted">Crear, consultar y gestionar las cuentas del sistema.</p>
  </div>
  <div class="dash-hero-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/dashboard.php')) ?>">Volver al panel</a>
  </div>
</section>

<section class="cards">
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
    <div><div class="stat-value"><?= e((string) $total) ?></div><p class="stat-label">Usuarios totales</p></div>
  </article>
  <?php foreach ($counts as $row): ?>
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 0 3-2 5-2s2 2 5 2v-5"/></svg></span>
    <div><div class="stat-value"><?= e((string) $row['total']) ?></div><p class="stat-label"><?= e($row['name']) ?></p></div>
  </article>
  <?php endforeach; ?>
</section>

<?php if ($errors !== []): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $err): ?>
      <p><?= e($err) ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="card" style="margin-bottom:24px">
  <h2 style="margin-bottom:6px"><?= $editing === null ? 'Crear nuevo usuario' : 'Editar usuario' ?></h2>
  <p class="text-muted" style="margin-bottom:18px"><?= $editing === null ? 'Completa los datos. Si dejas la contrasena vacia, se genera una segura automaticamente.' : 'Actualiza los datos, rol o correo del usuario seleccionado.' ?></p>
  <form method="post" action="<?= e(base_url('admin/users.php')) ?>" novalidate data-validate="create-user">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing === null ? 'create-user' : 'update-user' ?>">
    <?php if ($editing !== null): ?><input type="hidden" name="user_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div class="form-group">
        <label for="first_name">Nombre</label>
        <input type="text" id="first_name" name="first_name" value="<?= e($editing['first_name'] ?? $old['first_name']) ?>" autocomplete="given-name" maxlength="80" required>
        <span class="error-msg" id="first_name-error" aria-live="polite"><?= e($errors['first_name'] ?? '') ?></span>
      </div>
      <div class="form-group">
        <label for="last_name">Apellido</label>
        <input type="text" id="last_name" name="last_name" value="<?= e($editing['last_name'] ?? $old['last_name']) ?>" autocomplete="family-name" maxlength="80" required>
        <span class="error-msg" id="last_name-error" aria-live="polite"><?= e($errors['last_name'] ?? '') ?></span>
      </div>
    </div>
    <div class="form-group">
      <label for="email">Correo electronico</label>
      <input type="email" id="email" name="email" value="<?= e($editing['email'] ?? $old['email']) ?>" autocomplete="email" maxlength="190" required>
      <span class="error-msg" id="email-error" aria-live="polite"><?= e($errors['email'] ?? '') ?></span>
    </div>
    <div class="form-group">
      <label for="role_code">Rol</label>
      <select id="role_code" name="role_code" required>
        <option value="">-- Selecciona un rol --</option>
        <?php foreach ($roles as $role): ?>
        <option value="<?= e($role['code']) ?>" <?= ($editing['role_code'] ?? $old['role_code']) === $role['code'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="error-msg" id="role_code-error" aria-live="polite"><?= e($errors['role_code'] ?? '') ?></span>
    </div>
    <?php if ($editing === null): ?><div class="form-group">
      <label for="password">Contrasena</label>
      <div class="password-field">
        <input type="password" id="password" name="password" autocomplete="new-password" aria-describedby="password-hint">
        <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Mostrar contrasena" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <span class="visually-hidden">Mostrar contrasena</span>
        </button>
      </div>
      <span class="error-msg" id="password-error" aria-live="polite"><?= e($errors['password'] ?? '') ?></span>
      <small class="hint" id="password-hint">Dejar vacio para generar una contrasena segura automaticamente.</small>
    </div><?php endif; ?>
    <button type="submit" class="btn btn-primary" onclick="return confirm('<?= $editing === null ? '¿Crear este nuevo usuario?' : '¿Guardar los cambios del usuario?' ?>')"><?= $editing === null ? 'Crear usuario' : 'Guardar cambios' ?></button>
    <?php if ($editing !== null): ?><a class="btn btn-outline-dark" href="<?= e(base_url('admin/users.php')) ?>">Cancelar edicion</a><?php endif; ?>
  </form>
</section>

<section>
  <h2 class="section-title" style="margin-top:0">Usuarios existentes</h2>
</section>
<section class="table-wrap">
  <table>
    <caption class="visually-hidden">Lista de usuarios del sistema</caption>
    <thead>
      <tr>
        <th scope="col">ID</th>
        <th scope="col">Nombre</th>
        <th scope="col">Correo</th>
        <th scope="col">Rol</th>
        <th scope="col">Estado</th>
        <th scope="col">Ultimo acceso</th>
        <th scope="col">Creado</th>
        <th scope="col">Accion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e((string) $u['id']) ?></td>
        <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td>
          <form method="post" action="<?= e(base_url('admin/users.php')) ?>" class="inline-form" data-validate="role">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change-role">
            <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
            <label class="visually-hidden" for="role-<?= e((string) $u['id']) ?>">Rol del usuario <?= e((string) $u['id']) ?></label>
            <select id="role-<?= e((string) $u['id']) ?>" name="role_code" required>
              <?php foreach ($roles as $r): ?>
              <option value="<?= e($r['code']) ?>" <?= $u['role_code'] === $r['code'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-dark" onclick="return confirm('¿Cambiar el rol de este usuario?')">Guardar</button>
          </form>
        </td>
        <td><?= (int) $u['is_active'] === 1 ? '<span class="badge badge-ok">Activo</span>' : '<span class="badge badge-off">Inactivo</span>' ?></td>
        <td><?= $u['last_login_at'] !== null ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : 'Nunca' ?></td>
        <td><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
        <td><a class="btn btn-sm btn-outline-dark" href="<?= e(base_url('admin/users.php?edit=' . $u['id'])) ?>">Editar</a><form method="post" action="<?= e(base_url('admin/users.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle-active"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm btn-outline-dark" type="submit" onclick="return confirm('<?= (int) $u['is_active'] === 1 ? '¿Dar de baja a este usuario?' : '¿Reactivar este usuario?' ?>')"><?= (int) $u['is_active'] === 1 ? 'Dar de baja' : 'Reactivar' ?></button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
