document.querySelectorAll('[data-file-version]').forEach(select => {
  select.addEventListener('change', () => {
    const option = select.selectedOptions[0];
    const form = select.closest('form');
    const task = form.querySelector('select[name="task_id"]');
    const upload = form.querySelector('input[type="file"]');
    if (select.value !== '0' && task) task.value = option.dataset.taskId === '0' ? '' : option.dataset.taskId;
    if (upload) upload.multiple = select.value === '0';
  });
});
