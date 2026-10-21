/** Actualiza tarjetas y analitica con las mismas metricas utilizadas en PDF. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.APP) return;
    var timer, stopped = false, busy = false;
    function update() {
      if (stopped || busy || document.hidden) return;
      busy = true;
      var groups = new Map();
      document.querySelectorAll('[data-live-project], [data-project-progress]').forEach(function (el) {
        var id = el.dataset.liveProject || el.dataset.projectProgress;
        if (!groups.has(id)) groups.set(id, []);
        groups.get(id).push(el);
      });
      var jobs = [];
      var summaries = document.querySelectorAll('[data-live-summary]');
      if (summaries.length) jobs.push(fetch(window.APP.baseUrl + 'api/project-metrics.php?summary=1', {credentials:'same-origin',cache:'no-store'})
        .then(function (res) { if (!res.ok) throw new Error('Actualizacion no disponible'); return res.json(); })
        .then(function (data) { summaries.forEach(function (el) { if (el.innerHTML !== data.states_html) el.innerHTML = data.states_html; }); })
        .catch(function () {}));
      groups.forEach(function (elements, id) {
        var chart = elements.find(function (el) { return el.dataset.liveProject; });
        var query = new URLSearchParams({id: id});
        if (chart) { query.set('from', chart.dataset.from); query.set('to', chart.dataset.to); }
        jobs.push(fetch(window.APP.baseUrl + 'api/project-metrics.php?' + query, {credentials: 'same-origin', cache: 'no-store'})
          .then(function (res) {
            if (res.status === 401) stopped = true;
            if (!res.ok) throw new Error('Actualizacion no disponible');
            return res.json();
          }).then(function (data) {
            elements.forEach(function (el) {
              el.querySelectorAll('[data-live-value]').forEach(function (value) { value.textContent = data.metrics[value.dataset.liveValue]; });
              el.querySelectorAll('[data-live-status]').forEach(function (label) {
                label.textContent = data.metrics.completed ? 'Completado' : (data.timeline.expired ? 'Vencido' : (data.metrics.done > 0 ? 'En progreso' : 'Pendiente'));
                label.className = 'status ' + (data.metrics.completed ? 'status-done' : 'status-pending');
                label.removeAttribute('style');
              });
              el.querySelectorAll('[data-live-bar]').forEach(function (bar) {
                bar.style.width = data.metrics.progress + '%';
                if (bar.parentElement.getAttribute('role') === 'progressbar') bar.parentElement.setAttribute('aria-valuenow', data.metrics.progress);
              });
            });
            if (chart) {
              // HTML is rendered by the authenticated server, with user strings escaped.
              var states = chart.querySelector('[data-live-states]');
              if (states.innerHTML !== data.states_html) states.innerHTML = data.states_html;
              var history = chart.querySelector('[data-live-history]');
              if (history.innerHTML !== data.history_html) history.innerHTML = data.history_html;
              chart.querySelector('[data-live-feedback]').textContent = '';
            }
            data.students.forEach(function (s) {
              document.querySelectorAll('[data-live-student="' + Number(s.id) + '"]').forEach(function (row) {
                [['done', s.done], ['total', s.total], ['progress', s.progress]].forEach(function (p) {
                  var value = row.querySelector('[data-student-' + p[0] + ']'); if (value) value.textContent = p[1];
                });
              });
            });
          }).catch(function () {
            if (chart) chart.querySelector('[data-live-feedback]').textContent = 'No se pudo actualizar. Se muestran los ultimos datos disponibles.';
          }));
      });
      Promise.all(jobs).finally(function () { busy = false; if (!stopped) timer = setTimeout(update, 8000); });
    }
    document.addEventListener('visibilitychange', function () { clearTimeout(timer); if (!document.hidden) update(); });
    window.addEventListener('pagehide', function () { stopped = true; clearTimeout(timer); });
    update();
  });
})();
