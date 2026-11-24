document.querySelectorAll('form[data-confirm-delete]').forEach(form => {
  form.addEventListener('submit', event => {
    const name = form.dataset.account || 'esta cuenta';
    if (!window.confirm('¿Eliminar definitivamente la cuenta "' + name + '"?')) {
      event.preventDefault();
      return;
    }
    form.elements.confirm_delete.value = '1';
  });
});