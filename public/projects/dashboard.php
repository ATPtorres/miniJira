<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
$projectId = (int) ($_GET['id'] ?? 0);
if ($projectId <= 0 || !Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
    access_denied();
}

if (is_post()) {
    requireCsrf();
    $action = (string) post('action');
    if ($action === 'create-comment') {
        CommentController::store($projectId);
    }
    if ($action === 'delete-comment') {
        CommentController::delete($projectId);
    }
    flash_set('error', 'La accion solicitada no es valida.');
    redirect('projects/dashboard.php?id=' . $projectId);
}

$project = Project::find($projectId);
if ($project === null) {
    flash_set('error', 'El proyecto solicitado no existe.');
    redirect('projects/index.php');
}

$students = Project::students($projectId);
$tasks = Task::forProject($projectId);
$canManage = can('tasks.manage') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));

$comments = ProjectComment::forProject($projectId, current_user_id(), can('projects.manage.all'));
$commentFiles = [];
foreach ($comments as $c) {
    $commentFiles[(int) $c['id']] = ProjectFile::forComment((int) $c['id']);
}

$metrics = ProjectMetrics::forProject($projectId);
$totalTasks = $metrics['total'];
$todoCount = $metrics['todo'];
$doingCount = $metrics['doing'];
$reviewCount = $metrics['review'];
$doneCount = $metrics['done'];
$progressPct = $metrics['progress'];
$timeline = ProjectMetrics::timeline($project);
$timelinePct = $timeline['progress'];
$daysRemaining = $timeline['days_remaining'];
$isOverdue = $timeline['expired'] && !$metrics['completed'];
$projectExpired = $timeline['expired'];

$studentTasks = [];
foreach ($tasks as $task) {
    $sid = (int) $task['assigned_student_id'];
    if ($sid > 0) {
        $studentTasks[$sid][$task['status']] = ($studentTasks[$sid][$task['status']] ?? 0) + 1;
    }
}

$pageTitle = 'Panel · ' . $project['title'];
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head">
  <div>
    <h1><?= e($project['title']) ?></h1>
    <p class="text-muted">Panel de indicadores del proyecto</p>
  </div>
  <div class="form-actions">
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/index.php')) ?>">Mis proyectos</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>">Tablero</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/calendar.php?id=' . $projectId)) ?>">Calendario</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/files.php?id=' . $projectId)) ?>">Archivos</a>
    <?php if ($canManage && !$projectExpired): ?><a class="btn btn-primary" href="<?= e(base_url('projects/form.php?id=' . $projectId)) ?>">Editar proyecto</a><?php endif; ?>
  </div>
</section>

<?php if ($projectExpired): ?>
<div class="alert alert-warning">El proyecto ha vencido — la edicion y creacion de tareas esta bloqueada.</div>
<?php endif; ?>

<div data-project-progress="<?= $projectId ?>">
<section class="countdown-bar" data-countdown="<?= e($project['end_date'] . 'T23:59:59') ?>">
  Tiempo restante: <strong class="countdown-value">calculando...</strong>
</section>

<section class="cards project-indicators">
  <article class="card stat-card"><div><div class="stat-value" data-live-value="total"><?= $totalTasks ?></div><p class="stat-label">Tareas totales</p></div></article>
  <article class="card stat-card"><div><div class="stat-value" data-live-value="todo"><?= $todoCount ?></div><p class="stat-label">TO DO</p></div></article>
  <article class="card stat-card"><div><div class="stat-value" data-live-value="doing"><?= $doingCount ?></div><p class="stat-label">DOING</p></div></article>
  <article class="card stat-card"><div><div class="stat-value" data-live-value="review"><?= $reviewCount ?></div><p class="stat-label">IN REVIEW</p></div></article>
  <article class="card stat-card"><div><div class="stat-value" data-live-value="done"><?= $doneCount ?></div><p class="stat-label">DONE</p></div></article>
</section>

