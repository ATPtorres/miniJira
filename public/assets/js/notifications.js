/**
 * Sistema Universitario
 * public/assets/js/notifications.js
 *
 * Toggle del dropdown de notificaciones y marcar como leidas via fetch.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var bell = document.getElementById('notif-bell');
    var dropdown = document.getElementById('notif-dropdown');

    if (!bell || !dropdown) return;

    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      dropdown.classList.toggle('is-open');
    });

    document.addEventListener('click', function (e) {
      if (!dropdown.contains(e.target) && e.target !== bell) {
        dropdown.classList.remove('is-open');
      }
    });

    var markForms = document.querySelectorAll('.notif-item.notif-unread form[data-quick-mark]');
    markForms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(form);
        formData.set('action', 'mark-read');

        fetch(form.getAttribute('action'), {
          method: 'POST',
          body: formData
        }).then(function () {
          var item = form.closest('.notif-item');
          if (item) {
            item.classList.remove('notif-unread');
          }
          form.remove();
          var badge = document.getElementById('notif-badge');
          if (badge) {
            var current = parseInt(badge.textContent, 10);
            if (current > 1) {
              badge.textContent = current - 1;
            } else {
              badge.remove();
            }
          }
        });
      });
    });
  });
})();
