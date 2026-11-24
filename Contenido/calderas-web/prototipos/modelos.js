(() => {
  const models = {
    a: { title: 'Tipo A', price: '$1.150.000', description: '1.000 litros a 37 °C en 1 hora con 1 saco de pellet.' },
    b: { title: 'Tipo B', price: '$830.000', description: '1.000 litros a 37 °C en 2 horas con 2 sacos de pellet.' },
    c: { title: 'Tipo C', price: '$730.000', description: '1.000 litros a 37 °C en 2,5 horas con 2,5 sacos de pellet.' }
  };
  const selector = document.querySelector('#model-selector');
  if (!selector) return;
  selector.addEventListener('click', event => {
    const button = event.target.closest('button[data-model]');
    if (!button) return;
    const key = button.dataset.model;
    const model = models[key];
    if (!model) return;
    selector.querySelectorAll('button[data-model]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    selector.querySelector('#model-title').textContent = model.title;
    selector.querySelector('#model-price').textContent = model.price;
    selector.querySelector('#model-description').textContent = model.description;
    const image = selector.querySelector('#model-image');
    image.src = '../imagenes/caldera-tipo-' + key + '.webp';
    image.alt = 'Ficha ilustrada de la caldera ' + model.title;
    selector.querySelector('#model-contact').href = 'https://wa.me/56964170206?text=' + encodeURIComponent('Hola, quiero consultar por la caldera ' + model.title + '.');
  });
})();

