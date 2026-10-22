<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
$projectId = (int) ($_GET['id'] ?? $_POST['project_id'] ?? 0);
if ($projectId <= 0 || !Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
    access_denied();
}
if (is_post()) {
    $action = post('action');
    if ($action === 'create-task') ProjectController::createTask($projectId);
    if ($action === 'update-task-status') ProjectController::updateTaskStatus($projectId);
    if ($action === 'grade-task') ProjectController::gradeTask($projectId);
    flash_set('error', 'La accion solicitada no es valida.');
    redirect('projects/board.php?id=' . $projectId);
}

$project = Project::find($projectId);
$students = Project::students($projectId);
$tasks = Task::forProject($projectId);
$allFiles = ProjectFile::forProject($projectId);
$filesByTask = [];
foreach ($allFiles as $f) {
    if ($f['task_id'] !== null) {
        $filesByTask[(int) $f['task_id']][] = $f;
    }
}
$columns = ['todo' => 'TO DO', 'doing' => 'DOING', 'review' => 'IN REVIEW', 'done' => 'DONE'];
$grouped = array_fill_keys(array_keys($columns), []);
foreach ($tasks as $task) $grouped[$task['status']][] = $task;
$canManage = can('tasks.manage') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
$errors = form_errors();
$projectExpired = Task::isProjectExpired($projectId);
$pageTitle = 'Tablero · ' . $project['title'];
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head"><div><h1><?= e($project['title']) ?></h1><p class="text-muted">Responsable: <?= e($project['teacher_name']) ?> · <?= e(date('d/m/Y', strtotime($project['start_date']))) ?> — <?= e(date('d/m/Y', strtotime($project['end_date']))) ?></p><p><?= e($project['objectives']) ?></p></div><div class="form-actions"><a class="btn btn-outline-dark" href="<?= e(base_url('projects/index.php')) ?>">Mis proyectos</a><a class="btn btn-outline-dark" href="<?= e(base_url('projects/dashboard.php?id=' . $projectId)) ?>">Panel</a><a class="btn btn-outline-dark" href="<?= e(base_url('projects/calendar.php?id=' . $projectId)) ?>">Calendario</a><a class="btn btn-outline-dark" href="<?= e(base_url('projects/files.php?id=' . $projectId)) ?>">Archivos</a><?php if ($canManage): ?><a class="btn btn-primary" href="<?= e(base_url('projects/form.php?id=' . $projectId)) ?>">Editar proyecto</a><?php endif; ?></div></section>

<?php if ($projectExpired): ?>
<div class="alert alert-warning">El proyecto ha vencido — la edicion y creacion de tareas esta bloqueada.</div>
<?php endif; ?>

<section class="countdown-bar" data-countdown="<?= e($project['end_date'] . 'T23:59:59') ?>">
  Tiempo restante: <strong class="countdown-value">calculando...</strong>
</section>

<?php if ($canManage && !$projectExpired): ?>
<section class="card" style="margin-bottom:24px"><h2>Nueva tarea</h2><?php if ($errors !== []): ?><div class="alert alert-error"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?><form method="post" action="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="create-task"><div class="form-row"><div class="form-group"><label for="task-title">Titulo</label><input id="task-title" name="title" maxlength="160" required></div><div class="form-group"><label for="task-points">Puntaje (1-100)</label><input type="number" id="task-points" name="points" value="10" min="1" max="100" required><span class="error-msg" id="task_points-error" aria-live="polite"><?= e($errors['task_points'] ?? '') ?></span></div><div class="form-group"><label for="task-priority">Prioridad</label><select id="task-priority" name="priority"><option value="low">Baja</option><option value="medium" selected>Media</option><option value="high">Alta</option><option value="urgent">Urgente</option></select></div></div><div class="form-row"><div class="form-group"><label for="assigned_student_id">Asignar a</label><select id="assigned_student_id" name="assigned_student_id"><option value="">Sin asignar</option><?php foreach ($students as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="due_date">Fecha limite</label><input type="date" id="due_date" name="due_date" min="<?= e($project['start_date']) ?>" max="<?= e($project['end_date']) ?>"></div></div><div class="form-group"><label for="task-description">Descripcion</label><textarea id="task-description" name="description" maxlength="2000"></textarea></div><button class="btn btn-primary" type="submit" onclick="return confirm('¿Crear esta tarea en el proyecto?')">Crear tarea</button></form></section>
<?php endif; ?>

