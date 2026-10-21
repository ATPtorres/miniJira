<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$taskId = (int) ($_GET['task_id'] ?? $_POST['task_id'] ?? 0);

if ($projectId <= 0 || $taskId <= 0) {
    redirect('projects/index.php');
}
if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
    access_denied();
}

$task = Task::find($taskId);
if ($task === null || (int) $task['project_id'] !== $projectId) {
    flash_set('error', 'La tarea no existe.');
    redirect('projects/board.php?id=' . $projectId);
}

$project = Project::find($projectId);
$canManage = can('tasks.manage') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
$isAssignedStudent = can('tasks.update.assigned') && (int) $task['assigned_student_id'] === current_user_id();
$canUpload = FileController::canUpload($projectId, $taskId);

// La pagina es visible para cualquier miembro del proyecto (el tablero
// enlaza todas las tareas); el acceso ya quedo validado arriba con
// Project::userCanAccess. Subir/archivar archivos sigue restringido al
// docente o al estudiante asignado.

// Subir archivos
if (is_post()) {
    $action = post('action');
    if ($action === 'upload-file') {
        FileController::upload($projectId, $taskId);
    }
    if ($action === 'delete-file') {
        requirePermission('files.delete');
        if (!Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }
        FileController::delete($projectId);
    }
    if ($action === 'create-task-comment') CommentController::storeTask($projectId, $taskId);
    if ($action === 'delete-task-comment') CommentController::deleteTask($projectId, $taskId);
    flash_set('error', 'Acción no válida.');
    redirect('projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId);
}

$files = ProjectFile::forProject($projectId, $taskId);

$taskComments = ProjectComment::forTask($taskId);
$pageTitle = $task['title'] . ' · ' . $project['title'];
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head">
  <div>
    <p class="text-muted" style="margin:0"><a href="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>">&laquo; Volver al tablero</a></p>
    <h1><?= e($task['title']) ?></h1>
    <div class="task-detail-meta">
      <span class="priority-badge priority-<?= e($task['priority'] ?? 'medium') ?>"><?= e(Task::PRIORITIES[$task['priority']] ?? 'Media') ?></span>
      <span class="task-status-badge status-<?= e($task['status']) ?>"><?= e(ucfirst($task['status'])) ?></span>
      <span class="task-points"><?= (int) $task['points'] ?> pts</span>
      <?php if ($task['grade_result'] !== null): ?>
        <span class="task-grade"><?= (int) $task['grade_result'] ?>/<?= (int) $task['points'] ?> nota</span>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="task-detail-grid">
  <div class="task-detail-col">
    <?php if ($task['description'] !== null && $task['description'] !== ''): ?>
    <section class="card">
      <h2>Descripcion</h2>
      <p><?= e($task['description']) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($task['grade_comment'] !== null): ?>
    <section class="card">
      <h2>Comentario del docente</h2>
      <p class="task-comment"><em><?= wrap_words($task['grade_comment']) ?></em></p>
    </section>
    <?php endif; ?>

    <section class="card">
      <h2>Detalles</h2>
      <div class="task-detail-info">
        <div><strong>Asignada a:</strong> <?= e($task['student_name'] ?? 'Sin asignar') ?></div>
        <div><strong>Puntaje:</strong> <?= (int) $task['points'] ?> pts</div>
        <?php if ($task['due_date'] !== null): ?>
          <div><strong>Fecha limite:</strong> <?= e(date('d/m/Y', strtotime($task['due_date']))) ?></div>
        <?php endif; ?>
        <div><strong>Creada:</strong> <?= e(date('d/m/Y H:i', strtotime($task['created_at']))) ?></div>
      </div>
    </section>
  </div>

  <div class="task-detail-col">
    <section class="card">
      <h2>Archivos adjuntos (<?= count($files) ?>)</h2>

      <?php if ($canUpload): ?>
      <p class="text-muted">Sube tu entregable. Se registrarán automáticamente el autor, la fecha y la hora. Máximo 10 MB por archivo y 50 MB por proyecto.</p>
      <form method="post" action="<?= e(base_url('projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId)) ?>" enctype="multipart/form-data" style="margin-bottom:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload-file">
        <input type="hidden" name="task_id" value="<?= (int) $taskId ?>">
        <div class="form-group">
          <label for="file-input">Seleccionar archivo(s)</label>
          <input type="file" id="file-input" name="files[]" multiple required class="file-input">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="file-category">Categoria</label>
            <select id="file-category" name="category">
              <?php foreach (ProjectFile::CATEGORIES as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:2">
            <label for="file-description">Descripcion (opcional)</label>
            <input type="text" id="file-description" name="description" maxlength="500" placeholder="Ej: Avance parcial del informe">
          </div>
        </div>
        <button class="btn btn-primary btn-sm" type="submit" onclick="return confirm('¿Subir archivo(s)?')">Subir</button>
      </form>
      <?php elseif ($isAssignedStudent): ?>
        <p class="text-muted">La entrega está cerrada: la tarea está bloqueada o el proyecto ha vencido.</p>
      <?php endif; ?>

      <?php if ($files === []): ?>
        <p class="text-muted">No hay archivos adjuntos.</p>
      <?php else: ?>
        <div class="files-list">
          <?php foreach ($files as $file): ?>
            <div class="file-item">
              <div class="file-icon">
                <i class="fas fa-<?= e(ProjectFile::iconFor($file['mime_type'])) ?>"></i>
              </div>
              <div class="file-info">
                <div class="file-name"><?= e($file['original_name']) ?></div>
                <div class="file-meta">
                  <span class="file-category-badge cat-<?= e($file['category']) ?>"><?= e(ProjectFile::CATEGORIES[$file['category']] ?? $file['category']) ?></span>
                  <span><?= ProjectFile::formatSize((int) $file['file_size']) ?></span>
                  <span><?= e($file['uploader_name']) ?></span>
                  <span><?= e(date('d/m/Y H:i', strtotime($file['created_at']))) ?></span>
                </div>
                <?php if ($file['description']): ?>
                  <div class="file-description"><?= e($file['description']) ?></div>
                <?php endif; ?>
              </div>
              <div class="file-actions">
                <a class="btn btn-sm btn-outline-dark" href="<?= e(base_url('projects/download.php?file_id=' . $file['id'])) ?>" title="Descargar">
                  <i class="fas fa-download"></i>
                </a>
                <?php if (can('files.delete') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))): ?>
                  <form method="post" action="<?= e(base_url('projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId)) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete-file">
                    <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                    <button class="btn btn-sm btn-outline-dark" type="submit" onclick="return confirm('¿Eliminar este archivo?')">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php
$threadComments = $taskComments;
$commentTaskId = $taskId;
$commentUrl = 'projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId;
require APP_PATH . '/app/views/partials/comments.php';
?>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
