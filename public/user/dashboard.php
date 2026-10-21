<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
requirePermission('user.access');

if (can('admin.access')) {
    redirect('admin/dashboard.php');
}

$me = current_user();
$roleCode = (string) $me['role_code'];
$roleLabel = (string) ($me['role_name'] ?? $roleCode);
$isDocente = $roleCode === 'docente';
$isEstudiante = $roleCode === 'estudiante';

$allProjects = can('projects.manage.all');
$projects = Project::accessibleTo((int) $me['id'], $allProjects);
$summary = Project::summaryFor((int) $me['id'], $allProjects);
$taskCounts = $isEstudiante ? Task::countsForUser((int) $me['id']) : null;
$myTasks = $isEstudiante ? Task::forUser((int) $me['id']) : [];

$initials = mb_strtoupper(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1), 'UTF-8');

$now = new DateTime();
$hour = (int) $now->format('G');
$saludo = $hour < 12 ? 'Buenos dias' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');

$ico = fn (string $path) => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
$icoFolder  = $ico('<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>');
$icoTasks   = $ico('<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>');
$icoChart   = $ico('<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>');
$icoCheck   = $ico('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>');
$icoKey     = $ico('<path d="M21 2l-2 2m-7.6 7.6a5 5 0 1 0-7 7 5 5 0 0 0 7-7zm0 0L21 2m-2 2l-2 2m2-2l-4 4"/>');
$icoPlus    = $ico('<path d="M12 5v14M5 12h14"/>');
$icoClock   = $ico('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>');

$totalTasks = $summary['todo'] + $summary['doing'] + $summary['review'] + $summary['done'];
$pendingTasks = $summary['todo'] + $summary['doing'];
$progressPct = ProjectMetrics::fromCounts($summary)['progress'];

$pageTitle = 'Panel de ' . e($roleLabel);
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="dash-hero">
  <span class="avatar avatar-lg" aria-hidden="true"><?= $initials ?></span>
  <div class="dash-hero-body">
    <h1><?= e($saludo) ?>, <?= e($me['first_name']) ?> <span class="role-badge"><?= e($roleLabel) ?></span></h1>
    <p class="text-muted"><?= e($me['email']) ?> &middot; <?= e(date('d/m/Y')) ?></p>
  </div>
  <div class="dash-hero-actions">
    <?php if ($isDocente): ?>
      <a class="btn btn-light" href="<?= e(base_url('projects/form.php')) ?>">Crear proyecto</a>
    <?php endif; ?>
  </div>
</section>

<section class="cards">
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><?= $icoFolder ?></span>
    <div><div class="stat-value"><?= $summary['projects'] ?></div><p class="stat-label">Proyectos activos</p></div>
  </article>
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><?= $icoTasks ?></span>
    <div><div class="stat-value"><?= $isEstudiante ? $taskCounts['todo'] + $taskCounts['doing'] : $pendingTasks ?></div><p class="stat-label">Tareas pendientes</p></div>
  </article>
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><?= $icoChart ?></span>
    <div><div class="stat-value"><?= $isEstudiante ? $taskCounts['review'] : $summary['review'] ?></div><p class="stat-label">En revision</p></div>
  </article>
  <article class="card stat-card">
    <span class="stat-icon" aria-hidden="true"><?= $icoCheck ?></span>
    <div><div class="stat-value"><?= $isEstudiante ? $taskCounts['done'] : $summary['done'] ?></div><p class="stat-label">Completadas</p></div>
  </article>
</section>

