/**
 * Sistema Universitario
 * public/assets/js/app.js
 *
 * Interfaz de usuario: menu movil, utilidades generales.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /* Menu hamburguesa (movil)                                            */
  /* ------------------------------------------------------------------ */
  var toggle = document.querySelector('[data-nav-toggle]');
  var navList = document.querySelector('[data-nav-list]');

  if (toggle && navList) {
    toggle.addEventListener('click', function () {
      var isOpen = navList.classList.toggle('is-open');
      toggle.classList.toggle('is-active', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Cerrar el menu al hacer clic fuera
    document.addEventListener('click', function (event) {
      if (!toggle.contains(event.target) && !navList.contains(event.target)) {
        navList.classList.remove('is-open');
        toggle.classList.remove('is-active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Cerrar el menu al presionar Escape
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && navList.classList.contains('is-open')) {
        navList.classList.remove('is-open');
        toggle.classList.remove('is-active');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }
})();
