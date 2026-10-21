<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
requireAuth();
$projectId = (int) ($_GET['id'] ?? 0);
if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) access_denied();
$taskId = (int) ($_GET['task_id'] ?? 0) ?: null;
$fileId = (int) ($_GET['file_id'] ?? 0) ?: null;
if ($taskId !== null) {
    $task = Task::find($taskId);
    if ($task === null || (int) $task['project_id'] !== $projectId) access_denied();
}
$rootId = null;
if ($fileId !== null) {
    $file = ProjectFile::find($fileId, true);
    if ($file === null || (int) $file['project_id'] !== $projectId || $file['comment_id'] !== null
        || ($taskId !== null && (int) $file['task_id'] !== $taskId)) access_denied();
    $rootId = (int) ($file['version_root_id'] ?? $file['id']);
}
$project = Project::find($projectId);
$history = ProjectFile::history($projectId, $taskId, $rootId, (int) ($_GET['page'] ?? 1));
$tasks = Task::forProject($projectId);
$pageTitle = 'Historial de archivos y entregas · ' . $project['title'];
$pageParams = ['id' => $projectId];
if ($taskId !== null) $pageParams['task_id'] = $taskId;
if ($fileId !== null) $pageParams['file_id'] = $fileId;
require APP_PATH . '/app/views/partials/header.php';
?>
<section class="panel-head">
  <div>
    <h1>Historial de archivos y entregas</h1>
    <p><?= e($project['title']) ?><?= $taskId === null ? '' : ' · ' . e($task['title']) ?></p>
  </div>
  <a class="btn btn-outline-dark" href="<?= e(base_url($taskId === null ? 'projects/files.php?id=' . $projectId : 'projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId)) ?>">Volver a archivos</a>
</section>
<section class="card">
  <p>Consulta cada versión, su autor y la fecha de entrega. Los adjuntos de comentarios están en su conversación.</p>
  <form method="get" class="form-row">
    <input type="hidden" name="id" value="<?= $projectId ?>">
    <label>Filtrar por tarea
      <select name="task_id">
        <option value="">Todo el proyecto</option>
        <?php foreach ($tasks as $option): ?>
          <option value="<?= (int) $option['id'] ?>" <?= (int) $option['id'] === $taskId ? 'selected' : '' ?>><?= e($option['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-primary" type="submit">Filtrar</button>
    <?php if ($fileId !== null): ?><a href="<?= e(base_url('projects/file-history.php?' . http_build_query(array_diff_key($pageParams, ['file_id' => true])))) ?>">Ver todos los archivos</a><?php endif; ?>
  </form>
  <p><?= $history['total'] ?> versión(es) registradas<?= $fileId === null ? '' : ' de este archivo' ?>.</p>
  <?php if ($history['items'] === []): ?><p>No hay entregas ni archivos registrados para este filtro.</p><?php else: ?>
  <div style="overflow-x:auto">
    <table class="table">
      <thead><tr><th>Archivo / descripción</th><th>Tarea</th><th>Versión</th><th>Autor</th><th>Fecha y hora</th><th>Tamaño</th><th>Estado</th><th>Descarga</th></tr></thead>
      <tbody>
      <?php foreach ($history['items'] as $entry): ?>
        <tr>
          <td><?= e($entry['original_name']) ?><?php if ($entry['description']): ?><p class="text-muted"><?= e($entry['description']) ?></p><?php endif; ?></td>
          <td><?= e($entry['task_title'] ?? 'Recurso del proyecto') ?></td>
          <td>v<?= (int) $entry['version_number'] ?></td>
          <td><?= e($entry['uploader_name']) ?></td>
          <td><?= e(date('d/m/Y H:i:s', strtotime($entry['created_at']))) ?></td>
          <td><?= e(ProjectFile::formatSize((int) $entry['file_size'])) ?></td>
          <td>
            <?php if ($entry['deleted_at'] !== null): ?>
              Eliminado el <?= e(date('d/m/Y H:i:s', strtotime($entry['deleted_at']))) ?>
              <?php if ($entry['deleted_by_name']): ?> por <?= e($entry['deleted_by_name']) ?><?php endif; ?>
            <?php else: ?><?= (int) $entry['is_latest'] === 1 ? 'Actual' : 'Anterior' ?><?php endif; ?>
          </td>
          <td><?php if ($entry['deleted_at'] === null): ?>
            <a href="<?= e(base_url('projects/download.php?file_id=' . $entry['id'])) ?>">Descargar v<?= (int) $entry['version_number'] ?></a>
          <?php else: ?>No disponible<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <nav aria-label="Páginas del historial">
    <?php if ($history['page'] > 1): ?><a href="<?= e(base_url('projects/file-history.php?' . http_build_query($pageParams + ['page' => $history['page'] - 1]))) ?>">Anterior</a><?php endif; ?>
    <span>Página <?= $history['page'] ?> de <?= $history['pages'] ?></span>
    <?php if ($history['page'] < $history['pages']): ?><a href="<?= e(base_url('projects/file-history.php?' . http_build_query($pageParams + ['page' => $history['page'] + 1]))) ?>">Siguiente</a><?php endif; ?>
  </nav>
</section>
<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