<div class="dash-grid">
  <div class="dash-col">

    <h2 class="section-title">Mis proyectos</h2>
    <?php if ($projects === []): ?>
      <section class="card empty-state">
        <h2>Aun no hay proyectos</h2>
        <p class="text-muted"><?= $isDocente ? 'Crea tu primer proyecto y asigna un grupo de estudiantes.' : 'Un docente debe asignarte a un proyecto para que aparezca aqui.' ?></p>
        <?php if ($isDocente): ?><a class="btn btn-primary" href="<?= e(base_url('projects/form.php')) ?>" style="margin-top:14px">Crear proyecto</a><?php endif; ?>
      </section>
    <?php else: ?>
      <section class="cards">
        <?php foreach ($projects as $project):
          $pTotal = (int) $project['todo_count'] + (int) $project['doing_count'] + (int) $project['review_count'] + (int) $project['done_count'];
          $pDone = (int) $project['done_count'];
          $pPct = ProjectMetrics::fromCounts($project)['progress'];
          $time = ProjectMetrics::timeline($project);
          $daysLeft = $time['expired'] ? $time['days_overdue'] : $time['days_remaining'];
          $isOverdue = $time['expired'] && !ProjectMetrics::fromCounts($project)['completed'];
        ?>
        <article class="card project-card" data-project-progress="<?= (int)$project['id'] ?>">
          <div class="project-head">
            <h3><?= e($project['title']) ?></h3>
            <?php if ($isOverdue): ?>
              <span class="status" data-live-status style="background:var(--error-bg);color:var(--error)">Vencido</span>
            <?php elseif ($pTotal > 0 && $pDone === $pTotal): ?>
              <span class="status status-done" data-live-status>Completado</span>
            <?php elseif ($pDone > 0): ?>
              <span class="status status-progress" data-live-status>En progreso</span>
            <?php else: ?>
              <span class="status status-pending" data-live-status>Pendiente</span>
            <?php endif; ?>
          </div>
          <p class="project-meta">
            <span><?= $icoFolder ?> <?= (int) $project['student_count'] ?> estudiantes</span>
            <span><?= $icoClock ?> <?= $isOverdue ? 'Vencido hace ' . $daysLeft . ' dia(s)' : ($daysLeft > 0 ? $daysLeft . ' dia(s) restantes' : 'Vence hoy') ?></span>
          </p>
          <div class="kanban-summary">
            <span>TO DO <?= (int) $project['todo_count'] ?></span>
            <span>DOING <?= (int) $project['doing_count'] ?></span>
            <span>REVIEW <?= (int) $project['review_count'] ?></span>
            <span>DONE <?= (int) $project['done_count'] ?></span>
          </div>
          <div class="progress-row" style="margin-bottom:0">
            <span class="progress-label">Avance</span>
            <span class="progress-pct"><span data-live-value="progress"><?= $pPct ?></span>%</span>
            <div class="progress" style="grid-column:1/-1"><div class="progress-bar" data-live-bar style="width:<?= $pPct ?>%"></div></div>
          </div>
          <div class="form-actions" style="margin-top:12px">
            <a class="btn btn-primary btn-sm" href="<?= e(base_url('projects/board.php?id=' . $project['id'])) ?>">Tablero</a>
            <a class="btn btn-outline-dark btn-sm" href="<?= e(base_url('projects/dashboard.php?id=' . $project['id'])) ?>">Panel</a>
            <?php if ($isDocente && (int) $project['responsible_teacher_id'] === (int) $me['id']): ?>
              <a class="btn btn-outline-dark btn-sm" href="<?= e(base_url('projects/form.php?id=' . $project['id'])) ?>">Editar</a>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($isEstudiante && $myTasks !== []): ?>
    <h2 class="section-title">Mis tareas</h2>
    <section class="card">
      <ul class="task-list">
        <?php foreach (array_slice($myTasks, 0, 8) as $task):
          $statusLabel = match($task['status']) { 'todo' => 'TO DO', 'doing' => 'DOING', 'review' => 'IN REVIEW', 'done' => 'DONE' };
          $statusClass = match($task['status']) { 'todo' => 'status-pending', 'doing' => 'status-progress', 'review' => '', 'done' => 'status-done' };
        ?>
        <li class="task-item">
          <div class="task-main">
            <div class="task-title"><?= e($task['title']) ?></div>
            <div class="task-sub"><?= e($task['project_title']) ?><?= $task['due_date'] !== null ? ' · ' . e(date('d/m/Y', strtotime($task['due_date']))) : '' ?></div>
          </div>
          <span class="status <?= $statusClass ?>" <?php if ($task['status'] === 'review'): ?>style="background:var(--gold-pale);color:var(--gold-dark)"<?php endif ?>><?= $statusLabel ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if ($isDocente && $projects !== []): ?>
    <h2 class="section-title">Avance por proyecto</h2>
    <section class="card">
      <?php foreach ($projects as $project):
        $pTotal = (int) $project['todo_count'] + (int) $project['doing_count'] + (int) $project['review_count'] + (int) $project['done_count'];
        $pDone = (int) $project['done_count'];
        $pPct = ProjectMetrics::fromCounts($project)['progress'];
      ?>
      <div class="progress-row" data-project-progress="<?= (int)$project['id'] ?>">
        <span class="progress-label"><?= e($project['title']) ?></span>
        <span class="progress-pct"><span data-live-value="progress"><?= $pPct ?></span>%</span>
        <div class="progress" style="grid-column:1/-1"><div class="progress-bar" data-live-bar style="width:<?= $pPct ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

  </div>

  <aside class="dash-col">
    <h2 class="section-title">Accesos rapidos</h2>
    <section class="card">
      <ul class="quick-links">
        <?php if ($isDocente): ?>
        <li><a href="<?= e(base_url('projects/form.php')) ?>"><span class="ql-icon" aria-hidden="true"><?= $icoPlus ?></span> Crear proyecto</a></li>
        <?php endif; ?>
        <li><a href="<?= e(base_url('projects/index.php')) ?>"><span class="ql-icon" aria-hidden="true"><?= $icoFolder ?></span> Mis proyectos</a></li>
        <li><a href="<?= e(base_url('user/change-password.php')) ?>"><span class="ql-icon" aria-hidden="true"><?= $icoKey ?></span> Cambiar contrasena</a></li>
      </ul>
    </section>

    <h2 class="section-title">Mi cuenta</h2>
    <section class="card">
      <p><strong>Correo:</strong> <?= e($me['email']) ?></p>
      <p><strong>Rol:</strong> <?= e($roleLabel) ?></p>
      <p><strong>Ultimo acceso:</strong> <?= $me['last_login_at'] !== null ? e(date('d/m/Y H:i', strtotime($me['last_login_at']))) : 'Nunca' ?></p>
      <p><strong>Miembro desde:</strong> <?= e(date('d/m/Y', strtotime($me['created_at']))) ?></p>
    </section>
  </aside>
</div>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
