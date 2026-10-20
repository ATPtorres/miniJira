/** Countdown en tiempo real para fechas de proyecto y tareas. */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-countdown]').forEach(el => {
    const target = el.getAttribute('data-countdown');
    if (!target) return;
    const date = new Date(target);
    if (isNaN(date.getTime())) return;

    function update() {
      const now = new Date();
      const diff = date - now;
      if (diff <= 0) {
        el.classList.add('countdown-expired');
        el.querySelector('.countdown-value').textContent = 'Vencido';
        return;
      }
      const days = Math.floor(diff / 86400000);
      const hours = Math.floor((diff % 86400000) / 3600000);
      const minutes = Math.floor((diff % 3600000) / 60000);
      const seconds = Math.floor((diff % 60000) / 1000);
      el.querySelector('.countdown-value').textContent =
        days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
      if (days < 3) el.classList.add('countdown-urgent');
    }

    update();
    setInterval(update, 1000);
  });

  document.querySelectorAll('.task-due[data-due]').forEach(el => {
    const target = el.getAttribute('data-due');
    if (!target) return;
    const date = new Date(target);
    if (isNaN(date.getTime())) return;

    function update() {
      const now = new Date();
      const diff = date - now;
      if (diff <= 0) {
        el.classList.add('due-expired');
        return;
      }
      const days = Math.floor(diff / 86400000);
      if (days < 3) el.classList.add('due-urgent');
    }

    update();
  });
});
