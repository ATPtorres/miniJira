/** Calendario del proyecto: tooltips y modal de eventos (crear / editar / ver). */
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('event-modal');
  if (!modal) return;

  const form = document.getElementById('event-modal-form');
  const viewPane = document.getElementById('event-modal-view');
  const modalTitle = document.getElementById('event-modal-title');
  const btnSubmit = document.getElementById('event-modal-submit');
  const btnDelete = document.getElementById('event-modal-delete');
  const fAction = document.getElementById('event-modal-action');
  const fId = document.getElementById('event-modal-event-id');
  const fTitle = document.getElementById('event-title-input');
  const fType = document.getElementById('event-type-input');
  const fDate = document.getElementById('event-date-input');
  const fTime = document.getElementById('event-time-input');
  const fDesc = document.getElementById('event-desc-input');

  let opener = null;
  let previousOverflow = '';
  function openModal() {
    opener = document.activeElement;
    previousOverflow = document.body.style.overflow;
    modal.hidden = false;
    modal.showModal(); // The native dialog makes the background inert.
    document.body.style.overflow = 'hidden';
  }

  function closeModal() { modal.close(); }
  modal.addEventListener('close', () => {
    modal.hidden = true;
    document.body.style.overflow = previousOverflow;
    if (opener && opener.isConnected) opener.focus();
  });

  // Keep Tab inside the dialog, including when the browser would focus its own chrome.
  modal.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const controls = Array.from(modal.querySelectorAll(
      'button, a[href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])'
    )).filter(el => !el.matches(':disabled') && el.getClientRects().length > 0);
    if (!controls.length) return;
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); first.focus();
    }
  });

  function dataFrom(el) {
    return {
      id: el.getAttribute('data-event-id') || '',
      title: el.getAttribute('data-title') || '',
      date: el.getAttribute('data-date') || '',
      time: (el.getAttribute('data-time') || '').slice(0, 5),
      type: el.getAttribute('data-type') || 'custom',
      typeLabel: el.getAttribute('data-type-label') || '',
      desc: el.getAttribute('data-desc') || '',
      creator: el.getAttribute('data-creator') || ''
    };
  }

  function openCreate(dateStr) {
    if (!form) return;
    form.reset();
    fAction.value = 'create-event';
    fId.value = '';
    modalTitle.textContent = 'Nuevo evento';
    btnSubmit.textContent = 'Crear evento';
    btnDelete.hidden = true;
    viewPane.hidden = true;
    form.hidden = false;
    fDate.value = dateStr || '';
    openModal();
    fTitle.focus();
  }

  function openEdit(data) {
    if (!form) return;
    fAction.value = 'update-event';
    fId.value = data.id;
    fTitle.value = data.title;
    fType.value = data.type;
    if (fType.value !== data.type) {
      // Tipo fuera de las opciones del formulario (evento heredado)
      const opt = document.createElement('option');
      opt.value = data.type;
      opt.textContent = data.typeLabel || data.type;
      fType.appendChild(opt);
      fType.value = data.type;
    }
    fDate.value = data.date;
    fTime.value = data.time;
    fDesc.value = data.desc;
    modalTitle.textContent = 'Editar evento';
    btnSubmit.textContent = 'Guardar cambios';
    btnDelete.hidden = false;
    viewPane.hidden = true;
    form.hidden = false;
    openModal();
    fTitle.focus();
  }

  function openView(data) {
    if (!viewPane) return;
    document.getElementById('view-title').textContent = data.title;
    const badge = document.getElementById('view-type');
    badge.textContent = data.typeLabel || data.type;
    badge.className = 'event-type-badge type-' + data.type;
    document.getElementById('view-date').textContent = data.date
      ? data.date.split('-').reverse().join('/')
      : '';
    document.getElementById('view-time').textContent = data.time;
    document.getElementById('view-creator').textContent = data.creator
      ? 'Creado por ' + data.creator
      : '';
    document.getElementById('view-desc').textContent = data.desc;
    modalTitle.textContent = 'Evento';
    if (form) form.hidden = true;
    viewPane.hidden = false;
    openModal();
  }

  // Cerrar: boton X, Cancelar, clic en el fondo, tecla Escape
  const btnClose = modal.querySelector('.modal-close');
  if (btnClose) btnClose.addEventListener('click', closeModal);
  const btnCancel = modal.querySelector('.modal-cancel');
  if (btnCancel) btnCancel.addEventListener('click', closeModal);
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });
  // Escape is handled by the native dialog and follows the same close path.

  // Eliminar desde el modal (solo gestores)
  if (btnDelete) {
    btnDelete.addEventListener('click', () => {
      if (!confirm('¿Eliminar este evento?')) return;
      fAction.value = 'delete-event';
      form.submit();
    });
  }

  // Clicks en el grid: evento -> editar/ver; dia vacio (gestores) -> crear
  const calBody = document.querySelector('.calendar-body');
  if (calBody) {
    calBody.addEventListener('click', e => {
      const evtDot = e.target.closest('.event-dot[data-event-id]');
      if (evtDot) {
        const data = dataFrom(evtDot);
        if (form) openEdit(data);
        else openView(data);
        return;
      }
      if (e.target.closest('.event-dot')) return;
      const day = e.target.closest('.calendar-day.day-clickable[data-date]');
      if (day) {
        day.querySelector('.calendar-create').focus();
        openCreate(day.getAttribute('data-date'));
      }
    });
  }

  // Boton "Nuevo evento" (nav y/o lista)
  document.querySelectorAll('.btn-new-event').forEach(btn => {
    btn.addEventListener('click', () => {
      openCreate(btn.getAttribute('data-default-date') || '');
    });
  });

  // Boton "Editar" de la lista de eventos
  document.querySelectorAll('.event-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.event-item[data-event-id]');
      if (item && form) openEdit(dataFrom(item));
    });
  });
});
