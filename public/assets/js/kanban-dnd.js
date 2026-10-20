/**
 * Drag & drop de tareas en el tablero Kanban (estilo Jira).
 * El servidor sigue siendo la fuente de verdad: este script solo replica
 * las reglas de STUDENT_ALLOWED / bloqueos para una experiencia inmediata,
 * y hace POST al mismo endpoint que el formulario de estado (update-task-status).
 */
(function () {
  'use strict';

  var board = document.querySelector('.kanban-board');
  if (!board) return;

  var projectId = board.getAttribute('data-project-id');
  var canManage = board.getAttribute('data-can-manage') === '1';
  var studentAllowed = {};
  try {
    studentAllowed = JSON.parse(board.getAttribute('data-student-allowed') || '{}');
  } catch (e) {
    studentAllowed = {};
  }

  var dragTask = null;

  function canDrop(task, toStatus) {
    if (!task) return false;
    var from = task.getAttribute('data-status');
    if (!from || from === toStatus) return false;
    // Docente: puede mover libremente (incluye DONE -> anteriores), aunque este bloqueada
    if (canManage) return true;
    if (task.getAttribute('data-locked') === '1') return false;
    return Array.isArray(studentAllowed[from]) && studentAllowed[from].indexOf(toStatus) !== -1;
  }

  function toast(message, isError) {
    var el = document.getElementById('kanban-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'kanban-toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.className = 'kanban-toast show' + (isError ? ' error' : '');
    clearTimeout(el._timer);
    el._timer = setTimeout(function () {
      el.className = 'kanban-toast';
    }, 3500);
  }

  function updateCounts() {
    var cols = board.querySelectorAll('.kanban-column');
    for (var i = 0; i < cols.length; i++) {
      var badge = cols[i].querySelector('h2 span');
      if (badge) {
        badge.textContent = String(cols[i].querySelectorAll('.kanban-task').length);
      }
    }
  }

  function revert(task, fromStatus) {
    var origin = board.querySelector('.kanban-column[data-status="' + fromStatus + '"] .kanban-tasks');
    if (origin) origin.insertBefore(task, origin.firstChild);
    task.setAttribute('data-status', fromStatus);
    updateCounts();
  }

  function columnLabel(col) {
    var h2 = col.querySelector('h2');
    if (!h2) return '';
    return h2.textContent.replace(/\s+\d+$/, '').trim();
  }

  var tasks = board.querySelectorAll('.kanban-task[draggable="true"]');
  for (var t = 0; t < tasks.length; t++) {
    (function (card) {
      // Evita iniciar drag desde controles del formulario (select, botones, etc.)
      card.addEventListener('mousedown', function (e) {
        var interactive = e.target.closest && e.target.closest('form, a, button, select, input, textarea, label');
        if (interactive) {
          card.setAttribute('draggable', 'false');
          var enable = function () {
            card.setAttribute('draggable', 'true');
            document.removeEventListener('mouseup', enable);
          };
          document.addEventListener('mouseup', enable);
        }
      });

      card.addEventListener('dragstart', function (e) {
        dragTask = card;
        card.classList.add('dragging');
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', card.getAttribute('data-task-id'));
        }
      });

      card.addEventListener('dragend', function () {
        card.classList.remove('dragging');
        dragTask = null;
        var cols = board.querySelectorAll('.kanban-column');
        for (var i = 0; i < cols.length; i++) cols[i].classList.remove('drag-over');
      });
    })(tasks[t]);
  }

  var columns = board.querySelectorAll('.kanban-column');
  for (var c = 0; c < columns.length; c++) {
    (function (col) {
      var toStatus = col.getAttribute('data-status');

      col.addEventListener('dragover', function (e) {
        if (!dragTask) return;
        if (canDrop(dragTask, toStatus)) {
          e.preventDefault();
          if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
          col.classList.add('drag-over');
        } else {
          if (e.dataTransfer) e.dataTransfer.dropEffect = 'none';
          col.classList.remove('drag-over');
        }
      });

      col.addEventListener('dragleave', function (e) {
        if (!col.contains(e.relatedTarget)) col.classList.remove('drag-over');
      });

      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('drag-over');
        var task = dragTask;
        if (!task) return;

        if (!canDrop(task, toStatus)) {
          var from = task.getAttribute('data-status');
          var reason;
          if (task.getAttribute('data-locked') === '1') {
            reason = 'la tarea esta bloqueada (vencida o calificada).';
          } else if (toStatus === 'done' && from !== 'done') {
            reason = 'solo el docente puede mover tareas a "Terminado".';
          } else {
            reason = 'transicion no permitida para tu rol.';
          }
          toast('Movimiento no permitido: ' + reason, true);
          return;
        }

        var fromStatus = task.getAttribute('data-status');
        var list = col.querySelector('.kanban-tasks');
        if (list) list.appendChild(task);
        task.setAttribute('data-status', toStatus);
        updateCounts();

        var body = new URLSearchParams();
        body.set('csrf_token', window.APP.csrfToken);
        body.set('action', 'update-task-status');
        body.set('task_id', task.getAttribute('data-task-id'));
        body.set('status', toStatus);
        body.set('ajax', '1');

        fetch(window.APP.baseUrl + 'projects/board.php?id=' + encodeURIComponent(projectId), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString(),
          credentials: 'same-origin',
          redirect: 'follow'
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (data) {
            if (data && data.ok) {
              toast('Tarea movida a "' + columnLabel(col) + '".');
            } else {
              revert(task, fromStatus);
              toast((data && data.error) || 'Error al mover la tarea.', true);
            }
          })
          .catch(function () {
            revert(task, fromStatus);
            toast('Error de conexion al mover la tarea.', true);
          });
      });
    })(columns[c]);
  }
})();
