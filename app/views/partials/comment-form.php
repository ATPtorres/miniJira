<?php $formId = ($isTaskThread ? 'task-' : 'project-') . ($replyTo['id'] ?? 0); ?>
<form method="post" action="<?= e(base_url($commentUrl)) ?>" enctype="multipart/form-data" class="comment-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="<?= e($createCommentAction) ?>">
  <input type="hidden" name="parent_id" value="<?= (int) ($replyTo['id'] ?? 0) ?>">
  <label for="comment-body-<?= e($formId) ?>"><?= $replyTo === null ? 'Nuevo comentario' : 'Responder al hilo' ?></label>
  <textarea id="comment-body-<?= e($formId) ?>" name="body" rows="3" maxlength="2000" required placeholder="<?= $replyTo === null ? 'Escribe un comentario…' : 'Escribe tu respuesta…' ?>"></textarea>
  <div class="comment-form-row">
    <?php if (!$isTaskThread && $replyTo === null): ?>
      <label class="comment-field">Visibilidad
        <select name="visibility">
          <option value="project">Todos los del proyecto</option>
          <option value="private">Privado (solo tú y el docente responsable)</option>
        </select>
      </label>
    <?php elseif (!$isTaskThread && $replyTo['visibility'] === 'private'): ?>
      <span class="badge badge-private">Respuesta privada: solo participantes del hilo</span>
    <?php endif; ?>
    <?php if (!$isTaskThread): ?>
      <label class="comment-anon"><input type="checkbox" name="is_anonymous" value="1"> Publicar como anónimo</label>
    <?php endif; ?>
    <label class="comment-field comment-file-field">Adjuntos (opcional)
      <input type="file" name="files[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar">
    </label>
    <button type="submit" class="btn btn-primary"><?= $replyTo === null ? 'Publicar comentario' : 'Responder' ?></button>
  </div>
</form>
