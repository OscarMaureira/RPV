(() => {
  const form = document.querySelector('#review-form');
  if (!form) return;

  const sendButton = document.querySelector('.submit-review');
  const message = document.querySelector('#form-message');
  const dialog = document.querySelector('#confirm-dialog');
  const confirmButton = document.querySelector('#confirm-review');
  const editButton = document.querySelector('#edit-review');
  let redirectPending = false;

  const updatePreview = () => {
    const setValue = (id, value) => {
      const element = document.querySelector(id);
      element.textContent = value;
      element.hidden = !value;
    };

    setValue('#preview-name', form.elements.name.value.trim());
    setValue('#preview-meta', [form.elements.city.value.trim(), form.elements.company.value.trim()].filter(Boolean).join(' · '));
    setValue('#preview-product', form.elements.product.value);
    setValue('#preview-comment', form.elements.comment.value.trim());

    const selectedRating = form.querySelector('input[name="rating"]:checked');
    form.querySelectorAll('.stars label').forEach((label, index) => {
      label.classList.toggle('is-active', Boolean(selectedRating) && index < Number(selectedRating.value));
    });
    const stars = document.querySelector('#preview-stars');
    if (selectedRating) {
      document.querySelector('#preview-stars-filled').textContent = '★'.repeat(Number(selectedRating.value));
      document.querySelector('#preview-stars-empty').textContent = '★'.repeat(5 - Number(selectedRating.value));
      stars.setAttribute('aria-label', `${selectedRating.value} de 5 estrellas`);
      stars.hidden = false;
    } else {
      document.querySelector('#preview-stars-filled').textContent = '';
      document.querySelector('#preview-stars-empty').textContent = '';
      stars.setAttribute('aria-label', 'Valoración no seleccionada');
      stars.hidden = true;
    }

    const previewHasContent = Boolean(
      form.elements.name.value.trim() ||
      form.elements.city.value.trim() ||
      form.elements.company.value.trim() ||
      form.elements.product.value ||
      form.elements.comment.value.trim() ||
      selectedRating
    );
    document.querySelector('.preview-card').classList.toggle('is-empty', !previewHasContent);
  };

  form.addEventListener('input', updatePreview);
  form.addEventListener('change', updatePreview);

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    message.textContent = '';
    message.className = 'form-message';
    if (!form.reportValidity()) return;

    if (typeof dialog.showModal === 'function') {
      const rating = form.querySelector('input[name="rating"]:checked').value;
      const locationAndCompany = [form.elements.city.value.trim(), form.elements.company.value.trim()].filter(Boolean).join(' · ');
      const customer = [form.elements.name.value.trim(), locationAndCompany].filter(Boolean).join(' · ');
      document.querySelector('#confirm-summary').textContent = `${customer} · ${form.elements.product.value} · ${rating}/5 estrellas\n“${form.elements.comment.value.trim()}”`;
      dialog.showModal();
    } else if (window.confirm('¿Confirmas que quieres enviar tu comentario para revisión?')) {
      sendReview();
    }
  });

  editButton.addEventListener('click', () => {
    dialog.close();
    document.querySelector('#name').focus();
  });

  confirmButton.addEventListener('click', sendReview);

  async function sendReview() {
    if (dialog.open) dialog.close();
    const fields = Object.fromEntries(new FormData(form).entries());
    sendButton.disabled = true;
    sendButton.textContent = 'Enviando…';

    try {
      const response = await fetch('/opiniones/api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify(fields),
        credentials: 'same-origin'
      });
      const result = await response.json();
      message.textContent = result.message || (result.ok
        ? 'Gracias. Tu opinión es muy importante para nosotros.'
        : 'No pudimos completar el envío.');
      message.className = `form-message ${result.ok ? 'success' : 'error'}`;
      if (result.ok) {
        form.reset();
        updatePreview();
        if (result.email_sent) {
          redirectPending = true;
          message.textContent += ' Volverás al sitio principal en 2 segundos.';
          window.setTimeout(() => window.location.assign('/'), 2000);
        }
      }
      message.focus();
    } catch (_) {
      message.textContent = 'No pudimos conectar con el servidor. Revisa tu conexión e inténtalo nuevamente.';
      message.className = 'form-message error';
      message.focus();
    } finally {
      if (!redirectPending) sendButton.disabled = false;
      sendButton.innerHTML = 'Enviar comentario <span aria-hidden="true">→</span>';
    }
  }

  updatePreview();
})();
