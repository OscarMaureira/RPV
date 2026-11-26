const form = document.querySelector('#testimonial-form');
if (location.hash === '#cuenta') document.querySelector('#cuenta details')?.setAttribute('open', '');
if (form) {
  const dialog = document.querySelector('#save-dialog');
  const confirmed = form.querySelector('#save-confirmed');
  const cancelSave = document.querySelector('#cancel-save');
  const confirmSave = document.querySelector('#confirm-save');
  const name = form.querySelector('#nombre_publico');
  const city = form.querySelector('#ciudad');
  const company = form.querySelector('#nombre_empresa');
  const category = form.querySelector('#categoria');
  const ratingControl = form.querySelector('#rating-control');
  const ratingOptions = [...ratingControl.querySelectorAll('input[name="calificacion"]')];
  const ratingHint = form.querySelector('#rating-hint');
  const ratingClear = form.querySelector('#rating-clear');
  const comment = form.querySelector('#comentario');
  const output = document.querySelector('#character-count');
  const previewName = document.querySelector('#preview-name');
  const previewDetail = document.querySelector('#preview-detail');
  const previewComment = document.querySelector('#preview-comment');
  const previewStars = document.querySelector('#preview-stars');
  const previewAvatar = document.querySelector('#preview-avatar');

  const selectedRating = () => Number(ratingOptions.find((option) => option.checked)?.value || 0);
  const paintRating = (value) => {
    ratingOptions.forEach((option) => {
      option.closest('.rating-choice').classList.toggle('is-on', Number(option.value) <= value);
    });
    ratingHint.textContent = value ? `${value} de 5 estrellas` : 'Sin calificación';
    ratingClear.disabled = selectedRating() === 0;
  };

  const updatePreview = () => {
    const publicName = name.value.trim();
    const location = city.value.trim();
    const business = company.value.trim();
    const body = comment.value.trim();
    const stars = selectedRating();
    previewName.textContent = publicName || 'Nombre del cliente';
    previewDetail.textContent = [category.value, location, business].filter(Boolean).join(' · ');
    previewComment.textContent = body || 'El comentario de tu cliente aparecerá aquí mientras lo escribes.';
    previewAvatar.textContent = publicName ? Array.from(publicName)[0].toLocaleUpperCase('es') : 'C';
    previewStars.textContent = stars >= 1 && stars <= 5 ? '★'.repeat(stars) : '';
    previewStars.setAttribute('aria-label', stars >= 1 && stars <= 5 ? `${stars} de 5 estrellas` : 'Sin calificación');
    output.textContent = `${Array.from(comment.value).length} / 700`;
    paintRating(stars);
  };

  form.addEventListener('input', updatePreview);
  form.addEventListener('change', updatePreview);
  ratingControl.addEventListener('pointerover', (event) => {
    const choice = event.target.closest('.rating-choice');
    if (choice) paintRating(Number(choice.querySelector('input').value));
  });
  ratingControl.addEventListener('pointerleave', () => paintRating(selectedRating()));
  ratingControl.addEventListener('focusin', (event) => {
    const choice = event.target.closest('.rating-choice');
    if (choice) paintRating(Number(choice.querySelector('input').value));
  });
  ratingControl.addEventListener('focusout', () => paintRating(selectedRating()));
  ratingClear.addEventListener('click', () => {
    ratingOptions.forEach((option) => { option.checked = false; });
    updatePreview();
  });
  form.addEventListener('submit', (event) => {
    if (confirmed.value === '1') return;
    event.preventDefault();
    dialog.showModal();
  });
  cancelSave.addEventListener('click', () => dialog.close());
  confirmSave.addEventListener('click', () => {
    confirmed.value = '1';
    dialog.close();
    form.requestSubmit();
  });
  updatePreview();
}
