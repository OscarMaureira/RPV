document.querySelectorAll('.price-form').forEach(form => {
  const price = document.querySelector('[form="' + form.id + '"][name="precio_clp"]');
  if (!price) return;
  const format = () => {
    const digits = price.value.replace(/\D/g, '');
    price.value = digits ? Number(digits).toLocaleString('es-CL') : '';
  };
  price.addEventListener('blur', format);
  price.addEventListener('input', () => {
    price.setCustomValidity('');
    const value = Number(price.value.replace(/\D/g, ''));
    if (value > 2000000) price.setCustomValidity('El precio no puede superar $2.000.000.');
  });
  form.addEventListener('submit', event => {
    const value = Number(price.value.replace(/\D/g, ''));
    if (!Number.isInteger(value) || value < 1 || value > 2000000) {
      event.preventDefault();
      price.setCustomValidity('Ingresa un precio entre $1 y $2.000.000.');
      price.reportValidity();
    }
  });
});