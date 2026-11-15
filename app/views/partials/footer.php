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
      <nav class="footer-links" aria-label="Ayuda e información">
        <a href="<?= e(base_url('help.php')) ?>">Ayuda y soporte</a>
        <a href="<?= e(base_url('privacy.php')) ?>">Privacidad</a>
      </nav>
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
  <script src="<?= e(asset('js/project-metrics.js')) ?>" defer></script>
</body>
</html>