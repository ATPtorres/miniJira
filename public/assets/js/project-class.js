document.querySelectorAll('[data-project-class]').forEach(select => {
  select.addEventListener('change', () => {
    const url = new URL(window.location.href);
    url.searchParams.set('classroom_id', select.value);
    window.location.assign(url);
  });
});
