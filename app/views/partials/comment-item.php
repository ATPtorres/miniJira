<?php
$isMine = (int) $comment['author_id'] === current_user_id();
$attachments = $threadAttachments[(int)$comment['id']] ?? [];
?>
<div class="comment-head">
  <span class="comment-author">
    <?php if ((int) $comment['is_anonymous'] === 1): ?>
      Anónimo<?= $isMine ? ' (tú)' : '' ?>
    <?php else: ?>
      <?= e($comment['author_name'] ?? 'Usuario') ?>
      <span class="badge badge-<?= e($comment['author_role']) ?>"><?= e(ucfirst($comment['author_role'])) ?></span>
    <?php endif; ?>
  </span>
  <?php if ($comment['visibility'] === 'private'): ?><span class="badge badge-private">Privado</span><?php endif; ?>
  <span class="comment-date"><?= e(date('d/m/Y H:i', strtotime($comment['created_at']))) ?></span>
  <?php if ($isMine || $canManage): ?>
    <form method="post" action="<?= e(base_url($commentUrl)) ?>" class="comment-delete-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= e($deleteCommentAction) ?>">
      <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
      <button type="submit" class="btn btn-link btn-sm" onclick="return confirm('¿Eliminar este comentario, sus respuestas y adjuntos?')">Eliminar</button>
    </form>
  <?php endif; ?>
</div>
<p class="comment-body"><?= nl2br(e($comment['body'])) ?></p>
<?php if ($attachments !== []): ?>
  <div class="comment-attachments">
    <?php foreach ($attachments as $attachment): ?>
      <a class="comment-attachment" href="<?= e(base_url('projects/download.php?file_id=' . (int) $attachment['id'])) ?>">
        <i class="fas fa-<?= e(ProjectFile::iconFor($attachment['mime_type'])) ?>"></i>
        <span><?= e($attachment['original_name']) ?></span>
        <small>(<?= e(ProjectFile::formatSize((int) $attachment['file_size'])) ?>)</small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
