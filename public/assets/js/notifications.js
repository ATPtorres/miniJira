/** Campana y listado: SSE autenticado, con consultas periodicas como alternativa. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var bell = document.getElementById('notif-bell');
    var dropdown = document.getElementById('notif-dropdown');
    if (!bell || !dropdown || !window.APP) return;
    var stream, timer, version = null, stopped = false, listRequest = 0;
    var endpoint = window.APP.baseUrl + 'api/notifications.php';
    var results = document.getElementById('notification-results');
    var feedback = document.getElementById('notification-feedback');
    bell.setAttribute('aria-expanded', 'false');
    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = dropdown.classList.toggle('is-open');
      bell.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', function (e) {
      if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
        dropdown.classList.remove('is-open'); bell.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && dropdown.classList.contains('is-open') && !document.querySelector('dialog[open]')) {
        event.stopPropagation();
        dropdown.classList.remove('is-open');
        bell.setAttribute('aria-expanded', 'false');
        bell.focus();
      }
    }, true);
    document.addEventListener('focusin', function (event) {
      if (!dropdown.contains(event.target) && !bell.contains(event.target)) {
        dropdown.classList.remove('is-open');
        bell.setAttribute('aria-expanded', 'false');
      }
    });
    function pause() { clearTimeout(timer); if (stream) { stream.close(); stream = null; } }
    function expired() { stopped = true; pause(); }
    function check(res) {
      if (res.status === 401) { expired(); throw new Error('Sesion finalizada'); }
      if (!res.ok) throw new Error('No se pudo actualizar las notificaciones');
      return res;
    }
    function updateList() {
      if (!results) return;
      var request = ++listRequest;
      var params = new URLSearchParams(window.location.search); params.set('mode', 'list');
      fetch(endpoint + '?' + params.toString(), {credentials: 'same-origin', cache: 'no-store'})
        .then(check).then(function (res) { return res.text(); }).then(function (html) {
          if (request === listRequest) results.innerHTML = html;
        }).catch(function () { if (feedback) feedback.textContent = 'No se pudo actualizar el listado. Puedes recargar la pagina.'; });
    }
    function render(data) {
      document.querySelectorAll('[data-mark-all]').forEach(function (form) { form.hidden = data.unread === 0; });
      var badge = document.getElementById('notif-badge');
      if (data.unread > 0) {
        if (!badge) { badge = document.createElement('span'); badge.id = 'notif-badge'; badge.className = 'nav-bell-badge'; bell.appendChild(badge); }
        badge.textContent = data.unread > 99 ? '99+' : String(data.unread);
      } else if (badge) badge.remove();
      bell.setAttribute('aria-label', 'Notificaciones: ' + data.unread + ' sin leer');
      var list = dropdown.querySelector('.notif-dropdown-list');
      if (version !== data.version) {
        list.replaceChildren();
        data.items.forEach(function (n) {
          var li = document.createElement('li'); li.className = 'notif-item' + (Number(n.is_read) ? '' : ' notif-unread');
          var link = document.createElement('a');
          var url = new URL(n.url, location.href);
          link.href = url.origin === location.origin ? url.href : window.APP.baseUrl + 'user/notifications.php';
          [['span', 'notif-item-title', n.title], ['span', 'notif-item-msg', n.message], ['time', 'notif-item-time', n.created_at]].forEach(function (part) {
            var el = document.createElement(part[0]); el.className = part[1]; el.textContent = part[2]; link.appendChild(el);
          });
          li.appendChild(link); list.appendChild(li);
        });
        if (!data.items.length) { var empty = document.createElement('li'); empty.className = 'notif-empty'; empty.textContent = 'No tienes notificaciones'; list.appendChild(empty); }
        updateList();
        version = data.version;
      }
    }
    function poll() {
      if (stopped || document.hidden) return;
      fetch(endpoint, {credentials: 'same-origin', cache: 'no-store'})
        .then(check).then(function (res) { return res.json(); }).then(render)
        .catch(function () {}).finally(function () { if (!stopped) timer = setTimeout(poll, 5000); });
    }
    function start() {
      pause(); if (stopped || document.hidden) return;
      fetch(endpoint, {credentials: 'same-origin', cache: 'no-store'})
        .then(check).then(function (res) { return res.json(); }).then(function (data) {
          render(data);
          if (document.hidden || stopped) return;
          if (data.transport === 'sse' && window.EventSource) {
            stream = new EventSource(endpoint + '?mode=stream');
            stream.addEventListener('notifications', function (e) { render(JSON.parse(e.data)); });
            stream.addEventListener('expired', expired);
            stream.onerror = function () { pause(); if (!stopped) timer = setTimeout(poll, 3000); };
          } else timer = setTimeout(poll, 5000);
        }).catch(function () { if (!stopped) timer = setTimeout(poll, 5000); });
    }
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.matches('[data-notification-action]')) return;
      e.preventDefault();
      if (form.querySelector('[name="action"]').value === 'delete' && !confirm('¿Eliminar esta notificacion?')) return;
      var button = form.querySelector('button'); if (button) button.disabled = true;
      fetch(endpoint, {method: 'POST', body: new FormData(form), credentials: 'same-origin'})
        .then(check).then(function (res) { return res.json(); }).then(function (data) {
          if (!data.ok) throw new Error('Solicitud rechazada');
          render(data.feed); updateList(); if (feedback) feedback.textContent = 'Notificaciones actualizadas.';
        }).catch(function () { if (feedback) feedback.textContent = 'No se pudo guardar el cambio. Intenta de nuevo.'; })
        .finally(function () { if (button) button.disabled = false; });
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) pause(); else start(); });
    window.addEventListener('pagehide', pause);
    start();
  });
})();
