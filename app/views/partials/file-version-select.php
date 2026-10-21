<div class="form-group">
  <label for="<?= e($versionInputId) ?>">Tipo de subida</label>
  <select name="replaces_file_id" id="<?= e($versionInputId) ?>" data-file-version>
    <option value="0">Archivo nuevo e independiente</option>
    <?php foreach ($versionFiles as $versionFile): ?>
      <?php if (!FileController::canVersion($versionFile)) continue; ?>
      <option value="<?= (int) $versionFile['id'] ?>" data-task-id="<?= (int) $versionFile['task_id'] ?>">
        Nueva versión de <?= e($versionFile['original_name']) ?> (v<?= (int) $versionFile['version_number'] ?>)<?= empty($versionFile['task_title']) ? '' : ' · ' . e($versionFile['task_title']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <p class="text-muted">Para actualizar un archivo, selecciona su versión actual y sube un solo archivo. Las versiones anteriores se conservarán y ocuparán espacio.</p>
</div>
<script src="<?= e(asset('js/file-versions.js')) ?>" defer></script>
