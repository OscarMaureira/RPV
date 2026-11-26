(() => {
  const form = document.querySelector('#review-form');
  if (!form) return;

  const sendButton = document.querySelector('.submit-review');
  const message = document.querySelector('#form-message');
  const dialog = document.querySelector('#confirm-dialog');
  const confirmButton = document.querySelector('#confirm-review');
  const editButton = document.querySelector('#edit-review');
  const companyName = form.elements.company_name;
  const companyUrl = form.elements.company_url;
  let redirectPending = false;
  companyName.value = '';
  companyUrl.value = '';
  if (sendButton && sendButton.firstChild) sendButton.firstChild.textContent = 'Enviar opinión ';

  const normalizeWebsite = () => {
    const value = companyUrl.value.trim();
    if (value && !/^[a-z][a-z0-9+.-]*:/i.test(value) && /^[^\s/]+\.[^\s]+/.test(value)) {
      companyUrl.value = `https://${value}`;
    }
  };

  const validateBusiness = () => {
    const hasName = Boolean(companyName.value.trim());
    const hasUrl = Boolean(companyUrl.value.trim());
    const hasBusinessData = hasName && hasUrl;
    companyName.setCustomValidity(hasUrl && !hasName ? 'Ingresa el nombre de la empresa para publicar su sitio web.' : '');
    companyUrl.setCustomValidity(hasName && !hasUrl ? 'Ingresa el sitio web de la empresa para publicar sus datos.' : '');
    return hasBusinessData;
  };

  const updatePreview = () => {
    const setValue = (id, value) => {
      const element = document.querySelector(id);
      element.textContent = value;
      element.hidden = !value;
    };

    setValue('#preview-name', form.elements.name.value.trim());
    setValue('#preview-meta', form.elements.city.value.trim());
    setValue('#preview-product', form.elements.product.value);
    setValue('#preview-comment', form.elements.comment.value.trim());

    const hasBusinessData = validateBusiness();
    const businessPreview = document.querySelector('#preview-business');
    const previewCompanyName = document.querySelector('#preview-company-name');
    const previewCompanyUrl = document.querySelector('#preview-company-url');
    const showBusiness = hasBusinessData && Boolean(companyName.value.trim());
    previewCompanyName.textContent = showBusiness ? companyName.value.trim() : '';
    if (showBusiness && companyUrl.value.trim()) {
      previewCompanyUrl.href = companyUrl.value.trim();
      previewCompanyUrl.hidden = false;
    } else {
      previewCompanyUrl.removeAttribute('href');
      previewCompanyUrl.hidden = true;
    }
    businessPreview.hidden = !showBusiness;

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
      companyName.value.trim() ||
      companyUrl.value.trim() ||
      form.elements.product.value ||
      form.elements.comment.value.trim() ||
      selectedRating
    );
    document.querySelector('.preview-card').classList.toggle('is-empty', !previewHasContent);
  };

  form.addEventListener('input', updatePreview);
  form.addEventListener('change', updatePreview);
  companyUrl.addEventListener('blur', () => { normalizeWebsite(); updatePreview(); });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    message.textContent = '';
    message.className = 'form-message';
    normalizeWebsite();
    validateBusiness();
    if (!form.reportValidity()) return;

    if (typeof dialog.showModal === 'function') {
      const rating = form.querySelector('input[name="rating"]:checked').value;
      const customer = [form.elements.name.value.trim(), form.elements.city.value.trim()].filter(Boolean).join(' · ');
      const business = companyName.value.trim() && companyUrl.value.trim()
        ? `\nNegocio autorizado: ${companyName.value.trim()}${companyUrl.value.trim() ? ` · ${companyUrl.value.trim()}` : ''}`
        : '';
      document.querySelector('#confirm-summary').textContent = `${customer} · ${form.elements.product.value} · ${rating}/5 estrellas\n“${form.elements.comment.value.trim()}”${business}`;
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
      if (result.ok) result.email_sent = false;
      message.textContent = result.message || (result.ok
        ? 'Gracias. Tu opinión es muy importante para nosotros.'
        : 'No pudimos completar el envío.');
      message.className = `form-message ${result.ok ? 'success' : 'error'}`;
      if (result.ok) {
        message.textContent = '¡Gracias por compartir su opinión! Hemos recibido su comentario y quedará pendiente de revisión. Una vez aprobado, podrá ser publicado en nuestro sitio web.';
        form.reset();
        updatePreview();
        window.setTimeout(() => {
          if (sendButton && sendButton.firstChild) sendButton.firstChild.textContent = 'Enviar opinión ';
        }, 0);
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
