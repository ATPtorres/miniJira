/**
 * Sistema Universitario
 * public/assets/js/auth.js
 *
 * Funciones auxiliares de autenticacion (fetch).
 */
(function () {
  'use strict';

  if (typeof window.APP === 'undefined' || !window.APP.baseUrl || !window.APP.csrfToken) {
    return;
  }

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    var input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) {
      return;
    }

    button.addEventListener('click', function () {
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      button.setAttribute('aria-pressed', showing ? 'false' : 'true');
      button.setAttribute('aria-label', showing ? 'Mostrar contrasena' : 'Ocultar contrasena');
      button.querySelector('.visually-hidden').textContent = showing ? 'Mostrar contrasena' : 'Ocultar contrasena';
    });
  });

  var logoutForm = document.querySelector('[data-logout-form]');
  var logoutDialog = document.querySelector('[data-logout-dialog]');
  if (logoutForm && logoutDialog && typeof logoutDialog.showModal === 'function') {
    var cancelLogout = logoutDialog.querySelector('[data-cancel-logout]');
    var confirmLogout = logoutDialog.querySelector('[data-confirm-logout]');
    var opener = null;

    logoutForm.addEventListener('submit', function (event) {
      event.preventDefault();
      opener = document.activeElement;
      logoutDialog.showModal();
      confirmLogout.focus();
    });

    cancelLogout.addEventListener('click', function () {
      logoutDialog.close();
    });

    logoutDialog.addEventListener('close', function () {
      if (opener && typeof opener.focus === 'function') {
        opener.focus();
      }
    });

    confirmLogout.addEventListener('click', function () {
      logoutForm.submit();
    });
  } else if (logoutForm) {
    // Compatibilidad para navegadores que no implementan el elemento dialog.
    logoutForm.addEventListener('submit', function (event) {
      if (!window.confirm('¿Esta seguro de cerrar su sesion actual?')) {
        event.preventDefault();
      }
    });
  }

  window.APP.checkEmailUnique = function (email, callback) {
    fetch(window.APP.baseUrl + 'api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'check-email',
        email: email,
        csrf_token: window.APP.csrfToken
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) { if (callback) callback(data); })
      .catch(function () {});
  };
})();