<div class="dash-grid">
  <div class="dash-col">
    <h2 class="section-title">Informacion del proyecto</h2>
    <section class="card">
      <div class="project-info-grid">
        <div><strong>Responsable:</strong> <?= e($project['teacher_name']) ?></div>
        <div><strong>Estudiantes:</strong> <?= count($students) ?> asignados</div>
        <div><strong>Inicio:</strong> <?= e(date('d/m/Y', strtotime($project['start_date']))) ?></div>
        <div><strong>Fin:</strong> <?= e(date('d/m/Y', strtotime($project['end_date']))) ?></div>
      </div>
      <div style="margin-top:16px"><strong>Descripcion:</strong><p class="project-desc-obj"><?= e($project['description']) ?></p></div>
      <div style="margin-top:12px"><strong>Objetivos:</strong><p class="project-desc-obj"><?= e($project['objectives']) ?></p></div>
    </section>

    <h2 class="section-title">Avance general</h2>
    <section class="card">
      <div class="progress-row"><span class="progress-label">Progreso de tareas</span><span class="progress-pct"><span data-live-value="progress"><?= $progressPct ?></span>%</span><div class="progress" style="grid-column:1/-1"><div class="progress-bar" data-live-bar style="width:<?= $progressPct ?>%"></div></div></div>
      <div class="progress-row"><span class="progress-label">Avance temporal</span><span class="progress-pct"><?= $timelinePct ?>%</span><div class="progress" style="grid-column:1/-1"><div class="progress-bar" style="width:<?= $timelinePct ?>%;background:linear-gradient(90deg,var(--gold),var(--gold-dark))"></div></div></div>
      <?php if ($isOverdue): ?><p style="color:var(--error);font-weight:700;font-size:.88rem;margin-top:8px">El proyecto ha superado la fecha limite.</p><?php elseif ($daysRemaining > 0): ?><p style="color:var(--gray-600);font-size:.88rem;margin-top:8px">Faltan <?= $daysRemaining ?> dia(s) para la fecha limite.</p><?php else: ?><p style="color:var(--success);font-weight:700;font-size:.88rem;margin-top:8px">Fecha limite: hoy.</p><?php endif; ?>
    </section>

    <h2 class="section-title">Distribucion de tareas</h2>
    <section class="card">
      <div class="kanban-summary" style="margin:0">
        <span style="background:var(--gray-600);color:#fff">TO DO <?= $todoCount ?></span>
        <span style="background:var(--info);color:#fff">DOING <?= $doingCount ?></span>
        <span style="background:var(--gold);color:var(--navy)">IN REVIEW <?= $reviewCount ?></span>
        <span style="background:var(--success);color:#fff">DONE <?= $doneCount ?></span>
      </div>
    </section>

    <h2 class="section-title">Estudiantes y sus tareas</h2>
    <section class="card">
      <?php if ($students === []): ?>
        <p class="text-muted">No hay estudiantes asignados.</p>
      <?php else: ?>
        <ul class="task-list">
          <?php foreach ($students as $student):
            $sTasks = $studentTasks[(int) $student['id']] ?? [];
            $sTotal = array_sum($sTasks);
            $sDone = $sTasks['done'] ?? 0;
            $sPct = $sTotal > 0 ? ProjectMetrics::percentage($sDone, $sTotal) : 0;
          ?>
          <li class="task-item">
            <div class="task-main">
              <div class="task-title"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
              <div class="task-sub"><?= $sTotal ?> tarea(s) · <?= $sDone ?> completada(s)</div>
            </div>
            <div style="min-width:100px;text-align:right">
              <span class="progress-pct"><?= $sPct ?>%</span>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="dash-col">
    <h2 class="section-title">Tareas por estado</h2>
    <?php
    $statusConfig = [
        'todo' => ['label' => 'TO DO', 'color' => 'var(--gray-600)'],
        'doing' => ['label' => 'DOING', 'color' => 'var(--info)'],
        'review' => ['label' => 'IN REVIEW', 'color' => 'var(--gold)'],
        'done' => ['label' => 'DONE', 'color' => 'var(--success)'],
    ];
    foreach ($statusConfig as $status => $cfg):
        $statusTasks = array_filter($tasks, fn(array $t): bool => $t['status'] === $status);
    ?>
    <section class="card" style="margin-bottom:16px">
      <p style="font-weight:700;color:<?= $cfg['color'] ?>;margin:0 0 10px;font-size:.9rem"><?= $cfg['label'] ?> (<?= count($statusTasks) ?>)</p>
      <?php if ($statusTasks === []): ?>
        <p class="text-muted" style="font-size:.84rem">Sin tareas.</p>
      <?php else: ?>
        <ul class="task-list">
          <?php foreach ($statusTasks as $task):
            $locked = Task::isLocked((int) $task['id']) && !$canManage;
          ?>
          <li class="task-item<?= $locked ? ' task-item-locked' : '' ?>" style="padding:10px 12px">
            <div class="task-main">
              <div class="task-title" style="font-size:.86rem">
                <span class="priority-dot priority-<?= e($task['priority'] ?? 'medium') ?>"></span>
                <?= e($task['title']) ?>
                <?php if ($locked): ?><span class="lock-badge-sm">BLOQ.</span><?php endif; ?>
              </div>
              <div class="task-sub"><?= e($task['student_name'] ?? 'Sin asignar') ?><?= $task['due_date'] !== null ? ' · ' . e(date('d/m/Y', strtotime($task['due_date']))) : '' ?></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php endforeach; ?>
  </aside>
