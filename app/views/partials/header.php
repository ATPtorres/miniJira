<?php
/**
 * Sistema Universitario
 * app/views/partials/header.php
 *
 * Cabecera comun de todas las paginas. Requiere bootstrap cargado
 * ($pageTitle es opcional).
 */

declare(strict_types=1);

if (!defined('APP_PATH')) {
    http_response_code(403);
    exit('Acceso denegado');
}

$pageTitle  = $pageTitle ?? 'Bienvenida';
$currentUser = is_logged_in() ? current_user() : null;
$roleName    = $currentUser['role_name'] ?? null;
$notifCount  = $currentUser !== null ? Notification::unreadCount((int) $currentUser['id']) : 0;
$recentNotifs = $currentUser !== null ? Notification::forUser((int) $currentUser['id'], 5) : [];
$appName     = e(config('app.name'));
$brandInitial = mb_substr(trim(strip_tags($appName)), 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> · <?= $appName ?></title>
  <meta name="description" content="MiniJira: tareas por clase, entrega de archivos, evaluación y reportes de avance.">
  <script src="<?= e(asset('js/theme.js')) ?>"></script>
  <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
  <script>
    window.APP = {
      baseUrl: <?= json_encode(base_url()) ?>,
      csrfToken: <?= json_encode(csrf_token()) ?>
    };
  </script>
</head>
<body>
  <a class="skip-link" href="#contenido" data-skip-link>Saltar al contenido principal</a>

  <?php if ($currentUser !== null): ?>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="<?= e(base_url()) ?>">
        <span class="brand-mark" aria-hidden="true"><?= $brandInitial ?></span>
        <?= $appName ?>
      </a>
      <button class="nav-toggle" aria-label="Abrir menu" aria-expanded="false" data-nav-toggle aria-controls="main-nav-list">
        <span></span><span></span><span></span>
      </button>
      <nav aria-label="Navegacion principal">
        <ul class="nav-list" id="main-nav-list" data-nav-list>
          <li>
            <a href="<?= e(base_url(can('admin.access') ? 'admin/dashboard.php' : 'user/dashboard.php')) ?>">Mi panel</a>
          </li>
          <?php if (can('projects.view.assigned') || can('projects.create') || can('projects.manage.all')): ?>
          <li>
            <a href="<?= e(base_url('projects/index.php')) ?>">Proyectos</a>
          </li>
          <?php endif; ?>
          <?php if (can('password.change.own')): ?>
          <li>
            <a href="<?= e(base_url('user/change-password.php')) ?>">Cambiar contrasena</a>
          </li>
          <?php endif; ?>
          <?php if (can('user.access') || can('classes.manage')): ?>
          <li><a href="<?= e(base_url(can('classes.manage') ? 'admin/classes.php' : 'user/classes.php')) ?>">Clases</a></li>
          <?php endif; ?>
          <li><a href="<?= e(base_url('projects/analytics.php')) ?>">Analitica</a></li>
          <li><a href="<?= e(base_url('projects/reports.php')) ?>">Reportes</a></li>
          <li><?php require APP_PATH.'/app/views/partials/theme-toggle.php'; ?></li>
          <li class="nav-notif-wrapper">
            <button type="button" class="nav-bell" aria-label="Notificaciones" aria-controls="notif-dropdown" aria-expanded="false" id="notif-bell">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              <?php if ($notifCount > 0): ?>
              <span class="nav-bell-badge" id="notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
              <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notif-dropdown">
              <div class="notif-dropdown-header">
                <span>Notificaciones</span>
                <form method="post" action="<?= e(base_url('user/notifications.php')) ?>" class="inline-form" data-notification-action data-mark-all <?= $notifCount > 0 ? '' : 'hidden' ?>>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="mark-all-read">
                  <button type="submit" class="btn-link">Marcar todo leido</button>
                </form>
              </div>
              <ul class="notif-dropdown-list">
                <?php if ($recentNotifs === []): ?>
                  <li class="notif-empty">No tienes notificaciones nuevas</li>
                <?php else: ?>
                  <?php foreach ($recentNotifs as $n): ?>
                  <li class="notif-item <?= $n['is_read'] ? '' : 'notif-unread' ?>">
                    <a href="<?= e(notification_url($n['link'])) ?>">
                      <span class="notif-item-title"><?= e($n['title']) ?></span>
                      <span class="notif-item-msg"><?= e($n['message']) ?></span>
                      <time class="notif-item-time"><?= e($n['created_at']) ?></time>
                    </a>
                  </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
              <div class="notif-dropdown-footer">
                <a href="<?= e(base_url('user/notifications.php')) ?>">Ver todas las notificaciones</a>
              </div>
            </div>
          </li>
          <li class="nav-user">
            <?= e($currentUser['first_name']) ?>
            <span class="role-badge"><?= e($roleName ?? $currentUser['role_code']) ?></span>
          </li>
          <li>
            <form method="post" action="<?= e(base_url('logout.php')) ?>" class="inline-form" data-logout-form>
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline">Cerrar sesion</button>
            </form>
          </li>
        </ul>
      </nav>
    </div>
  </header>
  <?php else: ?>
  <div class="container guest-tools">
    <?php require APP_PATH.'/app/views/partials/theme-toggle.php'; ?>
  </div>
  <?php endif; ?>

  <dialog class="confirmation-dialog" data-logout-dialog aria-labelledby="logout-dialog-title" aria-describedby="logout-dialog-message">
    <h2 id="logout-dialog-title">Cerrar sesion</h2>
    <p id="logout-dialog-message">¿Esta seguro de cerrar su sesion actual?</p>
    <div class="dialog-actions">
      <button type="button" class="btn btn-primary" data-confirm-logout>Confirmar</button>
      <button type="button" class="btn btn-outline-dark" data-cancel-logout>Cancelar</button>
    </div>
  </dialog>

  <main id="contenido" class="container main" tabindex="-1">
    <?= flash_display() ?>
