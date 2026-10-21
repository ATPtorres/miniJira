<?php
/**
 * Sistema Universitario
 * public/admin/dashboard.php — Panel del Administrador.
 *
 * Indicadores reales de usuarios y proyectos visibles para el administrador.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
requirePermission('admin.access');

$me     = current_user();
$counts = User::countByRole();
$total  = array_sum(array_map(static fn (array $r): int => (int) $r['total'], $counts));
$initials = mb_strtoupper(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1), 'UTF-8');

$now  = new DateTime();
$hour = (int) $now->format('G');
$saludo = $hour < 12 ? 'Buenos dias' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');

$maxCount = (int) max(1, ...array_map(static fn (array $r): int => (int) $r['total'], $counts));

$projects = Project::accessibleTo(current_user_id(), can('projects.manage.all'));
$projectSummary = ProjectMetrics::fromCounts(Project::summaryFor(current_user_id(), can('projects.manage.all')));

// Iconos SVG
$ico = fn (string $path) => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
$icoUsers  = $ico('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>');
$icoTeach  = $ico('<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 0 3-2 5-2s2 2 5 2v-5"/>');
$icoStud   = $icoTeach;
$icoShield = $ico('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>');
$icoKey    = $ico('<path d="M21 2l-2 2m-7.6 7.6a5 5 0 1 0-7 7 5 5 0 0 0 7-7zm0 0L21 2m-2 2l-2 2m2-2l-4 4"/>');
$icoPlus   = $ico('<path d="M12 5v14M5 12h14"/>');
$icoList   = $ico('<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>');
$icoChart  = $ico('<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>');
$icoClip   = $ico('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>');
$icoDoc    = $icoClip;

$pageTitle = 'Panel de Administracion';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="dash-hero">
  <span class="avatar avatar-lg" aria-hidden="true"><?= e($initials) ?></span>
  <div class="dash-hero-body">
    <h1><?= e($saludo) ?>, <?= e($me['first_name']) ?> <span class="role-badge">Administrador</span></h1>
    <p class="text-muted"><?= e($me['email']) ?> &middot; <?= e(date('d/m/Y')) ?></p>
  </div>
  <div class="dash-hero-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/users.php')) ?>">Gestionar usuarios</a>
    <a class="btn btn-outline" href="<?= e(base_url('admin/academic.php')) ?>">Gestion academica</a>
    <?php if (can('roles.manage')): ?>
    <a class="btn btn-outline" href="<?= e(base_url('admin/roles.php')) ?>">Roles y permisos</a>
    <?php endif; ?>
  </div>
</section>

<section class="cards">
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><?= $icoUsers ?></span>
    <div><div class="stat-value"><?= e((string) $total) ?></div><p class="stat-label">Usuarios en el sistema</p></div>
  </article>
  <?php foreach ($counts as $row): ?>
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><?= $row['code'] === 'docente' ? $icoTeach : ($row['code'] === 'estudiante' ? $icoStud : $icoShield) ?></span>
    <div><div class="stat-value"><?= e((string) $row['total']) ?></div><p class="stat-label"><?= e($row['name']) ?></p></div>
  </article>
  <?php endforeach; ?>
</section>

<div class="dash-grid">
  <div class="dash-col">
    <h2 class="section-title">Gestion del sistema</h2>
    <section class="cards">
      <a class="card card-link" href="<?= e(base_url('admin/users.php')) ?>">
        <h2>Gestionar docentes</h2>
        <p>Crear, editar, asignar rol y dar de baja (CRUD).</p>
        <span class="btn btn-sm btn-outline-dark">Abrir</span>
      </a>
      <?php if (can('roles.manage')): ?>
      <a class="card card-link" href="<?= e(base_url('admin/roles.php')) ?>">
        <h2>Roles y permisos</h2>
        <p>Crear roles y definir el acceso a cada modulo.</p>
        <span class="btn btn-sm btn-outline-dark">Abrir</span>
      </a>
      <?php endif; ?>
      <a class="card card-link" href="<?= e(base_url('admin/academic.php')) ?>">
        <h2>Gestion academica</h2>
        <p>Carreras, planes, materias, grupos, asignaciones y matriculas.</p>
        <span class="btn btn-sm btn-outline-dark">Abrir</span>
      </a>
      <a class="card card-link" href="<?= e(base_url('admin/users.php')) ?>">
        <h2>Gestionar estudiantes</h2>
        <p>Crear, editar, asignar rol y dar de baja (CRUD).</p>
        <span class="btn btn-sm btn-outline-dark">Abrir</span>
      </a>
    </section>

    <h2 class="section-title">Distribucion de cuentas</h2>
    <section class="card">
      <div class="bar-chart">
        <?php foreach ($counts as $row): ?>
        <div class="bar-row">
          <span class="bar-label"><?= e($row['name']) ?></span>
          <span class="bar-track"><span class="bar-fill" style="width:<?= e((string) round((int) $row['total'] / $maxCount * 100)) ?>%"></span></span>
          <span class="bar-value"><?= e((string) $row['total']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <aside class="dash-col">
    <h2 class="section-title">Reportes y desempeno</h2>
    <section class="card">
      <h3>Distribucion real de tareas</h3>
      <div data-live-summary><?= ProjectCharts::states($projectSummary) ?></div>
      <p><a class="btn btn-primary" href="<?= e(base_url('projects/analytics.php')) ?>">Analitica e informes PDF</a></p>
    </section>
    <section class="card"><h3>Avance por proyecto</h3>
      <?php if (!$projects): ?><p>No hay proyectos disponibles.</p><?php endif; ?>
      <?php foreach ($projects as $p): $m = ProjectMetrics::fromCounts($p); ?>
      <div class="progress-row" data-project-progress="<?= (int)$p['id'] ?>">
        <a href="<?= e(base_url('projects/analytics.php?id='.$p['id'])) ?>"><?= e($p['title']) ?></a>
        <span><span data-live-value="progress"><?= $m['progress'] ?></span>%</span>
        <div class="progress" style="grid-column:1/-1"><div class="progress-bar" data-live-bar style="width:<?= $m['progress'] ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </section>

    <h2 class="section-title">Acciones</h2>
    <section class="card">
      <ul class="quick-links">
        <li><a href="<?= e(base_url('admin/users.php')) ?>"><span class="ql-icon" aria-hidden="true"><?= $icoPlus ?></span> Crear usuario</a></li>
        <li><a href="<?= e(base_url('admin/users.php')) ?>"><span class="ql-icon" aria-hidden="true"><?= $icoList ?></span> Ver directorio</a></li>
        <?php if (can('roles.manage')): ?>
        <li><a href="<?= e(base_url('admin/roles.php')) ?>"><span class="ql-icon" aria-hidden="true"><?= $icoShield ?></span> Gestionar roles</a></li>
        <?php endif; ?>
        <li><a href="<?= e(base_url('user/change-password.php')) ?>"><span class="ql-icon" aria-hidden="true"><?= $icoKey ?></span> Mi contrasena</a></li>
      </ul>
    </section>
  </aside>
</div>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
