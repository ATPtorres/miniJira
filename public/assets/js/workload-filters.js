document.querySelectorAll('[data-workload-filters]').forEach(form => {
  const fields = ['period', 'career', 'subject', 'semester_number', 'group_name'];
  fields.forEach((key, index) => {
    const select = form.elements.namedItem(key);
    if (!select) return;
    select.addEventListener('change', () => {
      fields.slice(index + 1).forEach(child => { form.elements.namedItem(child).value = ''; });
      form.requestSubmit();
    });
  });
});
