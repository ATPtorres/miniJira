<?php
/**
 * Sistema Universitario
 * public/admin/dashboard.php — Panel del Administrador.
 *
 * NOTA DE DISENO (MOCKUP): reportes, graficos y auditoria son datos
 * DEMOSTRACION. Los totales por rol si se obtienen de la BD.
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

// Sparkline SVG para reportes
$spark = [40, 55, 48, 70, 62, 80, 74, 90, 82, 95, 88, 100];
$n = count($spark); $w = 300; $h = 110; $pad = 10;
$pts = [];
foreach ($spark as $i => $v) {
    $x = $pad + ($w - 2*$pad) * ($i / ($n - 1));
    $y = $h - $pad - ($h - 2*$pad) * ($v / 100);
    $pts[] = round($x,1).','.round($y,1);
}
$poly = implode(' ', $pts);
$area = $pad.','.($h-$pad).' '.$poly.' '.round($w-$pad,1).','.($h-$pad);

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
    <div><div class="stat-value"><?= e((string) $total) ?></div><p class="stat-label">Usuarios en el sistema</p><span class="stat-trend up">&#9650; 4 este mes</span></div>
  </article>
  <?php foreach ($counts as $row): ?>
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><?= $row['code'] === 'docente' ? $icoTeach : ($row['code'] === 'estudiante' ? $icoStud : $icoShield) ?></span>
    <div><div class="stat-value"><?= e((string) $row['total']) ?></div><p class="stat-label"><?= e($row['name']) ?></p><span class="stat-trend flat">&#8212; estable</span></div>
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
      <p style="font-weight:700;color:var(--navy);margin:0 0 10px;font-size:.9rem">Avance general de proyectos</p>
      <svg viewBox="0 0 <?= $w ?> <?= $h ?>" width="100%" height="120" preserveAspectRatio="none" aria-hidden="true" style="display:block;border-radius:8px;background:var(--gray-100)">
        <polygon points="<?= $area ?>" fill="rgba(13,27,62,.12)"></polygon>
        <polyline points="<?= $poly ?>" fill="none" stroke="#0d1b3e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
      </svg>
      <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--gray-400);margin-top:6px"><span>Ene</span><span>Jun</span><span>Dic</span></div>
      <ul class="quick-links" style="margin-top:14px">
        <li><a href="#"><span class="ql-icon" aria-hidden="true"><?= $icoClip ?></span> Reporte de avance general</a></li>
        <li><a href="#"><span class="ql-icon" aria-hidden="true"><?= $icoChart ?></span> Desempeno individual</a></li>
        <li><a href="#"><span class="ql-icon" aria-hidden="true"><?= $icoDoc ?></span> Exportar a PDF</a></li>
      </ul>
    </section>

    <h2 class="section-title">Auditoria del sistema</h2>
    <section class="card">
      <ul class="audit">
        <li><span class="audit-tag">Crear</span><div><span class="audit-text">Creo el usuario "maria.lopez@universidad.edu".</span><span class="audit-time">Hace 35 min</span></div></li>
        <li><span class="audit-tag">Editar</span><div><span class="audit-text">Cambio el rol de "juan.perez" a Docente.</span><span class="audit-time">Hoy, 09:12</span></div></li>
        <li><span class="audit-tag">Eliminar</span><div><span class="audit-text">Elimino logicamente el proyecto "Inventario Lab".</span><span class="audit-time">Ayer, 17:40</span></div></li>
        <li><span class="audit-tag">Reporte</span><div><span class="audit-text">Descargo el reporte de avance mensual.</span><span class="audit-time">Ayer, 14:05</span></div></li>
        <li><span class="audit-tag">Sesion</span><div><span class="audit-text">Inicio sesion desde una nueva direccion IP.</span><span class="audit-time">Ayer, 08:30</span></div></li>
      </ul>
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
