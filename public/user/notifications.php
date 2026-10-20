<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
requirePermission('user.access');

$me = current_user();
$userId = (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = post('action');

    if ($action === 'mark-all-read') {
        Notification::markAllRead($userId);
        flash_set('success', 'Todas las notificaciones marcadas como leidas.');
    } elseif ($action === 'mark-read') {
        $notifId = (int) post('notif_id');
        Notification::markRead($notifId, $userId);
    } elseif ($action === 'delete') {
        $notifId = (int) post('notif_id');
        Notification::delete($notifId, $userId);
        flash_set('success', 'Notificacion eliminada.');
    }

    $params = [];
    if (isset($_GET['page'])) $params['page'] = $_GET['page'];
    if (isset($_GET['type'])) $params['type'] = $_GET['type'];
    if (isset($_GET['read'])) $params['read'] = $_GET['read'];
    redirect('user/notifications.php' . ($params !== [] ? '?' . http_build_query($params) : ''));
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$typeFilter = $_GET['type'] ?? null;
$readFilter = $_GET['read'] ?? null;

$result = Notification::paginated($userId, $page, 15, $typeFilter, $readFilter);
$notifications = $result['items'];
$totalPages = $result['totalPages'];
$total = $result['total'];

$pageTitle = 'Notificaciones';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head">
  <h1>Notificaciones</h1>
  <div class="panel-head-actions">
    <?php if ($total > 0): ?>
    <form method="post" action="<?= e(base_url('user/notifications.php')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark-all-read">
      <button type="submit" class="btn btn-sm btn-outline-dark">Marcar todo leido</button>
    </form>
    <?php endif; ?>
  </div>
</section>

<section class="notif-filters">
  <form method="get" action="<?= e(base_url('user/notifications.php')) ?>" class="notif-filter-form">
    <select name="type" class="form-input notif-select">
      <option value="">Todos los tipos</option>
      <?php foreach (Notification::TYPES as $type): ?>
        <option value="<?= e($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= e(Notification::typeLabel($type)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="read" class="form-input notif-select">
      <option value="">Todas</option>
      <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>No leidas</option>
      <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Leidas</option>
    </select>
    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
    <?php if ($typeFilter !== null || $readFilter !== null): ?>
      <a href="<?= e(base_url('user/notifications.php')) ?>" class="btn btn-sm btn-outline-dark">Limpiar</a>
    <?php endif; ?>
  </form>
</section>

<section class="card">
  <?php if ($notifications === []): ?>
    <div class="empty-state">
      <h2>Sin notificaciones</h2>
      <p class="text-muted">No tienes notificaciones<?= $typeFilter !== null || $readFilter !== null ? ' que coincidan con los filtros' : '' ?>.</p>
    </div>
  <?php else: ?>
    <ul class="notif-list">
      <?php foreach ($notifications as $n): ?>
      <li class="notif-list-item <?= $n['is_read'] ? '' : 'notif-list-unread' ?>">
        <div class="notif-list-content">
          <div class="notif-list-header">
            <span class="notif-type-badge"><?= e(Notification::typeLabel($n['type'])) ?></span>
            <time class="notif-list-time"><?= e(date('d/m/Y H:i', strtotime($n['created_at']))) ?></time>
          </div>
          <strong class="notif-list-title"><?= e($n['title']) ?></strong>
          <p class="notif-list-msg"><?= e($n['message']) ?></p>
        </div>
        <div class="notif-list-actions">
          <?php if (!$n['is_read']): ?>
          <form method="post" action="<?= e(base_url('user/notifications.php')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark-read">
            <input type="hidden" name="notif_id" value="<?= (int) $n['id'] ?>">
            <button type="submit" class="btn-link" title="Marcar como leida">Marcar leido</button>
          </form>
          <?php endif; ?>
          <?php if ($n['link'] !== null): ?>
          <a href="<?= e(notification_url($n['link'])) ?>" class="btn btn-sm btn-outline-dark">Ver</a>
          <?php endif; ?>
          <form method="post" action="<?= e(base_url('user/notifications.php')) ?>" class="inline-form" data-confirm-delete>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="notif_id" value="<?= (int) $n['id'] ?>">
            <button type="submit" class="btn-link btn-danger" title="Eliminar">Eliminar</button>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Paginacion de notificaciones">
      <?php
      $baseUrl = base_url('user/notifications.php');
      $queryParams = [];
      if ($typeFilter !== null) $queryParams['type'] = $typeFilter;
      if ($readFilter !== null) $queryParams['read'] = $readFilter;
      ?>
      <?php if ($page > 1): ?>
        <?php $queryParams['page'] = $page - 1; ?>
        <a href="<?= e($baseUrl . '?' . http_build_query($queryParams)) ?>" class="btn btn-sm btn-outline-dark">&laquo; Anterior</a>
      <?php endif; ?>

      <span class="pagination-info">Pagina <?= $page ?> de <?= $totalPages ?></span>

      <?php if ($page < $totalPages): ?>
        <?php $queryParams['page'] = $page + 1; ?>
        <a href="<?= e($baseUrl . '?' . http_build_query($queryParams)) ?>" class="btn btn-sm btn-outline-dark">Siguiente &raquo;</a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
