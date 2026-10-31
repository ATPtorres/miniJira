/* Load before styles to restore the selected theme before the first paint. */
(function () {
  'use strict';
  var key = 'minijira.theme';
  var root = document.documentElement;

  function readTheme() {
    try {
      return window.localStorage.getItem(key) === 'dark' ? 'dark' : 'light';
    } catch (_) {
      // The switch still works when browser storage is unavailable.
      return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }
  }

  function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
      button.hidden = false;
      button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      button.title = theme === 'dark' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
    });
  }

  applyTheme(readTheme());
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme(readTheme());
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
      button.addEventListener('click', function () {
        var theme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(theme);
        try { window.localStorage.setItem(key, theme); } catch (_) { /* Optional storage. */ }
      });
    });
  });
  // Keep other tabs and pages restored from the back/forward cache consistent.
  window.addEventListener('storage', function (event) {
    if (event.key === key || event.key === null) applyTheme(readTheme());
  });
  window.addEventListener('pageshow', function () { applyTheme(readTheme()); });
})();
