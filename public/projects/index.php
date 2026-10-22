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

try{$filters=Workload::filters($_GET);}catch(InvalidArgumentException $e){http_response_code(400);exit('Filtros no válidos.');}
$options=Workload::options((int)$me['id'],$allProjects,$filters);
$summary=Project::summaryFor((int)$me['id'],$allProjects,$filters);
$pages=max(1,(int)ceil($summary['projects']/25));$page=max(1,min((int)($_GET['page']??1),$pages));
$projects=Project::accessibleTo((int)$me['id'],$allProjects,25,($page-1)*25,$filters);
$filterReset='projects/index.php';$showStatus=false;
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

<?php require APP_PATH.'/app/views/partials/workload-filters.php'; ?>
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
    $progressPct = ProjectMetrics::fromCounts($project)['progress'];
    $time = ProjectMetrics::timeline($project);
    $daysLeft = $time['expired'] ? $time['days_overdue'] : $time['days_remaining'];
    $isOverdue = $time['expired'] && !ProjectMetrics::fromCounts($project)['completed'];
    $isMine = (int) $project['responsible_teacher_id'] === (int) $me['id'];
  ?>
    <article class="card project-card" data-project-progress="<?= (int)$project['id'] ?>">
      <div class="project-head">
        <h2><?= e($project['title']) ?></h2>
        <?php if ($isOverdue): ?>
          <span class="status" data-live-status style="background:var(--error-bg);color:var(--error)">Vencido</span>
        <?php elseif ($totalTasks > 0 && $doneTasks === $totalTasks): ?>
          <span class="status status-done" data-live-status>Completado</span>
        <?php elseif ($doneTasks > 0): ?>
          <span class="status status-progress" data-live-status>En progreso</span>
        <?php else: ?>
          <span class="status status-pending" data-live-status>Hasta <?= e(date('d/m/Y', strtotime($project['end_date']))) ?></span>
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
        <span class="progress-pct"><span data-live-value="progress"><?= $progressPct ?></span>%</span>
        <div class="progress" style="grid-column:1/-1"><div class="progress-bar" data-live-bar style="width:<?= $progressPct ?>%"></div></div>
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

<nav aria-label="Páginas de proyectos"><?php if($page>1):?><a href="?<?=e(http_build_query(array_merge($filters,['page'=>$page-1])))?>">Anterior</a><?php endif;?> Página <?=$page?> de <?=$pages?> <?php if($page<$pages):?><a href="?<?=e(http_build_query(array_merge($filters,['page'=>$page+1])))?>">Siguiente</a><?php endif;?></nav>
<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
