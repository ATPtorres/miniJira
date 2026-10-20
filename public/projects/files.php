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
    if ($action === 'upload-file') FileController::upload($projectId);
    if ($action === 'delete-file') FileController::delete($projectId);
    flash_set('error', 'La acción solicitada no es válida.');
    redirect('projects/files.php?id=' . $projectId);
}

$project = Project::find($projectId);
$canManage = can('files.upload') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
$canDelete = can('files.delete') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
$files = ProjectFile::forProject($projectId);
$tasks = Task::forProject($projectId);
$totalSize = ProjectFile::totalSize($projectId);
$pageTitle = 'Archivos · ' . $project['title'];
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head">
  <div>
    <h1><?= e($project['title']) ?></h1>
    <p class="text-muted">Archivos adjuntos del proyecto · <?= ProjectFile::formatSize($totalSize) ?> de 50 MB usados</p>
  </div>
  <div class="form-actions">
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/index.php')) ?>">Mis proyectos</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>">Tablero</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/dashboard.php?id=' . $projectId)) ?>">Panel</a>
    <?php if ($canManage): ?>
      <a class="btn btn-primary" href="<?= e(base_url('projects/form.php?id=' . $projectId)) ?>">Editar proyecto</a>
    <?php endif; ?>
  </div>
</section>

<?php if ($canManage): ?>
<section class="card" style="margin-bottom:24px">
  <h2>Subir archivos</h2>
  <p class="text-muted" style="font-size:.84rem;margin-top:0">Tipos permitidos: PDF, Word, PowerPoint, Excel, imágenes, ZIP/RAR, TXT. Máximo 10 MB por archivo, 50 MB total.</p>
  <form method="post" action="<?= e(base_url('projects/files.php?id=' . $projectId)) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload-file">
    <div class="form-row">
      <div class="form-group" style="flex:2">
        <label for="file-input">Seleccionar archivo(s)</label>
        <input type="file" id="file-input" name="files[]" multiple required class="file-input">
      </div>
      <div class="form-group">
        <label for="file-category">Categoría</label>
        <select id="file-category" name="category">
          <?php foreach (ProjectFile::CATEGORIES as $key => $label): ?>
            <option value="<?= $key ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:2">
        <label for="file-task">Asignar a tarea (opcional)</label>
        <select id="file-task" name="task_id">
          <option value="">Recurso general del proyecto</option>
          <?php foreach ($tasks as $task): ?>
            <option value="<?= (int) $task['id'] ?>"><?= e($task['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:2">
        <label for="file-description">Descripción (opcional)</label>
        <input type="text" id="file-description" name="description" maxlength="500" placeholder="Ej: Formato de informe final">
      </div>
    </div>
    <button class="btn btn-primary" type="submit" onclick="return confirm('¿Subir archivo(s)?')">Subir archivo(s)</button>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <h2>Archivos del proyecto (<?= count($files) ?>)</h2>
  <?php if ($files === []): ?>
    <p class="text-muted">No hay archivos adjuntos todavía.</p>
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
              <?php if ($file['task_title']): ?>
                <span class="file-task-link">Tarea: <?= e($file['task_title']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($file['description']): ?>
              <div class="file-description"><?= e($file['description']) ?></div>
            <?php endif; ?>
          </div>
          <div class="file-actions">
            <a class="btn btn-sm btn-outline-dark" href="<?= e(base_url('projects/download.php?file_id=' . $file['id'])) ?>" title="Descargar">
              <i class="fas fa-download"></i> Descargar
            </a>
            <?php if ($canDelete): ?>
              <form method="post" action="<?= e(base_url('projects/files.php?id=' . $projectId)) ?>" style="display:inline">
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

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
