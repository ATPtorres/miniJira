<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();

$me = current_user();
$roleCode = (string) $me['role_code'];
$isAdmin = can('admin.access');
$isDocente = $roleCode === 'docente';
$isEstudiante = $roleCode === 'estudiante';
$allProjects = can('projects.manage.all');

$projects = Project::accessibleTo((int) $me['id'], $allProjects);
$summary = Project::summaryFor((int) $me['id'], $allProjects);
$canCreate = $isDocente && can('projects.create');
$pageTitle = 'Proyectos';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="dash-hero">
  <div class="dash-hero-body">
    <h1><?= $isAdmin ? 'Todos los proyectos' : ($isDocente ? 'Mis proyectos' : 'Proyectos asignados') ?></h1>
    <p class="text-muted"><?= $isAdmin ? 'Vista general de todos los proyectos del sistema.' : ($isDocente ? 'Gestiona tus proyectos universitarios y sigue el avance de cada grupo.' : 'Consulta el avance de los proyectos donde participas.') ?></p>
  </div>
  <?php if ($canCreate): ?><div class="dash-hero-actions"><a class="btn btn-light" href="<?= e(base_url('projects/form.php')) ?>">Crear proyecto</a></div><?php endif; ?>
</section>

<?php if ($projects !== []): ?>
<section class="cards project-indicators">
  <article class="card stat-card"><div><div class="stat-value"><?= $summary['projects'] ?></div><p class="stat-label">Proyectos activos</p></div></article>
  <article class="card stat-card"><div><div class="stat-value"><?= $summary['todo'] ?></div><p class="stat-label">TO DO</p></div></article>
  <article class="card stat-card"><div><div class="stat-value"><?= $summary['doing'] ?></div><p class="stat-label">DOING</p></div></article>
  <article class="card stat-card"><div><div class="stat-value"><?= $summary['review'] ?></div><p class="stat-label">IN REVIEW</p></div></article>
  <article class="card stat-card"><div><div class="stat-value"><?= $summary['done'] ?></div><p class="stat-label">DONE</p></div></article>
</section>
<?php endif; ?>

<?php if ($projects === []): ?>
  <section class="card empty-state">
    <h2>Aun no hay proyectos</h2>
    <p class="text-muted"><?= $isAdmin ? 'Aun no hay proyectos en el sistema. Los docentes pueden crear proyectos desde su panel.' : ($canCreate ? 'Crea tu primer proyecto y asigna un grupo de estudiantes.' : 'Un docente debe asignarte a un proyecto para que aparezca aqui.') ?></p>
    <?php if ($canCreate): ?><a class="btn btn-primary" href="<?= e(base_url('projects/form.php')) ?>" style="margin-top:14px">Crear proyecto</a><?php endif; ?>
  </section>
<?php else: ?>
  <section class="cards">
  <?php foreach ($projects as $project):
    $totalTasks = (int) $project['todo_count'] + (int) $project['doing_count'] + (int) $project['review_count'] + (int) $project['done_count'];
    $doneTasks = (int) $project['done_count'];
    $progressPct = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;
    $daysLeft = (int) (new DateTime())->diff(new DateTime($project['end_date']))->format('%a');
    $isOverdue = (new DateTime()) > new DateTime($project['end_date']) && $progressPct < 100;
    $isMine = (int) $project['responsible_teacher_id'] === (int) $me['id'];
  ?>
    <article class="card project-card">
      <div class="project-head">
        <h2><?= e($project['title']) ?></h2>
        <?php if ($isOverdue): ?>
          <span class="status" style="background:var(--error-bg);color:var(--error)">Vencido</span>
        <?php elseif ($progressPct >= 100): ?>
          <span class="status status-done">Completado</span>
        <?php elseif ($doneTasks > 0): ?>
          <span class="status status-progress">En progreso</span>
        <?php else: ?>
          <span class="status status-pending">Hasta <?= e(date('d/m/Y', strtotime($project['end_date']))) ?></span>
        <?php endif; ?>
      </div>
      <p class="text-muted"><?= e(mb_strimwidth($project['description'], 0, 120, '...')) ?></p>
      <p class="project-meta">
        <strong>Responsable:</strong> <?= e($project['teacher_name']) ?> · <strong>Grupo:</strong> <?= (int) $project['student_count'] ?> estudiante(s)
        · <?= $isOverdue ? 'Vencido hace ' . $daysLeft . ' dia(s)' : ($daysLeft > 0 ? $daysLeft . ' dia(s) restantes' : 'Vence hoy') ?>
      </p>
      <div class="kanban-summary">
        <span>TO DO <?= (int) $project['todo_count'] ?></span>
        <span>DOING <?= (int) $project['doing_count'] ?></span>
        <span>REVIEW <?= (int) $project['review_count'] ?></span>
        <span>DONE <?= (int) $project['done_count'] ?></span>
      </div>
      <div class="progress-row" style="margin-bottom:0">
        <span class="progress-label">Avance</span>
        <span class="progress-pct"><?= $progressPct ?>%</span>
        <div class="progress" style="grid-column:1/-1"><div class="progress-bar" style="width:<?= $progressPct ?>%"></div></div>
      </div>
      <div class="form-actions" style="margin-top:12px">
        <a class="btn btn-primary" href="<?= e(base_url('projects/board.php?id=' . $project['id'])) ?>">Tablero<?= $totalTasks > 0 ? ' (' . $totalTasks . ')' : '' ?></a>
        <a class="btn btn-outline-dark" href="<?= e(base_url('projects/dashboard.php?id=' . $project['id'])) ?>">Panel</a>
        <?php if (($canCreate && $isMine) || $isAdmin): ?>
          <a class="btn btn-outline-dark" href="<?= e(base_url('projects/form.php?id=' . $project['id'])) ?>">Editar</a>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
