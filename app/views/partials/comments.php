<?php
/** $threadComments, $commentTaskId, $commentUrl y $canManage provienen de la página. */
$roots = [];
$replies = [];
foreach ($threadComments as $entry) {
    if ($entry['parent_id'] === null) $roots[(int) $entry['id']] = $entry;
    else $replies[(int) $entry['parent_id']][] = $entry;
}
$isTaskThread = $commentTaskId !== null;
$createCommentAction = $isTaskThread ? 'create-task-comment' : 'create-comment';
$deleteCommentAction = $isTaskThread ? 'delete-task-comment' : 'delete-comment';
?>
<section class="card comments-section" id="<?= $isTaskThread ? 'comentarios-tarea' : 'comentarios' ?>" style="margin-top:16px">
  <h2>Comentarios (<?= count($threadComments) ?>)</h2>
  <p class="text-muted">Abre un hilo o responde a una conversación. Puedes adjuntar documentos, imágenes, audio o video (10 MB por archivo, 50 MB por proyecto).</p>
  <?php if (!$isTaskThread || ($canManage && count($roots) < ProjectComment::MAX_ROOTS_PER_TASK)): ?>
    <?php $replyTo = null; require APP_PATH . '/app/views/partials/comment-form.php'; ?>
  <?php elseif ($canManage): ?>
    <p class="comment-limit-note">Límite de <?= ProjectComment::MAX_ROOTS_PER_TASK ?> hilos alcanzado. Puedes responder a los existentes.</p>
  <?php else: ?>
    <p class="text-muted">El docente abre los hilos de la tarea; puedes participar con «Responder».</p>
  <?php endif; ?>
  <?php if ($roots === []): ?><p class="text-muted">Todavía no hay comentarios.</p><?php endif; ?>
  <?php foreach ($roots as $rootId => $rootComment): ?>
    <article class="comment-item" id="hilo-<?= $rootId ?>">
      <?php $comment = $rootComment; require APP_PATH . '/app/views/partials/comment-item.php'; ?>
      <div class="comment-replies" aria-label="Respuestas al comentario">
        <?php foreach ($replies[$rootId] ?? [] as $comment): ?>
          <div class="comment-item comment-reply">
            <?php require APP_PATH . '/app/views/partials/comment-item.php'; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php $replyTo = $rootComment; require APP_PATH . '/app/views/partials/comment-form.php'; ?>
    </article>
  <?php endforeach; ?>
</section>
