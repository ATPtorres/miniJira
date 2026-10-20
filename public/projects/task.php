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

// La pagina es visible para cualquier miembro del proyecto (el tablero
// enlaza todas las tareas); el acceso ya quedo validado arriba con
// Project::userCanAccess. Subir/archivar archivos sigue restringido al
// docente o al estudiante asignado.

// Subir archivos
if (is_post()) {
    $action = post('action');
    if ($action === 'upload-file') {
        requirePermission('files.upload');
        // Docente sube a cualquier tarea; estudiante solo a la suya
        if (!$canManage && !$isAssignedStudent) {
            access_denied();
        }
        FileController::upload($projectId);
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

// Comentarios de la tarea: raices (docente) con sus respuestas
$taskComments = ProjectComment::forTask($taskId);
$commentRoots = [];
$commentReplies = [];
foreach ($taskComments as $tc) {
    if ($tc['parent_id'] === null) {
        $commentRoots[(int) $tc['id']] = $tc;
    } else {
        $commentReplies[(int) $tc['parent_id']][] = $tc;
    }
}
$commentRootCount = count($commentRoots);
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

      <?php if ($canManage || $isAssignedStudent): ?>
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

<section class="card comments-section" id="comentarios-tarea" style="margin-top:16px">
  <div class="task-comments-head">
    <h2 style="margin:0;font-size:1rem">Comentarios (<?= count($taskComments) ?>)</h2>
    <?php if ($canManage): ?>
      <span class="comment-limit-badge"><?= $commentRootCount ?>/<?= ProjectComment::MAX_ROOTS_PER_TASK ?> comentarios del docente</span>
    <?php endif; ?>
  </div>

  <?php if ($canManage): ?>
    <?php if ($commentRootCount < ProjectComment::MAX_ROOTS_PER_TASK): ?>
    <form method="post" action="<?= e(base_url('projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId)) ?>" class="comment-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create-task-comment">
      <input type="hidden" name="parent_id" value="0">
      <textarea name="body" rows="3" maxlength="2000" required placeholder="Escribe un comentario para el estudiante..."></textarea>
      <button class="btn btn-primary btn-sm" type="submit">Publicar comentario</button>
    </form>
    <?php else: ?>
      <p class="comment-limit-note">Has alcanzado el limite de <?= ProjectComment::MAX_ROOTS_PER_TASK ?> comentarios en esta tarea. Ya no puedes abrir mas comentarios (las respuestas siguen permitidas).</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($commentRoots === []): ?>
    <p class="events-empty">Todavia no hay comentarios en esta tarea.</p>
  <?php else: ?>
    <?php foreach ($commentRoots as $rid => $root):
      $rootReplies = $commentReplies[$rid] ?? [];
      $rootMine = (int) $root['author_id'] === current_user_id();
    ?>
    <div class="comment-item">
      <div class="comment-head">
        <span class="comment-author">
          <?= e($root['author_name']) ?>
          <span class="badge badge-<?= e($root['author_role']) ?>"><?= e(ucfirst((string) $root['author_role'])) ?></span>
        </span>
        <span class="comment-date"><?= e(date('d/m/Y H:i', strtotime($root['created_at']))) ?></span>
        <?php if ($rootMine || $canManage): ?>
        <form method="post" action="<?= e(base_url('projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId)) ?>" class="comment-delete-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete-task-comment">
          <input type="hidden" name="comment_id" value="<?= (int) $rid ?>">
          <button class="btn btn-link btn-sm" type="submit" onclick="return confirm('¿Eliminar este comentario y sus respuestas?')">Eliminar</button>
        </form>
        <?php endif; ?>
      </div>
      <p class="comment-body"><?= nl2br(e($root['body'])) ?></p>

      <?php if ($rootReplies !== []): ?>
      <div class="comment-replies">
        <?php foreach ($rootReplies as $reply):
          $replyMine = (int) $reply['author_id'] === current_user_id();
        ?>
        <div class="comment-item comment-reply">
          <div class="comment-head">
            <span class="comment-author">
              <?= e($reply['author_name']) ?>
              <span class="badge badge-<?= e($reply['author_role']) ?>"><?= e(ucfirst((string) $reply['author_role'])) ?></span>
            </span>
            <span class="comment-date"><?= e(date('d/m/Y H:i', strtotime($reply['created_at']))) ?></span>
            <?php if ($replyMine || $canManage): ?>
            <form method="post" action="<?= e(base_url('projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId)) ?>" class="comment-delete-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete-task-comment">
              <input type="hidden" name="comment_id" value="<?= (int) $reply['id'] ?>">
              <button class="btn btn-link btn-sm" type="submit" onclick="return confirm('¿Eliminar esta respuesta?')">Eliminar</button>
            </form>
            <?php endif; ?>
          </div>
          <p class="comment-body"><?= nl2br(e($reply['body'])) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId)) ?>" class="comment-reply-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create-task-comment">
        <input type="hidden" name="parent_id" value="<?= (int) $rid ?>">
        <textarea name="body" rows="2" maxlength="2000" required placeholder="Escribe tu respuesta..."></textarea>
        <button class="btn btn-sm btn-outline-dark" type="submit">Responder</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
