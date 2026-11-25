/* Gestor de contexto conversacional RPV | Version 1.0.0 */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.RPVContexto = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  const VERSION = 1;
  const MAX_HISTORY = 16;
  const STORAGE_PREFIX = 'rpv-contexto-v1:';
  const PRODUCTS = ['tinaja', 'caldera', 'estufa'];
  const NUMBER_WORDS = {
    un: 1, uno: 1, una: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5,
    seis: 6, siete: 7, ocho: 8, nueve: 9, diez: 10, once: 11,
    doce: 12, trece: 13, catorce: 14, quince: 15, dieciseis: 16,
    diecisiete: 17, dieciocho: 18, diecinueve: 19, veinte: 20
  };

  const normalize = value => String(value || '').normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
  const clean = value => normalize(value).replace(/[¡!¿?.,;:]+/g, ' ').replace(/\s+/g, ' ').trim();
  const formatNumber = value => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(value);

  function initialState(scope) {
    return {
      version: VERSION,
      productoActual: scope && PRODUCTS.includes(scope) ? scope : null,
      modeloActual: null,
      intencionActual: null,
      preguntaPendiente: null,
      unidadEsperada: null,
      datosCliente: {
        personas: null, litros: null, metrosCuadrados: null,
        cantidadProductos: null, comuna: null, region: null
      },
      historial: [],
      ultimaRespuesta: null,
      ultimaIntencion: null,
      ultimaFirma: null,
      repeticiones: 0
    };
  }

  function detectProduct(text) {
    const t = clean(text);
    const found = [];
    if (/\b(?:tinajas?|tinas?|jacuzzis?|spa)\b/.test(t)) found.push('tinaja');
    if (/\b(?:calderas?|calentadores? de agua|temperar agua)\b/.test(t)) found.push('caldera');
    if (/\b(?:estufas?|calefaccion(?:ar)? espacios?|calefaccion de (?:casa|hogar))\b/.test(t)) found.push('estufa');
    return found.length === 1 ? found[0] : null;
  }

  function detectIntent(text) {
    const t = clean(text);
    if (/\b(?:precio|precios|valor|valores|cuanto (?:cuesta|cuestan|sale|salen|vale|valen)|costo|costos)\b/.test(t)) return 'precio';
    if (/\b(?:despacho|despachan|envio|envian|flete|entrega|transporte)\b/.test(t)) return 'despacho';
    if (/\b(?:cotizar|cotizacion|presupuesto)\b/.test(t)) return 'cotizacion';
    if (/\b(?:garantia|posventa|reparacion)\b/.test(t)) return 'garantia';
    if (/\b(?:instalacion|instalar|montaje)\b/.test(t)) return 'instalacion';
    if (/\b(?:recomiendas?|recomendacion|cual me sirve|cual elegir)\b/.test(t)) return 'recomendacion';
    if (/\b(?:caracteristicas?|medidas?|capacidad|informacion|saber|conocer|mostrar|muestrame)\b/.test(t)) return 'informacion';
    return 'consulta';
  }

  function isCatalogRequest(text) {
    const t = clean(text);
    if (/\b(?:precio|valor|despacho|garantia|instalacion|medidas|litros)\b/.test(t)) return false;
    return /\b(?:catalogo|productos|que venden|que ofrecen|que tienen|que comercializan|cuentame que venden|conocer sus productos|saber de los productos|informacion de productos|algo para calefaccionar)\b/.test(t);
  }

  function conversational(text) {
    const t = clean(text).replace(/^(?:oye|estimados?|equipo rpv)\s+/, '');
    if (/^(?:hola+|hla+|holi+|holaa+|buenas+|buenos dias|buen dia|buenas tardes|buenas noches|que tal|saludos|hey|ola+)(?: como estan?)?$/.test(t))
      return { intent: 'saludo', response: '¡Hola! Bienvenido a RPV Construcciones. 😊 ¿Te interesan nuestras tinajas, calderas o estufas a pellets?' };
    if (/^(?:gracias+|muchas gracias|mil gracias|te agradezco|perfecto gracias)$/.test(t))
      return { intent: 'agradecimiento', response: '¡De nada! ¿Hay algo más en lo que te pueda orientar?' };
    if (/^(?:adios|chao+|chau+|hasta luego|nos vemos|hasta pronto)$/.test(t))
      return { intent: 'despedida', response: '¡Hasta pronto! Gracias por contactar a RPV Construcciones.' };
    if (/^(?:ok|okay|vale|perfecto|entendido|de acuerdo|listo)$/.test(t))
      return { intent: 'continuacion', response: 'Perfecto. ¿Te gustaría consultar otro producto o necesitas una cotización?' };
    return null;
  }

  function greetingPrefix(text) {
    const t = clean(text);
    const match = t.match(/^(hola+|holi+|holaa+|buenas+|buenos dias|buen dia|buenas tardes|buenas noches|que tal|saludos)\b\s*(.*)$/);
    return match && match[2] ? match[2] : null;
  }

  function parseNumber(text) {
    const t = clean(text).replace(/\b(?:aproximadamente|aprox|alrededor de|cerca de|unas?|para|somos|seremos|necesito|quiero|tengo|son|serian)\b/g, ' ').replace(/\s+/g, ' ').trim();
    const digit = t.match(/\b(\d{1,3}(?:[.]\d{3})+|\d{1,6}(?:[.,]\d{1,2})?)\b/);
    if (digit) {
      const raw = digit[1];
      const value = /^\d{1,3}(?:[.]\d{3})+$/.test(raw) ? Number(raw.replace(/\./g, '')) : Number(raw.replace(',', '.'));
      return Number.isFinite(value) && value > 0 ? value : null;
    }
    for (const [word, value] of Object.entries(NUMBER_WORDS)) if (new RegExp('\\b' + word + '\\b').test(t)) return value;
    return null;
  }

  function explicitUnit(text) {
    const t = clean(text);
    if (/\b(?:personas?|puestos?)\b/.test(t)) return 'personas';
    if (/\b(?:litros?|lts?|lt|l)\b/.test(t)) return 'litros';
    if (/\b(?:m2|metros? cuadrados?|mts2)\b/.test(t)) return 'm2';
    if (/\b(?:unidades?|productos?|tinajas?|calderas?|estufas?)\b/.test(t) && /\b(?:cantidad|cotizar|necesito|quiero|son)\b/.test(t)) return 'cantidad';
    return null;
  }

  function parseMarkdown(markdown) {
    const sections = [];
    let current = null;
    for (const line of String(markdown || '').split(/\r?\n/)) {
      const heading = line.match(/^#{2,3}\s+(.+)$/);
      if (heading) {
        if (current && current.text.trim()) sections.push(current);
        current = { title: heading[1].trim(), text: '' };
      } else if (!/^#\s+/.test(line) && !/^>/.test(line)) {
        if (!current) current = { title: 'Información general', text: '' };
        if (line.trim()) current.text += line.replace(/^[-*]\s*/, '').trim() + '\n';
      }
    }
    if (current && current.text.trim()) sections.push(current);
    return sections;
  }

  function createKnowledgeBase(markdown) {
    const sections = Array.isArray(markdown) ? markdown : parseMarkdown(markdown);
    const productOf = value => detectProduct(value);
    const tokens = value => [...new Set(clean(value).match(/[a-z0-9]+/g) || [])].filter(word => word.length > 2);
    function exact(title) {
      const target = clean(title);
      return sections.find(section => clean(section.title) === target)?.text.trim() || null;
    }
    function ask(question, forcedProduct) {
      const direct = exact(question);
      if (direct) return direct;
      const product = forcedProduct || detectProduct(question);
      const intent = detectIntent(question);
      const terms = tokens(question);
      const hits = sections.map(section => {
        const titleTokens = new Set(tokens(section.title));
        const bodyTokens = new Set(tokens(section.text));
        const sectionProduct = productOf(section.title);
        let score = 0;
        terms.forEach(term => { if (titleTokens.has(term)) score += 8; else if (bodyTokens.has(term)) score += 0.3; });
        if (product) score += sectionProduct === product ? 9 : sectionProduct ? -20 : 0;
        if (intent === 'precio') score += detectIntent(section.title) === 'precio' ? 14 : -8;
        if (intent === 'despacho') score += detectIntent(section.title) === 'despacho' ? 12 : 0;
        return { section, score };
      }).sort((a, b) => b.score - a.score);
      return hits[0] && hits[0].score >= 5 ? hits[0].section.text.trim() : null;
    }
    return { sections, exact, ask };
  }

  function canonicalQuestion(intent, product, model) {
    if (intent === 'precio') {
      if (product === 'tinaja' && model) return `¿Cuánto cuesta una tinaja para ${model} personas?`;
      if (product === 'caldera') return '¿Cuáles son los precios de las calderas?';
      if (product === 'tinaja') return '¿Cuáles son los precios de las tinajas?';
      if (product === 'estufa') return '¿Cuáles son los precios de las estufas a pellets?';
    }
    if (intent === 'despacho') return product === 'tinaja' ? '¿Cuánto cuesta enviar una tinaja a regiones?' : '¿Despachan a todo Chile?';
    if (intent === 'garantia') return '¿Qué hago si mi equipo presenta una falla?';
    if (intent === 'instalacion' && product === 'caldera') return '¿Cómo se instala una caldera para tinaja?';
    if (intent === 'informacion' && product === 'tinaja' && model) return `¿Qué medidas tiene la tinaja de ${model} personas?`;
    return null;
  }

  function create(options) {
    const settings = options || {};
    const scope = PRODUCTS.includes(settings.scope) ? settings.scope : null;
    const storage = settings.storage === undefined && typeof sessionStorage !== 'undefined' ? sessionStorage : settings.storage;
    const key = settings.storageKey || STORAGE_PREFIX + (scope || 'general');
    let state = initialState(scope);

    function save() {
      if (!storage) return;
      try { storage.setItem(key, JSON.stringify(state)); } catch (_) { /* almacenamiento opcional */ }
    }
    function restore() {
      if (!storage) return;
      try {
        const saved = JSON.parse(storage.getItem(key) || 'null');
        if (!saved || saved.version !== VERSION || !saved.datosCliente || !Array.isArray(saved.historial)) return;
        state = Object.assign(initialState(scope), saved);
        state.datosCliente = Object.assign(initialState(scope).datosCliente, saved.datosCliente);
        state.historial = saved.historial.slice(-MAX_HISTORY);
      } catch (_) { state = initialState(scope); }
    }
    restore();

    function reset() {
      state = initialState(scope);
      if (storage) { try { storage.removeItem(key); } catch (_) { /* almacenamiento opcional */ } }
      return getState();
    }
    function getState() { return JSON.parse(JSON.stringify(state)); }
    function setPending(question, unit) { state.preguntaPendiente = question || null; state.unidadEsperada = unit || null; }
    function knowledgeAnswer(kb, question, product) { return kb && question ? kb.ask(question, product) : null; }
    function record(intent, product, model, response) {
      const signature = [intent || '', product || '', model || ''].join('|');
      if (signature === state.ultimaFirma && !['saludo', 'agradecimiento', 'continuacion'].includes(intent)) state.repeticiones += 1;
      else state.repeticiones = 0;
      state.ultimaFirma = signature;
      state.ultimaIntencion = intent;
      state.ultimaRespuesta = response;
      state.historial.push({ intencion: intent, producto: product || null, modelo: model || null });
      state.historial = state.historial.slice(-MAX_HISTORY);
      if (state.repeticiones === 1) response = 'Claro, te lo confirmo nuevamente: ' + response;
      else if (state.repeticiones > 1) response += '\n\nSi quieres, dime qué aspecto específico necesitas aclarar y lo revisamos.';
      state.ultimaRespuesta = response;
      save();
      return response;
    }

    function productStart(product) {
      state.productoActual = product; state.modeloActual = null; state.intencionActual = 'informacion';
      if (product === 'tinaja') {
        setPending('personas', 'personas');
        return 'Tenemos tinajas para 4 y 7 personas. ¿Para cuántas personas necesitas una?';
      }
      if (product === 'caldera') {
        setPending('litros', 'litros');
        return 'Contamos con alternativas de calderas para temperar agua de tinajas y piscinas pequeñas, según compatibilidad. ¿Cuántos litros tiene tu tinaja o piscina?';
      }
      setPending('metros_cuadrados', 'm2');
      return 'Contamos con una estufa a pellets de cobertura referencial cercana a 70 m², según las condiciones del recinto. ¿Cuántos metros cuadrados necesitas calefaccionar?';
    }

    function numericPending(text) {
      if (!state.preguntaPendiente || !state.unidadEsperada) return null;
      const value = parseNumber(text);
      if (value === null) return null;
      const explicit = explicitUnit(text);
      if (explicit && explicit !== state.unidadEsperada) {
        const labels = { personas: 'personas', litros: 'litros', m2: 'metros cuadrados', cantidad: 'cantidad de productos' };
        return { response: `Entiendo que indicas ${formatNumber(value)} ${labels[explicit]}. Sin embargo, te había preguntado por ${labels[state.unidadEsperada]}. ¿Qué dato deseas registrar?`, keepPending: true, intent: 'aclaracion' };
      }
      const expected = state.unidadEsperada;
      if (expected === 'personas') {
        if (value > 30) return { response: 'Esa cifra parece alta para una cantidad de personas. ¿Puedes confirmar cuántas personas usarán la tinaja?', keepPending: true, intent: 'aclaracion' };
        state.datosCliente.personas = value;
        const model = value <= 4 ? 4 : value <= 7 ? 7 : null;
        state.modeloActual = model ? `tinaja-${model}-personas` : null;
        setPending(model ? 'confirmar_precio' : null, null);
        if (!model) return { response: `Para ${formatNumber(value)} personas, los modelos informados llegan hasta 7 personas. Podemos consultar una alternativa especial con el equipo comercial.`, intent: 'recomendacion' };
        const fit = value === model ? 'corresponde a la capacidad indicada' : `podría ser una alternativa adecuada para ${formatNumber(value)} personas`;
        return { response: `Nuestra tinaja de ${model} personas ${fit}. ¿Quieres conocer sus características y precio?`, intent: 'recomendacion', model };
      }
      if (expected === 'litros') {
        if (value < 100 || value > 1000000) return { response: `La capacidad de ${formatNumber(value)} litros parece poco habitual. ¿Puedes confirmar el volumen correcto en litros?`, keepPending: true, intent: 'aclaracion' };
        state.datosCliente.litros = value; setPending('uso_caldera', null);
        return { response: `Entiendo: necesitas temperar ${formatNumber(value)} litros. Los rendimientos publicados dependen de la temperatura inicial y final, la instalación y el uso, por lo que no corresponde extrapolarlos automáticamente. ¿La usarás ocasionalmente, con frecuencia, para arriendo o para varias tinajas?`, intent: 'recomendacion' };
      }
      if (expected === 'm2') {
        if (value > 10000) return { response: 'La superficie indicada parece demasiado grande. ¿Puedes confirmar los metros cuadrados?', keepPending: true, intent: 'aclaracion' };
        state.datosCliente.metrosCuadrados = value; setPending('aislacion', null);
        const comparison = value <= 70 ? 'está dentro de la cobertura referencial informada' : 'supera la cobertura referencial informada de aproximadamente 70 m²';
        return { response: `Para ${formatNumber(value)} m², la superficie ${comparison}. El resultado real depende de la aislación, altura y clima; no podemos garantizar cobertura sin evaluar el espacio. ¿Cómo es la aislación del lugar?`, intent: 'recomendacion' };
      }
      state.datosCliente.cantidadProductos = value; setPending(null, null);
      return { response: `Entendido: deseas cotizar ${formatNumber(value)} producto${value === 1 ? '' : 's'}. Para preparar la consulta, indícame el producto y la comuna de destino.`, intent: 'cotizacion' };
    }

    function process(message, contextOptions) {
      const raw = String(message || '').trim();
      if (!raw) return { respuesta: '', ignorado: true, estado: getState() };
      const kb = contextOptions && contextOptions.knowledge;
      const originalGreetingRemainder = greetingPrefix(raw);
      if (originalGreetingRemainder) {
        const nested = process(originalGreetingRemainder, contextOptions);
        if (!nested.ignorado) nested.respuesta = '¡Hola! ' + nested.respuesta;
        return nested;
      }
      const chat = conversational(raw);
      if (chat) return { respuesta: record(chat.intent, state.productoActual, state.modeloActual, chat.response), intencion: chat.intent, estado: getState() };

      const explicitProduct = detectProduct(raw);
      const topicChanged = explicitProduct && explicitProduct !== state.productoActual;
      if (topicChanged) {
        state.productoActual = explicitProduct; state.modeloActual = null; setPending(null, null);
      }

      if (isCatalogRequest(raw)) {
        state.intencionActual = 'catalogo'; state.productoActual = null; state.modeloActual = null; setPending('producto', null);
        const response = '¡Claro! En RPV Construcciones ofrecemos tres líneas principales:\n\n🔥 Calderas para temperar agua de tinajas y piscinas pequeñas, según compatibilidad.\n\n🛁 Tinajas para 4 y 7 personas.\n\n🏠 Estufas a pellets para calefacción de espacios.\n\n¿Sobre cuál te gustaría conocer más?';
        return { respuesta: record('catalogo', null, null, response), intencion: 'catalogo', estado: getState() };
      }

      if (state.preguntaPendiente === 'producto' && explicitProduct) {
        const response = productStart(explicitProduct);
        return { respuesta: record('informacion', explicitProduct, null, response), intencion: 'informacion', estado: getState() };
      }
      if (explicitProduct && ['consulta', 'informacion'].includes(detectIntent(raw))
        && !/\b(?:precio|valor|despacho|envio|garantia|instalacion|medidas|litros|m2|metros cuadrados)\b/.test(clean(raw))) {
        const response = productStart(explicitProduct);
        return { respuesta: record('informacion', explicitProduct, null, response), intencion: 'informacion', estado: getState() };
      }

      const shortChoice = clean(raw);
      if (state.productoActual === 'tinaja' && /^(?:la grande|grande|la de siete|la segunda|esa|quiero esa)$/.test(shortChoice)) {
        state.modeloActual = 'tinaja-7-personas'; setPending('confirmar_precio', null);
        const answer = knowledgeAnswer(kb, '¿Qué medidas tiene la tinaja de 7 personas?', 'tinaja') || 'La grande corresponde al modelo informado para 7 personas.';
        return { respuesta: record('informacion', 'tinaja', state.modeloActual, answer + '\n\n¿Quieres conocer su precio de referencia?'), intencion: 'informacion', estado: getState() };
      }
      if (state.productoActual === 'tinaja' && /^(?:la pequena|pequena|la de cuatro|la primera)$/.test(shortChoice)) {
        state.modeloActual = 'tinaja-4-personas'; setPending('confirmar_precio', null);
        const answer = knowledgeAnswer(kb, '¿Qué medidas tiene la tinaja de 4 personas?', 'tinaja') || 'La pequeña corresponde al modelo informado para 4 personas.';
        return { respuesta: record('informacion', 'tinaja', state.modeloActual, answer + '\n\n¿Quieres conocer su precio de referencia?'), intencion: 'informacion', estado: getState() };
      }

      const yes = /^(?:si|claro|por supuesto|dale|bueno|ya)$/.test(shortChoice);
      const no = /^(?:no|no gracias|por ahora no)$/.test(shortChoice);
      if (yes && state.preguntaPendiente === 'confirmar_precio') {
        const model = /tinaja-(4|7)-personas/.exec(state.modeloActual || '');
        const question = canonicalQuestion('precio', 'tinaja', model ? Number(model[1]) : null);
        const response = knowledgeAnswer(kb, question, 'tinaja') || 'No pude recuperar el precio de referencia. Puedes confirmarlo con el equipo comercial.';
        setPending(null, null);
        return { respuesta: record('precio', 'tinaja', state.modeloActual, response), intencion: 'precio', estado: getState() };
      }
      if (yes && state.preguntaPendiente) {
        return { respuesta: record('aclaracion', state.productoActual, state.modeloActual, 'Para orientarte sin asumir una compra ni autorización, cuéntame a qué parte de la pregunta respondes “sí”.'), intencion: 'aclaracion', estado: getState() };
      }
      if (no && state.preguntaPendiente) {
        setPending(null, null);
        return { respuesta: record('continuacion', state.productoActual, state.modeloActual, 'Entendido. ¿Qué otro dato o producto te gustaría revisar?'), intencion: 'continuacion', estado: getState() };
      }

      if (!topicChanged) {
        const numeric = numericPending(raw);
        if (numeric) {
          if (!numeric.keepPending && numeric.intent !== 'recomendacion') setPending(null, null);
          return { respuesta: record(numeric.intent, state.productoActual, numeric.model || state.modeloActual, numeric.response), intencion: numeric.intent, estado: getState() };
        }
      }
      if (parseNumber(raw) !== null && !explicitUnit(raw) && !state.preguntaPendiente) {
        const response = 'Para interpretar esa cifra correctamente, ¿corresponde a personas, litros, metros cuadrados o cantidad de productos?';
        return { respuesta: record('aclaracion', state.productoActual, state.modeloActual, response), intencion: 'aclaracion', estado: getState() };
      }

      if (state.preguntaPendiente === 'uso_caldera') {
        if (/\b(?:ocasional|frecuente|seguido|diario|arriendo|turismo|varias tinajas|comercial)\b/.test(shortChoice)) {
          setPending(null, null);
          const base = knowledgeAnswer(kb, '¿Qué caldera necesito para una tinaja de mil litros?', 'caldera');
          const response = (base ? base + '\n\n' : '') + 'Con el volumen y uso indicados, el equipo comercial debe confirmar el modelo y la compatibilidad antes de cotizar. ¿Quieres revisar los precios de referencia?';
          return { respuesta: record('recomendacion', 'caldera', null, response), intencion: 'recomendacion', estado: getState() };
        }
      }
      if (state.preguntaPendiente === 'aislacion' && /\b(?:buena|mala|regular|si|no|aislada|sin aislacion)\b/.test(shortChoice)) {
        setPending(null, null);
        const response = 'Gracias. La aislación modifica la cobertura real: un recinto con mayores pérdidas puede requerir una evaluación térmica. También importa la altura, las ventanas, el clima y la evacuación segura de gases. ¿Quieres conocer las características o el precio disponible?';
        return { respuesta: record('recomendacion', 'estufa', null, response), intencion: 'recomendacion', estado: getState() };
      }

      const asksPrice = /\b(?:precio|precios|valor|valores|cuanto (?:cuesta|cuestan)|costo|costos)\b/.test(shortChoice);
      const asksShipping = /\b(?:despacho|despachan|envio|envian|flete|entrega|transporte)\b/.test(shortChoice);
      if (asksPrice && asksShipping) {
        const product = explicitProduct || state.productoActual || scope;
        if (!product) {
          setPending('producto', null);
          const response = '¿Sobre qué producto necesitas precio y despacho: tinajas, calderas o estufas a pellets?';
          return { respuesta: record('precio_despacho', null, null, response), intencion: 'precio_despacho', estado: getState() };
        }
        const modelNumber = /tinaja-(4|7)-personas/.exec(state.modeloActual || '');
        const priceQuestion = canonicalQuestion('precio', product, modelNumber ? Number(modelNumber[1]) : null);
        const price = knowledgeAnswer(kb, priceQuestion, product) || 'El precio debe confirmarse con el equipo comercial.';
        const shipping = knowledgeAnswer(kb, canonicalQuestion('despacho', product), product) || 'El despacho depende del destino.';
        setPending('ubicacion_despacho', null);
        return { respuesta: record('precio_despacho', product, state.modeloActual, price + '\n\nSobre el despacho: ' + shipping), intencion: 'precio_despacho', estado: getState() };
      }

      let intent = detectIntent(raw);
      let product = explicitProduct || state.productoActual || scope;
      if (explicitProduct) state.productoActual = explicitProduct;
      if (intent === 'cotizacion' && product && /\b(?:varias?|cantidad|cuantas?)\b/.test(shortChoice)) {
        setPending('cantidad_productos', 'cantidad');
        const response = `¿Cuánt${product === 'estufa' ? 'as' : 'os'} ${product === 'tinaja' ? 'tinajas' : product === 'caldera' ? 'equipos' : 'estufas'} necesitas cotizar?`;
        return { respuesta: record(intent, product, null, response), intencion: intent, estado: getState() };
      }

      const modelMatch = product === 'tinaja' ? shortChoice.match(/\b([47]|cuatro|siete)\s*personas?\b/) : null;
      if (modelMatch) {
        const model = modelMatch[1] === '4' || modelMatch[1] === 'cuatro' ? 4 : 7;
        state.modeloActual = `tinaja-${model}-personas`;
      }
      const modelNumber = /tinaja-(4|7)-personas/.exec(state.modeloActual || '');
      const canonical = canonicalQuestion(intent, product, modelNumber ? Number(modelNumber[1]) : null);
      let response = knowledgeAnswer(kb, canonical, product) || knowledgeAnswer(kb, raw, product);

      if (!product && ['precio', 'despacho', 'recomendacion'].includes(intent)) {
        response = '¿Sobre qué producto necesitas esa información: tinajas, calderas o estufas a pellets?';
        setPending('producto', null);
      } else if (!response && explicitProduct && intent === 'consulta') {
        response = productStart(explicitProduct);
        intent = 'informacion';
      } else if (!response) {
        response = 'No encuentro información suficiente para responder con seguridad. ¿Puedes indicar si consultas por una tinaja, una caldera o una estufa, y qué dato necesitas?';
      }
      state.intencionActual = intent;
      if (intent === 'despacho') setPending('ubicacion_despacho', null);
      return { respuesta: record(intent, product, state.modeloActual, response), intencion: intent, estado: getState() };
    }

    return { process, reset, getState, setPending };
  }

  return { VERSION, normalize, detectProduct, detectIntent, parseNumber, explicitUnit, createKnowledgeBase, create };
});
