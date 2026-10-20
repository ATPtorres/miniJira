<?php
/**
 * Sistema Universitario
 * app/views/partials/footer.php
 */

declare(strict_types=1);

if (!defined('APP_PATH')) {
    http_response_code(403);
    exit('Acceso denegado');
}
?>
  </main>

  <footer class="site-footer">
    <div class="container">
      <p class="footer-brand"><?= e(config('app.name')) ?></p>
      <p>&copy; <?= date('Y') ?> <?= e(config('app.name')) ?>. Todos los derechos reservados.</p>
    </div>
  </footer>

  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
  <script src="<?= e(asset('js/validation.js')) ?>" defer></script>
  <script src="<?= e(asset('js/auth.js')) ?>" defer></script>
  <script src="<?= e(asset('js/countdown.js')) ?>" defer></script>
  <script src="<?= e(asset('js/calendar.js')) ?>" defer></script>
  <script src="<?= e(asset('js/notifications.js')) ?>" defer></script>
  <script src="<?= e(asset('js/kanban-dnd.js')) ?>" defer></script>
</body>
</html>