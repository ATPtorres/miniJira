<?php
declare(strict_types=1);
$notifications = $result['items'];
?>
<?php if (!$notifications): ?>
<p class="text-muted">No hay notificaciones que coincidan con los filtros.</p>
<?php else: ?>
<ul class="notif-list">
<?php foreach ($notifications as $n): ?>
  <li class="notif-list-item <?= $n['is_read'] ? '' : 'notif-list-unread' ?>">
    <div class="notif-list-content">
      <span class="notif-type-badge"><?= e(Notification::typeLabel($n['type'])) ?></span>
      <time><?= e($n['created_at']) ?></time>
      <strong class="notif-list-title"><?= e($n['title']) ?></strong>
      <p><?= e($n['message']) ?></p>
    </div>
    <div class="notif-list-actions">
      <?php if (!$n['is_read']): ?><form method="post" action="<?= e(base_url('user/notifications.php')) ?>" data-notification-action>
        <?= csrf_field() ?><input type="hidden" name="action" value="mark-read"><input type="hidden" name="notif_id" value="<?= (int) $n['id'] ?>"><button class="btn-link">Marcar leido</button>
      </form><?php endif; ?>
      <?php if ($n['link'] !== null): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(notification_url($n['link'])) ?>">Ver</a><?php endif; ?>
      <form method="post" action="<?= e(base_url('user/notifications.php')) ?>" data-notification-action>
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="notif_id" value="<?= (int) $n['id'] ?>"><button class="btn-link">Eliminar</button>
      </form>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if ($result['totalPages'] > 1): ?>
<nav class="pagination" aria-label="Paginacion de notificaciones">
<?php foreach ([$page-1=>'Anterior',$page+1=>'Siguiente'] as $target=>$label): if ($target<1 || $target>$result['totalPages']) continue;
    $query = http_build_query(['page'=>$target,'type'=>$typeFilter,'read'=>$readFilter]); ?>
<a class="btn btn-sm btn-outline-dark" href="<?= e(base_url('user/notifications.php?' . $query)) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
<span>Pagina <?= $page ?> de <?= $result['totalPages'] ?></span>
</nav>
<?php endif; ?>
