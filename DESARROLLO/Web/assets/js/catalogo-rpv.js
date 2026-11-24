/* Catálogo comercial compartido por las páginas y ambos asistentes. */
(() => {
  'use strict';
  let current = null, pending = null;
  const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const productValue = product => ({...product});
  function get(id) { return current?.productos.find(p => p.id === id) ? productValue(current.productos.find(p => p.id === id)) : null; }
  function list(category) { return (current?.productos || []).filter(p => !category || p.categoria === category).map(productValue); }
  const calderaImages = Object.freeze({
    'tipo-a': {src:'/assets/images/calderas/caldera-tipo-a-representativa-960.webp', alt:'Ilustración referencial de la caldera a pellet tipo A conectada a una tinaja de madera, en una terraza nocturna'},
    'tipo-b': {src:'/assets/images/calderas/caldera-tipo-b-representativa-960.webp', alt:'Ilustración referencial de la caldera a pellet tipo B conectada a una tinaja de madera, en un patio con jardín'},
    'tipo-c': {src:'/assets/images/calderas/caldera-tipo-c-representativa-960.webp', alt:'Ilustración referencial de la caldera a pellet tipo C conectada a una tinaja de madera, junto a una cabaña'}
  });
  const calderaDifferences = Object.freeze({
    'tipo-a': {badge:'Más rápida', time:'1 hora', pellet:'1 saco'},
    'tipo-b': {badge:'Opción intermedia', time:'2 horas', pellet:'2 sacos'},
    'tipo-c': {badge:'Ciclo de 2,5 horas', time:'2,5 horas', pellet:'2,5 sacos'}
  });
  function updateCalderaImages() {
    document.querySelectorAll('.c-model[id]').forEach(card => {
      const visual = calderaImages[card.id], difference = calderaDifferences[card.id], image = card.querySelector(':scope > picture > img, :scope > img');
      if (!visual || !image) return;
      card.classList.add(`c-model--${card.id.slice(-1)}`);
      image.src = visual.src; image.alt = visual.alt; image.width = 1448; image.height = 1086;
      if (!card.querySelector('.c-image-note')) {
        const note = document.createElement('p');
        note.className = 'c-image-note';
        note.textContent = 'Imagen representativa generada a partir de la ficha comercial.';
        (image.closest('picture') || image).insertAdjacentElement('afterend', note);
      }
      const body = card.querySelector('.c-model-body');
      if (difference && body && !body.querySelector('.c-model-difference')) {
        const highlight = document.createElement('div');
        highlight.className = 'c-model-difference';
        highlight.setAttribute('aria-label', `Diferencias principales: ${difference.badge}, calentamiento ${difference.time}, consumo ${difference.pellet}`);
        highlight.innerHTML = `<span class="c-model-badge"></span><dl class="c-model-specs"><div><dt>Calentamiento</dt><dd></dd></div><div><dt>Pellet por ciclo</dt><dd></dd></div></dl>`;
        highlight.querySelector('.c-model-badge').textContent = difference.badge;
        const values = highlight.querySelectorAll('dd');
        values[0].textContent = difference.time; values[1].textContent = difference.pellet;
        body.prepend(highlight);
      }
    });
  }
  function updatePage() {
    updateCalderaImages();
    document.querySelectorAll('[data-catalog-product][data-catalog-field]').forEach(element => {
      const p = get(element.dataset.catalogProduct), field = element.dataset.catalogField;
      if (p) element.textContent = field === 'precio' ? p.precio_texto : p[field] || 'Consultar con el equipo.';
      else if (field === 'precio') element.textContent = 'Precio a confirmar por WhatsApp';
    });
  }
  async function load() {
    if (pending) return pending;
    pending = (async () => {
      const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 5000);
      try {
        let data = null;
        for (const source of ['/api/catalogo.php', '/assets/data/catalogo-rpv.json']) {
          try {
            const response = await fetch(source, {cache:'no-store', signal:controller.signal});
            if (!response.ok) continue;
            data = await response.json();
            break;
          } catch (error) {
            if (controller.signal.aborted) throw error;
          }
        }
        if (!data) throw new Error('Catálogo no disponible');
        if (data.schema_version !== 1 || typeof data.revision !== 'string' || !Array.isArray(data.productos) || typeof data.conocimiento !== 'string') throw new Error('Catálogo inválido');
        const expected = ['caldera-tipo-a','caldera-tipo-b','caldera-tipo-c','tinaja-4-personas','tinaja-7-personas','estufa-pellet'];
        if (data.productos.length !== expected.length || expected.some(id => !data.productos.some(p => p.id === id && typeof p.precio_texto === 'string' && typeof p.respuesta === 'string'))) throw new Error('Catálogo incompleto');
        current = data; updatePage(); return data;
      } catch (error) {
        current = null; updatePage(); console.warn('RPV: no se pudo cargar el catálogo comercial.'); return null;
      } finally { clearTimeout(timer); pending = null; }
    })();
    return pending;
  }
  const fallback = 'No pude consultar el catálogo comercial actualizado. Para confirmar el precio y las condiciones, continúa con nuestro equipo por WhatsApp.';
  const clarify = 'No encuentro una correspondencia aprobada para ese modelo. ¿Te refieres a la caldera tipo A, B o C? Si tienes una ficha con otra referencia, confírmala con nuestro equipo por WhatsApp antes de cotizar.';
  function categoryFrom(q) {
    if (/\bestufas?\b/.test(q)) return 'estufas';
    if (/\bcalderas?\b/.test(q)) return 'calderas';
    if (/\b(tinajas?|tinas?|jacuzzi)\b/.test(q)) return 'tinajas';
    return null;
  }
  function answer(question) {
    const q = normalize(question).trim();
    const intent = /\b(precios?|valores?|cuanto|costos?|cotiz\w*|inclu\w*|garanti\w*|despach\w*|instalacion|modelos?|tipos?|rendimiento|consum\w*|sacos?|calienta\w*|medidas?|dimensiones)\b/.test(q);
    const rawModel = q.match(/\b(?:tipo|modelo|equipo)\s*([a-z0-9]+)\b/) || q.match(/\bcaldera\s+([a-z0-9])\b(?!\s+pellets?)/) || q.match(/^([abc])$/);
    const model = rawModel && !['de','para','que','con'].includes(rawModel[1]) ? rawModel : null;
    let category = categoryFrom(q), id = null;
    if (model) {
      if (!/^[abc]$/.test(model[1]) || /\bequipo\s*\d/.test(q)) return category === 'tinajas' ? 'No encuentro una correspondencia aprobada para esa tinaja. ¿Buscas la de 4 o 7 personas? Confirma otro modelo con el equipo por WhatsApp.' : category === 'estufas' ? 'No encuentro una correspondencia aprobada para ese modelo de estufa. Confirma su referencia y precio con el equipo por WhatsApp.' : clarify;
      category = 'calderas'; id = 'caldera-tipo-' + model[1];
    }
    if (category === 'tinajas') {
      const capacity = q.match(/\b(4|7|cuatro|siete)\s*(?:personas?)?\b/);
      if (capacity) id = 'tinaja-' + (/^(4|cuatro)$/.test(capacity[1]) ? '4' : '7') + '-personas';
      else if (/\b\d+\s*personas?\b/.test(q)) return 'Las referencias disponibles son tinajas para 4 o 7 personas. ¿Cuál necesitas? Para otra capacidad, confirma las opciones por WhatsApp.';
    }
    if (!intent && !id) return null;
    if (!current) return fallback;
    if (category === 'estufas') id = 'estufa-pellet';
    if (id) {
      const p = get(id);
      if (!p) return clarify;
      const requested = [];
      if (/\binclu\w*/.test(q)) requested.push('Incluye: ' + p.incluye);
      if (/\binstalacion\b/.test(q)) requested.push('Instalación: ' + p.instalacion);
      if (/\bdespach\w*/.test(q)) requested.push('Despacho: ' + p.despacho);
      if (/\bgaranti\w*/.test(q)) requested.push('Garantía: ' + p.garantia);
      return requested.length ? p.nombre + ': ' + p.precio_texto + '. ' + requested.join(' ') + ' ' + p.vigencia_texto : p.respuesta;

    }
    let products = list(category);
    if (category && /\b(mas barata|mas barato|mas economica|mas economico|menor precio)\b/.test(q)) {
      products = products.filter(p => p.modo_precio !== 'consultar').sort((a,b) => a.precio_clp - b.precio_clp).slice(0,1);
    }
    if (!products.length) return 'El precio y las condiciones requieren confirmación del equipo por WhatsApp.';
    const conditionQuery = /\b(inclu\w*|garanti\w*|despach\w*|instalacion)\b/.test(q);
    return products.map(p => conditionQuery ? p.nombre + '. Incluye: ' + p.incluye + ' Instalación: ' + p.instalacion + ' Despacho: ' + p.despacho + ' Garantía: ' + p.garantia : p.respuesta).join('\n\n') + (category === 'calderas' ? '\n\n¿Quieres revisar la caldera tipo A, B o C?' : category === 'tinajas' ? '\n\n¿Te interesa la tinaja para 4 o 7 personas?' : '');
  }
  window.RPVCatalogo = Object.freeze({load, get, list, answer});
  load();
  window.addEventListener('focus', () => load());
})();