</div>

</div>
<?php
[$from, $to] = ProjectMetrics::dateRange(null, null);
require APP_PATH . '/app/views/partials/project-charts.php';
?>

<h2 class="section-title" id="comentarios">Comentarios <span class="text-muted">(<?= count($comments) ?>)</span></h2>
<section class="card comments-section">
  <form method="post" action="<?= e(base_url('projects/dashboard.php?id=' . $projectId)) ?>" enctype="multipart/form-data" class="comment-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create-comment">
    <textarea name="body" rows="3" maxlength="2000" required placeholder="Escribe un comentario..."></textarea>
    <div class="comment-form-row">
      <label class="comment-field">
        <span>Visibilidad</span>
        <select name="visibility">
          <option value="project">Todos los del proyecto</option>
          <option value="private">Privado (solo tu y el docente responsable)</option>
        </select>
      </label>
      <label class="comment-anon">
        <input type="checkbox" name="is_anonymous" value="1"> Publicar como anonimo
      </label>
      <label class="comment-field comment-file-field">
        <span>Adjuntos (imagen, video, audio o documento, max 10 MB c/u)</span>
        <input type="file" name="files[]" multiple
               accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar">
      </label>
      <button type="submit" class="btn btn-primary">Publicar</button>
    </div>
  </form>

  <?php if ($comments === []): ?>
    <p class="text-muted" style="font-size:.85rem;margin-top:12px">Todavia no hay comentarios.</p>
  <?php else: ?>
    <ul class="comment-list">
      <?php foreach ($comments as $c):
        $cid = (int) $c['id'];
        $isMine = (int) $c['author_id'] === current_user_id();
        $cFiles = $commentFiles[$cid] ?? [];
        $anon = (int) $c['is_anonymous'] === 1;
      ?>
      <li class="comment-item<?= $c['visibility'] === 'private' ? ' comment-private' : '' ?>">
        <div class="comment-head">
          <span class="comment-author">
            <?php if ($anon): ?>
              Anonimo<?= $isMine ? ' (tu)' : '' ?>
            <?php else: ?>
              <?= e($c['author_name'] ?? 'Usuario') ?>
              <span class="badge badge-<?= e($c['author_role']) ?>"><?= e(ucfirst((string) $c['author_role'])) ?></span>
            <?php endif; ?>
          </span>
          <?php if ($c['visibility'] === 'private'): ?><span class="badge badge-private">Privado</span><?php endif; ?>
          <span class="comment-date"><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></span>
          <?php if ($isMine || $canManage): ?>
          <form method="post" action="<?= e(base_url('projects/dashboard.php?id=' . $projectId)) ?>" class="comment-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete-comment">
            <input type="hidden" name="comment_id" value="<?= $cid ?>">
            <button type="submit" class="btn btn-link btn-sm" onclick="return confirm('Eliminar este comentario?')">Eliminar</button>
          </form>
          <?php endif; ?>
        </div>
        <p class="comment-body"><?= nl2br(e($c['body'])) ?></p>
        <?php if ($cFiles !== []): ?>
        <div class="comment-attachments">
          <?php foreach ($cFiles as $f): ?>
          <a class="comment-attachment" href="<?= e(base_url('projects/download.php?file_id=' . (int) $f['id'])) ?>"
             title="<?= e($f['original_name']) ?>">
            <i class="fas fa-<?= e(ProjectFile::iconFor($f['mime_type'])) ?>"></i>
            <span><?= e($f['original_name']) ?></span>
            <small>(<?= e(ProjectFile::formatSize((int) $f['file_size'])) ?>)</small>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