<section class="kanban-board" aria-label="Tablero de tareas" data-project-id="<?= $projectId ?>" data-can-manage="<?= $canManage ? '1' : '0' ?>" data-expired="<?= $projectExpired ? '1' : '0' ?>" data-student-allowed='<?= e(json_encode(Task::STUDENT_ALLOWED)) ?>'>
<?php foreach ($columns as $status => $label): ?>
  <section class="kanban-column status-<?= $status ?>" data-status="<?= $status ?>"><h2><?= $label ?><span><?= count($grouped[$status]) ?></span></h2><div class="kanban-tasks">
  <?php foreach ($grouped[$status] as $task): ?>
    <?php $locked = Task::locked($task); ?>
    <?php $isAssignedTask = can('tasks.update.assigned') && (int) $task['assigned_student_id'] === current_user_id(); ?>
    <?php $lockedForUser = $locked && !$canManage; ?>
    <?php $canMoveTask = $canManage || (!$locked && $isAssignedTask); ?>
    <?php $canEditGrade = $task['grade_result'] !== null || $task['status'] === 'done'; ?>
    <article class="kanban-task<?= $lockedForUser ? ' task-locked' : '' ?> priority-<?= e($task['priority'] ?? 'medium') ?>"<?= $canMoveTask ? ' draggable="true"' : '' ?> data-task-id="<?= (int) $task['id'] ?>" data-status="<?= e($task['status']) ?>"<?= $lockedForUser ? ' data-locked="1"' : '' ?>>
      <div class="task-header">
        <span class="priority-badge priority-<?= e($task['priority'] ?? 'medium') ?>"><?= e(Task::PRIORITIES[$task['priority']] ?? 'Media') ?></span>
        <h3><a href="<?= e(base_url('projects/task.php?project_id=' . $projectId . '&task_id=' . $task['id'])) ?>" class="task-title-link"><?= e($task['title']) ?></a></h3>
        <?php if ($lockedForUser): ?><span class="lock-badge">BLOQUEADA</span><?php endif; ?>
      </div>
      <div class="task-meta"><span class="task-points"><?= (int) $task['points'] ?> pts</span><?php if ($task['grade_result'] !== null): ?><span class="task-grade"><?= (int) $task['grade_result'] ?>/<?= (int) $task['points'] ?> nota</span><?php endif; ?></div>
      <?php if ($task['description'] !== null): ?><p><?= e($task['description']) ?></p><?php endif; ?>
      <?php if (!empty($filesByTask[(int) $task['id']])): ?>
        <div class="task-files">
          <span class="task-files-label"><i class="fas fa-paperclip"></i> <?= count($filesByTask[(int) $task['id']]) ?> archivo(s)</span>
          <?php foreach ($filesByTask[(int) $task['id']] as $tf): ?>
            <a class="task-file-link" href="<?= e(base_url('projects/download.php?file_id=' . $tf['id'])) ?>" title="<?= e($tf['original_name']) ?>">
              <i class="fas fa-<?= e(ProjectFile::iconFor($tf['mime_type'])) ?>"></i> <?= e(mb_substr($tf['original_name'], 0, 20)) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($task['grade_comment'] !== null): ?><p class="task-comment" title="<?= e($task['grade_comment']) ?>"><em><?= wrap_words($task['grade_comment']) ?></em></p><?php endif; ?>
      <div class="task-meta">
        <span><?= e($task['student_name'] ?? 'Sin asignar') ?></span>
        <?php if ($task['due_date'] !== null): ?>
          <span class="task-due" data-due="<?= e($task['due_date'] . 'T23:59:59') ?>">Limite <?= e(date('d/m/Y', strtotime($task['due_date']))) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($canManage): ?>
        <?php if ($task['status'] === 'review' || $canEditGrade): ?>
          <form method="post" action="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>" class="task-grade-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="grade-task">
            <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
            <div class="form-row">
              <div class="form-group" style="margin:0">
                <label for="grade-<?= (int) $task['id'] ?>">Nota (0-<?= (int) $task['points'] ?>)</label>
                <input type="number" id="grade-<?= (int) $task['id'] ?>" name="grade_result" min="0" max="<?= (int) $task['points'] ?>" required placeholder="0" value="<?= $task['grade_result'] !== null ? (int) $task['grade_result'] : '' ?>">
              </div>
              <div class="form-group" style="margin:0;flex:2">
                <label for="comment-<?= (int) $task['id'] ?>">Comentario</label>
                <textarea id="comment-<?= (int) $task['id'] ?>" name="grade_comment" maxlength="500" rows="2" placeholder="Opcional" class="auto-resize"><?= e($task['grade_comment'] ?? '') ?></textarea>
              </div>
            </div>
            <?php if ($task['status'] === 'review'): ?>
              <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('¿Calificar y completar esta tarea?')">Calificar y completar</button>
            <?php else: ?>
              <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('¿Guardar la calificacion y el comentario?')">Guardar calificacion</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>" class="task-status-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update-task-status">
          <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
          <label class="visually-hidden" for="status-<?= (int) $task['id'] ?>">Cambiar estado</label>
          <select id="status-<?= (int) $task['id'] ?>" name="status">
            <?php foreach ($columns as $value => $statusLabel): ?>
              <option value="<?= $value ?>" <?= $task['status'] === $value ? 'selected' : '' ?>><?= $statusLabel ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-dark" type="submit" onclick="return confirm('¿Mover esta tarea a este estado?')">Mover</button>
        </form>
      <?php elseif ($isAssignedTask && !$locked): ?>
          <form method="post" action="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>" class="task-status-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update-task-status">
            <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
            <label class="visually-hidden" for="status-<?= (int) $task['id'] ?>">Cambiar estado</label>
            <select id="status-<?= (int) $task['id'] ?>" name="status">
              <option value="<?= e($task['status']) ?>" selected><?= e($columns[$task['status']]) ?></option>
              <?php foreach (Task::STUDENT_ALLOWED[$task['status']] ?? [] as $allowed): ?>
                <option value="<?= e($allowed) ?>"><?= e($columns[$allowed]) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-dark" type="submit" onclick="return confirm('¿Mover esta tarea a este estado?')">Mover</button>
          </form>
      <?php elseif ($lockedForUser && $isAssignedTask): ?>
        <p class="task-locked-msg">Tarea bloqueada — plazo vencido o ya calificada.</p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div></section>
<?php endforeach; ?>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
