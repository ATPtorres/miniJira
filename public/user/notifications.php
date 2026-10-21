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
    <form method="post" action="<?= e(base_url('user/notifications.php')) ?>" class="inline-form" data-notification-action>
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

<section class="card" id="notification-results" aria-live="polite">
  <?php require APP_PATH . '/app/views/partials/notification-list.php'; ?>
</section>
<p id="notification-feedback" role="status" aria-live="polite"></p>
<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
