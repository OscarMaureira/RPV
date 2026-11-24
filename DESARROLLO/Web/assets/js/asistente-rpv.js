// Asistente RPV | Version 2026-11-10-11 | ubicacion discreta y guardado automatico
(()=>{'use strict';
const $=id=>document.getElementById(id), L=$('launcher'),C=$('chat'),B=$('backdrop'),M=$('messages'),Q=$('question'),WA=$('chatWa'),S=$('status'),H=$('hint'),anchor=$('anchor'),mobile=matchMedia('(max-width:767px)');
let sections=[],loaded=false,loadAttempted=false,last='',savedScroll=0,preventLaunchClick=false;
const stop=new Set('el la los las un una unos unas de del y o a al por para con en es son se que como cuál cuales qué cual quiero saber me mi sus sobre ustedes tienen hay está estan más puedo podrian favor información informacion necesito hola gracias buen buenas producto productos'.split(' '));
const norm=t=>t.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const tok=t=>[...new Set((norm(t).match(/[a-z0-9]+/g)||[]).map(w=>({tinajas:'tinaja',calderas:'caldera',estufas:'estufa',pellets:'pellet',precios:'precio',modelos:'modelo',envios:'envio',despachos:'despacho',garantias:'garantia',instalaciones:'instalacion',cotizaciones:'cotizacion'}[w]||w)))].filter(w=>w.length>2&&!stop.has(w));
const aliases={tinaja:['tinaja','tinas','spa','jacuzzi','banera','bano','agua'],caldera:['caldera','calentador','calefactor','quemador'],pellet:['pellet','estufa','calefaccion','calor'],precio:['precio','valor','costo','cotizacion','cotizar','presupuesto'],envio:['envio','despacho','transporte','region','entrega'],instalacion:['instalacion','montaje','instalar'],garantia:['garantia','reparacion','posventa'],contacto:['contacto','telefono','whatsapp','correo']};
function expand(words){let out=new Set(words);for(const [key,syn] of Object.entries(aliases)){if(syn.some(w=>out.has(w))) {out.add(key);syn.forEach(w=>out.add(w));}}return out;}
function parseMarkdown(md){const parts=[];let current=null;for(const line of md.split(/\r?\n/)){let match=line.match(/^#{2,3}\s+(.+)$/);if(match){if(current&&current.text.trim())parts.push(current);current={title:match[1].trim(),text:''};continue;}if(/^#\s+/.test(line)||/^>/.test(line))continue;if(!current)current={title:'Información general',text:''};if(line.trim())current.text+=line.replace(/^[-*]\s*/, '').trim()+'\n';}if(current&&current.text.trim())parts.push(current);return parts;}
let knowledgePromise=null;
async function loadKnowledge(){
 if(knowledgePromise)return knowledgePromise;
 S.textContent='Consultando catálogo…';
 knowledgePromise=(async()=>{
  try{
   const catalog=await window.RPVCatalogo.load();
   if(!catalog)throw Error('Catálogo no disponible');
   sections=parseMarkdown(catalog.conocimiento);
   if(sections.length<2)throw Error('Conocimiento incompleto');
   loaded=true;loadAttempted=true;S.classList.remove('is-error');S.textContent='';return true;
  }catch(e){loaded=false;loadAttempted=false;S.classList.add('is-error');S.textContent='No se pudo consultar el catálogo. Puedes confirmar precios por WhatsApp.';return false;}
  finally{knowledgePromise=null;}
 })();
 return knowledgePromise;
}
function productFrom(text){
 const t=norm(text);
 const found=[];
 if(/\b(caldera|calderas|calentador(?:es)?\s+de\s+agua)\b/.test(t))found.push('caldera');
 if(/\b(tinaja|tinajas|tina|tinas|jacuzzi|jacuzzis)\b/.test(t))found.push('tinaja');
 if(/\b(estufa|estufas)\b/.test(t))found.push('estufa');
 return found.length===1?found[0]:null;
}
function priceIntent(text){return /\b(precio|precios|valor|valores|cuanto\s+(?:cuesta|cuestan|sale|salen|vale|valen)|costo|costos|tarifa|tarifas|lista\s+de\s+precios)\b/.test(norm(text));}
function headingSection(title){return sections.find(s=>norm(s.title)===norm(title));}
function respond(question){
 const commercial=window.RPVCatalogo.answer(question);if(commercial!==null)return commercial;
 if(!loaded)return 'No pude cargar la base de conocimiento. Puedes volver a intentarlo o continuar por WhatsApp.';
 const q=norm(question),product=productFrom(question),isPrice=priceIntent(question);
 // La coincidencia literal completa debe ganar a las coincidencias por vocabulario.
 const exact=sections.find(sec=>norm(sec.title).replace(/[^a-z0-9 ]/g,'').trim()===q.replace(/[^a-z0-9 ]/g,'').trim());
 if(exact)return exact.text.trim();
 const terms=tok(question),hits=[];
 for(const sec of sections){
  const head=new Set(tok(sec.title));
  const body=new Set(tok(sec.text));
  const secProduct=productFrom(sec.title);
  let score=0;
  for(const term of terms){
   if(head.has(term))score+=9;
   else if(body.has(term))score+=0.35;
  }
  if(product){if(secProduct===product)score+=8;else if(secProduct)score-=18;}
  if(isPrice){if(priceIntent(sec.title))score+=12;else score-=8;}
  else if(priceIntent(sec.title))score-=7;
  if(score>0)hits.push({score,sec});
 }
 hits.sort((a,b)=>b.score-a.score);
 if(!hits.length||hits[0].score<5)return 'No encuentro una respuesta específica a esa consulta en nuestra información actual. Puedes confirmarla con nuestro equipo por WhatsApp.';
 return hits[0].sec.text.trim();
}
// Contexto conversacional: interpretar respuestas numéricas sin confundir unidades.
let pendingMeasure=null,calderaVolume=null,unconfirmedVolume=null;
// Volúmenes menores de 100 L son inusuales para las tinajas comercializadas.
// Se confirma el dato en vez de inferir que el cliente quiso escribir otro número.
const MIN_TINAJA_LITERS_FOR_AUTO_ADVICE=100;
const fmtNum=n=>new Intl.NumberFormat('es-CL',{maximumFractionDigits:3}).format(n);
function numericInput(text){
 const t=norm(text).trim();
 if(t.length>110||/\b(precio|cuesta|valor|pesos|clp|garantia|despacho|cotizar)\b/.test(t))return null;
 const m=t.match(/^(?:(?:son|tengo|serian|aproximadamente|aprox|unos|unas|cerca de|alrededor de|de|mi (?:casa|espacio|tinaja) (?:tiene|es de)|el (?:espacio|lugar) (?:tiene|es de))\s+)?(\d{1,3}(?:[.,]\d{3})+|\d{1,6}(?:[.,]\d{1,2})?)\s*(m2|m\s*²|metros?\s*cuadrados?|mts?2|litros?|lts?|l)?(?:\s*[,;.]?\s*(?:para\s+)?(?:uso\s+)?(?:diario|todos? los dias|frecuente|ocasional|personal|familiar|arriendo|cabanas?|varias? tinajas?|comercial|semanal))?\s*[.!]?$/);
 if(!m)return null;
 const raw=m[1];
 const value=/^\d{1,3}(?:[.,]\d{3})+$/.test(raw)?Number(raw.replace(/[.,]/g,'')):Number(raw.replace(',','.'));
 if(!Number.isFinite(value)||value<=0)return null;
 const u=m[2]||'';
 return {value,unit:/^(?:l|lt|litro)/.test(u)?'litros':u?'m2':null};
}
function volumeConfirmation(input){
 if(!input||input.unit==='m2')return null;
 if(input.value<MIN_TINAJA_LITERS_FOR_AUTO_ADVICE){
  unconfirmedVolume=input.value;
  return `Entiendo que indicas ${fmtNum(input.value)} litros. Ese volumen es inusualmente pequeño para las tinajas que comercializamos. ¿Confirmas que son ${fmtNum(input.value)} litros, o quisiste indicar otra capacidad, por ejemplo 1.500 litros? Puedes responder «sí, ${fmtNum(input.value)} litros» o escribir la cifra correcta.`;
 }
 if(input.value>1000000){
  unconfirmedVolume=input.value;
  return `La capacidad indicada de ${fmtNum(input.value)} litros es muy elevada para una tinaja. ¿Puedes confirmar el volumen correcto en litros?`;
 }
 unconfirmedVolume=null;
 return null;
}
function volumeConfirmationReply(text){
 if(unconfirmedVolume===null)return null;
 const t=norm(text).trim();
 if(/^(?:no|corrijo|me equivoque|era otro|otro valor|no son esos)$/.test(t))return {reply:'Entendido. ¿Cuántos litros tiene realmente tu tinaja? Puedes escribir, por ejemplo, «1.500 litros».',next:'litros'};
 const yes=/^(?:si|sí|correcto|confirmo|exacto|asi es|son esos|efectivamente)(?:[,. ]+\s*\d[\d.,]*\s*(?:litros?|lts?)?)?[.!]?$/.test(t);
 if(yes){
  const volume=unconfirmedVolume;
  unconfirmedVolume=null;
  calderaVolume=volume;
  return {reply:`Gracias por confirmar los ${fmtNum(volume)} litros. Es una capacidad poco habitual para nuestras calderas de tinajas. Nuestros rendimientos publicados corresponden a 1.000 litros y no permiten asegurar compatibilidad ni tiempos de calentamiento para este volumen. Antes de recomendar un equipo, ¿se trata de una tinaja, un recipiente pequeño u otra aplicación?`,next:'caldera_tipo_aplicacion'};
 }
 const corrected=numericInput(text);
 if(corrected && corrected.unit!=='m2'){
  const check=volumeConfirmation(corrected);
  if(check)return {reply:check,next:'confirmar_litros'};
  calderaVolume=corrected.value;
  return {reply:measureAnswer(corrected,'litros'),next:'caldera_frecuencia'};
 }
 return {reply:'Para evitar recomendar un equipo con una capacidad incorrecta, ¿puedes confirmar los litros o escribir el volumen correcto? Por ejemplo: «1.500 litros».',next:'confirmar_litros'};
}
function calderaFrequency(text){
 const t=norm(text).trim();
 if(t.length>115||/\b(precio|cuesta|valor|pesos|garantia|envio|despacho|estufa)\b/.test(t))return null;
 if(/\b(varias?\s+(?:tinajas?|tinas?)|complejo|multiples?\s+tinajas?|varias?\s+cabanas?)\b/.test(t))return 'multiple';
 if(/\b(arriendo|arrendar|alquiler|turismo|turistico|cabanas?|comercial|negocio)\b/.test(t))return 'arriendo';
 if(/\b(diario|todos? los dias|frecuente|seguido|cada dia|habitual|semanal|fin de semana)\b/.test(t))return 'frecuente';
 if(/\b(ocasional|personal|familiar|a veces|de vez en cuando|poco uso|esporadico)\b/.test(t))return 'ocasional';
 return null;
}
function calderaAdvice(volume,frequency){
 const amount=volume?`Para ${fmtNum(volume)} litros, `:'';
 return amount+'conviene comparar las calderas tipo A, B y C con el equipo según la frecuencia de uso y las conexiones. No tenemos una equivalencia aprobada con los antiguos modelos numerados ni podemos asegurar compatibilidad por el volumen solamente. ¿Quieres revisar precios y condiciones de los tipos A, B y C?';
}

// Flujo de selección de producto tras una respuesta de catálogo.
function generalCatalogReply(reply){
 const r=norm(reply);
 return r.includes('ofrecemos tinajas para 4 y 7 personas')&&r.includes('estufas a pellets');
}
function productSelection(text){
 const t=norm(text).trim();
 if(t.length>95||priceIntent(t)||/\b(despacho|envio|garantia|ubicacion|direccion|instalacion|cotizacion)\b/.test(t))return null;
 if(/\b(estufa|estufas|calefaccionar|calefaccion de casa)\b/.test(t))return 'estufa';
 if(/\b(caldera|calderas|calentar agua|temperar agua|piscina)\b/.test(t))return 'caldera';
 if(/\b(tinaja|tinajas|tinas?|jacuzzi|spa)\b/.test(t))return 'tinaja';
 if(/^\s*(?:quiero|me interesa|busco|necesito|para)\s+(?:calor|calefaccion)\s*$/.test(t))return 'estufa';
 return null;
}
function tinajaCapacity(text){
 const t=norm(text).trim();
 if(t.length>105||/\b(precio|cuanto|valor|caldera|estufa|m2|litros|despacho)\b/.test(t))return null;
 const m=t.match(/\b(4|7|cuatro|siete)\s*(?:personas?|puestos?|pasajeros?)?\b/);
 if(!m)return null;
 if(/\d{2,}/.test(t.replace(m[0],'')))return null;
 return m[1]==='4'||m[1]==='cuatro'?4:7;
}
function tinajaAnswer(capacity){
 const p=window.RPVCatalogo.get('tinaja-'+capacity+'-personas');
 return p?p.respuesta+' ¿La necesitas para uso familiar o arriendo?':'Confirma el precio y las condiciones de esa tinaja con el equipo por WhatsApp.';
}
function productStart(product){
 if(product==='tinaja')return window.RPVCatalogo.list('tinajas').map(p=>p.nombre+': '+p.precio_texto+'.').join(' ')+' ¿Para cuántas personas la necesitas: 4 o 7?';
 if(product==='caldera')return 'Disponemos de calderas tipo A, B y C. Para orientarte en la elección, ¿cuántos litros de agua tiene tu tinaja o piscina pequeña?';
 const p=window.RPVCatalogo.get('estufa-pellet');
 return (p?p.rendimiento:'Confirma las características del equipo con RPV.')+' ¿Cuántos metros cuadrados necesitas calefaccionar?';
}
function productContext(question,previousContext,entered){
 const t=norm(question).trim();
 if(previousContext==='producto'){
   const selected=productSelection(t);
   if(selected==='tinaja'){const chosen=tinajaCapacity(t);if(chosen)return {reply:tinajaAnswer(chosen),next:null};}
   if(selected)return {reply:productStart(selected),next:selected==='tinaja'?'tinaja_personas':selected==='caldera'?'litros':'m2'};
   const cap=tinajaCapacity(t);
   if(cap)return {reply:tinajaAnswer(cap),next:null};
   return null;
 }
 if(previousContext==='tinaja_personas'){
   const cap=tinajaCapacity(t);
   if(cap)return {reply:tinajaAnswer(cap),next:null};
   const selected=productSelection(t);
   if(selected&&selected!=='tinaja')return {reply:productStart(selected),next:selected==='caldera'?'litros':'m2'};
 }
 return null;
}
function measureContext(question,reply){
 const q=norm(question),r=norm(reply);
 if(/\b(estufa|calefaccionar|metros cuadrados|m2)\b/.test(q)&&/\b(metros cuadrados|m2|superficie)\b/.test(r))return 'm2';
 if(/\b(calderas?|tinajas?|piscinas?)\b/.test(q)&&/\b(cuantos litros|cuantos litros|capacidad de tu tinaja|de cuantos litros|litros tiene)\b/.test(r))return 'litros';
 return null;
}
function measureAnswer(input,kind){
 if(!input||!kind)return null;
 if(input.unit&&input.unit!==kind)return 'Para orientarte correctamente, ¿puedes confirmar si esa medida corresponde a '+(kind==='m2'?'metros cuadrados (m²)':'litros (L)')+'?';
 const n=input.value;
 if(kind==='m2'){
  if(n>10000)return 'La superficie indicada parece demasiado grande. ¿Puedes confirmar cuántos m² necesitas calefaccionar?';
  const p=window.RPVCatalogo.get('estufa-pellet');
  return `Para ${fmtNum(n)} m² debemos revisar aislación, altura del recinto y evacuación de gases. ${p?p.rendimiento:'Confirma la cobertura del equipo con RPV.'} No podemos asegurar cobertura sin evaluar el espacio. ¿Tu vivienda tiene buena aislación?`;
 }
 const confirmation=volumeConfirmation(input);
 if(confirmation)return confirmation;
 return `Para ${fmtNum(n)} litros, las referencias publicadas no permiten extrapolar directamente tiempos ni consumo a tu instalación. `+window.RPVCatalogo.list('calderas').map(p=>p.nombre+': '+p.rendimiento).join(' ')+' ¿La usarás ocasionalmente, con frecuencia o para arriendo?';
}
function insulationAnswer(text){
 const t=norm(text);
 if(t.length>110)return null;
 if(/\b(no|mala|poca|deficiente|sin aislacion|mal aislada|mal aislado)\b/.test(t))return 'Gracias. Si la aislación es deficiente, la estufa puede cubrir menos que la cobertura publicada, porque aumentan las pérdidas de calor. Para recomendar un equipo con seguridad se necesita revisar la altura del recinto, las ventanas, la ventilación y la evacuación de gases. ¿Se trata de una casa, cabaña o departamento?';
 if(/\b(si|buena|bien aislada|bien aislado|aislada|aislado)\b/.test(t))return 'Gracias. Una buena aislación favorece el rendimiento, pero la cobertura continúa siendo referencial. También importa la altura del recinto, la zona climática y la salida segura de gases. ¿Se trata de una casa, cabaña o departamento?';
 return null;
}
const TEAM_ACTION=$('teamAction'),LOCATION_SAVE_ACTION=$('locationSaveAction');
const whatsappFor=question=>'https://wa.me/56964170206?text='+encodeURIComponent('Hola, estaba conversando con el Asistente RPV en su sitio web. Mi consulta es: '+question);
const GREETING='¡Hola! Soy el Asistente RPV. Puedo orientarte utilizando nuestra información de productos. ¿Qué deseas consultar?';

let customerLocation=null,locationRequestToken=0;
// Identificador efimero por conversacion; no incluye datos personales.
let locationConversationId=(window.crypto?.randomUUID?.()||('rpv-'+Date.now()+'-'+Math.random().toString(36).slice(2)));
const sentLocationCaptures=new Set();
async function persistLocation(data,captureId){
  if(!data||!Number.isFinite(data.latitud)||!Number.isFinite(data.longitud)||sentLocationCaptures.has(captureId))return false;
  sentLocationCaptures.add(captureId);
  try{
    const response=await fetch('/api/guardar-ubicacion.php',{
      method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify({...data,id_conversacion:locationConversationId,id_captura:captureId})
    });
    if(!response.ok)throw Error('HTTP '+response.status);
    const result=await response.json();
    if(result.ok!==true)throw Error('El servidor rechazo el registro');
    return true;
  }catch(error){
    sentLocationCaptures.delete(captureId);
    console.warn('No se pudo guardar la ubicacion en RPV:',error);
    return false;
  }
}
const needsLocation=(question,reply)=>{
  const q=norm(question),r=norm(reply);
  // Pedidos en los que la comuna o región permite orientar sobre entrega, instalación o cobertura.
  return /\b(despach|envio|enviar|entrega|entregar|transporte|flete|instal|montaje|cobertura|region|comuna|ubicacion|domicilio|direccion|llegan|llegar|donde estoy|mi zona)\w*/.test(q)
    // También detectar respuestas de cotización que soliciten ubicación de destino.
    || /\b(comuna|region|ubicacion|direccion|localidad|ciudad)\b/.test(r)
       && /\b(indica|indicanos|informanos|comparte|proporciona|necesitamos|destino|cotiz|precio|disponibilidad|envio|despacho|entrega)\w*/.test(r);
};
// Identificar afirmaciones que remiten la confirmación al equipo de RPV.
const needsTeamConfirmation=reply=>{
  const r=norm(reply);
  // Direccion que siempre debe ofrecer contacto directo con el equipo.
  if(/\bpasaje\s+el\s+olmo\s+sur\b/.test(r))return true;
  const teams=/\b(nuestro equipo|nuestros asesores|nuestro asesor|equipo comercial|equipo de ventas|con nosotros|con un asesor|con nuestros especialistas|el equipo|nuestro personal|nuestros ejecutivos|nuestros especialistas)\b/;
  const actions=/\b(confirm\w*|verific\w*|valid\w*|revis\w*|coordin\w*|consult\w*|contact\w*|cotiz\w*|disponibilidad)\b/;
  return teams.test(r) && actions.test(r);
};
function offerTeamConfirmation(question,reply=""){
  TEAM_ACTION.replaceChildren();
  const wrap=document.createElement('div');wrap.className='team-confirmation';
  const link=document.createElement('a');link.href='#';link.target='_blank';link.rel='noopener noreferrer';
  const addressMentioned=/\bpasaje\s+el\s+olmo\s+sur\b/.test(norm(reply));
  link.setAttribute('aria-label',addressMentioned?'Consultar por Pasaje El Olmo Sur en WhatsApp':'Confirmar con nuestro equipo por WhatsApp');
  link.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.52 3.48A11.8 11.8 0 0 0 12.04 0C5.56 0 .3 5.25.3 11.73c0 2.07.54 4.09 1.56 5.88L0 24l6.55-1.72a11.7 11.7 0 0 0 5.48 1.4h.01c6.47 0 11.73-5.26 11.73-11.74 0-3.14-1.22-6.09-3.25-8.46ZM12.04 21.7h-.01a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.89 1.02 1.04-3.79-.24-.39a9.76 9.76 0 0 1-1.51-5.23c0-5.4 4.39-9.79 9.79-9.79 2.62 0 5.08 1.02 6.93 2.88a9.73 9.73 0 0 1 2.86 6.93c0 5.4-4.39 9.8-9.78 9.8Zm5.37-7.34c-.29-.15-1.72-.85-1.99-.95-.27-.1-.46-.15-.66.15-.19.29-.76.95-.93 1.14-.17.19-.34.22-.63.07-.29-.15-1.23-.45-2.34-1.44-.86-.76-1.45-1.7-1.62-1.99-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.19-.29.29-.49.1-.19.05-.36-.02-.51-.07-.15-.66-1.59-.9-2.18-.24-.57-.49-.49-.66-.5h-.56c-.19 0-.49.07-.74.36-.25.29-.96.94-.96 2.3s.98 2.67 1.11 2.86c.14.19 1.92 2.94 4.66 4.12.65.28 1.15.44 1.54.56.65.2 1.24.17 1.7.1.52-.08 1.72-.7 1.96-1.37.24-.67.24-1.24.17-1.36-.08-.12-.27-.19-.56-.34Z"/></svg><span>Confirmar con nuestro equipo por WhatsApp</span>';
  if(addressMentioned)link.querySelector('span').textContent='Consultar por WhatsApp: Pasaje El Olmo Sur';
  // La ubicación puede capturarse después de dibujar el botón; calcular el enlace al hacer clic.
  const updateLink=()=>{
    const location=locationText();
    const context=reply?'\nInformación entregada por el asistente: '+reply:'';
    const message='Hola, estaba conversando con el Asistente RPV en su sitio web. Quisiera confirmar con su equipo lo siguiente:\nConsulta: '+question+context+(location?'\nUbicación proporcionada: '+location:'\nUbicación: no proporcionada.');
    link.href='https://wa.me/56964170206?text='+encodeURIComponent(message);
  };
  updateLink();link.addEventListener('click',updateLink);
  wrap.append(link);TEAM_ACTION.append(wrap);TEAM_ACTION.classList.add('has-action');
}
const locationText=()=>customerLocation?.description||'';
function updateWhatsappQuestion(question){
  const extra=locationText();
  WA.href=whatsappFor(question+(extra?'\nUbicación proporcionada voluntariamente: '+extra:''));
}
function offerLocation(question){
  M.querySelectorAll('.location-request').forEach(el=>el.remove());
  const token=++locationRequestToken;
  const panel=document.createElement('div');panel.className='location-request';
  const prompt=document.createElement('p');prompt.textContent='Para orientar tu consulta según tu zona, puedes compartir tu ubicación o indicar tu comuna y región.';
  const note=document.createElement('div');note.className='location-note';note.textContent='Compartir tu ubicación es opcional. Si eliges «Compartir mi ubicación» y permites el acceso del navegador, RPV guardará automáticamente la comuna, provincia, región, coordenadas y precisión para gestionar tu consulta. Las coordenadas también se consultan en servicios externos para identificar tu zona. WhatsApp es independiente.';
  const actions=document.createElement('div');actions.className='location-actions';
  const locate=document.createElement('button');locate.type='button';locate.textContent='📍 Compartir mi ubicación';
  const manual=document.createElement('div');manual.className='location-manual';
  const input=document.createElement('input');input.type='text';input.placeholder='Comuna y región';input.setAttribute('aria-label','Ingresar comuna y región');input.maxLength=120;
  const save=document.createElement('button');save.type='button';save.textContent='Usar esta ubicación';
  manual.append(input,save);actions.append(locate);panel.append(prompt,actions,manual,note);M.append(panel);
  function confirmLocation(description,data=null){
    if(token!==locationRequestToken||!panel.isConnected)return;
    customerLocation={description,data};
    panel.remove();
    const visibleLocation=data ? [data.comuna?'Comuna: '+data.comuna:'',data.provincia?'Provincia: '+data.provincia:'',data.region?'Región: '+data.region:''].filter(Boolean).join('. ') : description;
    const message=add('Ubicación indicada en esta conversación: '+(visibleLocation||'Zona pendiente de confirmar')+'.\nPuedes continuar con tu consulta. La disponibilidad de despacho o instalación debe confirmarla nuestro equipo.',false);
    updateWhatsappQuestion(last||question);
    offerTeamConfirmation(last||question);
    anchorResponse(message);
    LOCATION_SAVE_ACTION.replaceChildren();
    LOCATION_SAVE_ACTION.classList.remove('has-action');
    if(!data||!Number.isFinite(data.latitud)||!Number.isFinite(data.longitud)){
      // Sin coordenadas no hay insercion: la ubicacion manual permanece disponible para WhatsApp.
      return;
    }
    // El usuario ya eligio compartir ubicación tras leer el aviso de almacenamiento.
    // No requerir una segunda confirmacion: guardar inmediatamente.
    const captureId=window.crypto?.randomUUID?.()||('cap-'+Date.now()+'-'+Math.random().toString(36).slice(2));
    // Guardar silenciosamente. Solo avisar si ocurre un error real.
    (async()=>{
      const ok=await persistLocation(data,captureId);
      if(ok||token!==locationRequestToken)return;
      const feedback=document.createElement('div');feedback.className='location-save-feedback';feedback.setAttribute('role','alert');
      feedback.textContent='No fue posible procesar la ubicación. Puedes continuar por WhatsApp.';
      LOCATION_SAVE_ACTION.replaceChildren(feedback);LOCATION_SAVE_ACTION.classList.add('has-action');
    })();
  }
  let pendingCoordinates=null;
  save.addEventListener('click',()=>{
    const value=input.value.trim();if(!value){input.focus();return;}
    const raw=value.split(',').map(x=>x.trim()).filter(Boolean);
    const prior=customerLocation?.data||pendingCoordinates;
    const data=prior?{...prior,comuna:raw[0]||prior.comuna,region:raw[1]||prior.region}:null;
    const coords=prior?' Coordenadas: '+prior.latitud.toFixed(5)+', '+prior.longitud.toFixed(5)+'. Mapa: '+prior.url_google_maps+'.':'';
    confirmLocation('Comuna y región indicadas por el cliente: '+value+'.'+coords,data);
  });
  input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();save.click();}});
  // Geocodificación inversa con respaldo. Los límites administrativos no siempre
  // están presentes en los proveedores: nunca completar con datos inventados.
  const clean=value=>typeof value==='string'?value.trim():'';
  async function getJSON(url,ms=8500){
    const controller=new AbortController();
    const timer=setTimeout(()=>controller.abort(),ms);
    try{
      const response=await fetch(url,{signal:controller.signal,headers:{Accept:'application/json'}});
      if(!response.ok)throw Error('Servicio geográfico HTTP '+response.status);
      return await response.json();
    }finally{clearTimeout(timer);}
  }
  async function reverseNominatim(latitude,longitude){
    const url=new URL('https://nominatim.openstreetmap.org/reverse');
    url.searchParams.set('format','jsonv2');
    url.searchParams.set('lat',String(latitude));
    url.searchParams.set('lon',String(longitude));
    url.searchParams.set('zoom','18');
    url.searchParams.set('addressdetails','1');
    url.searchParams.set('accept-language','es');
    const data=await getJSON(url.toString());
    const a=data?.address||{};
    if(clean(a.country_code).toLowerCase()!=='cl')return null;
    return {
      comuna:clean(a.municipality)||clean(a.city_district)||clean(a.town)||clean(a.village)||clean(a.city),
      provincia:clean(a.county)||clean(a.state_district),
      region:clean(a.state)||clean(a.region)
    };
  }
  async function reverseBigDataCloud(latitude,longitude){
    const url=new URL('https://api.bigdatacloud.net/data/reverse-geocode-client');
    url.searchParams.set('latitude',String(latitude));
    url.searchParams.set('longitude',String(longitude));
    url.searchParams.set('localityLanguage','es');
    const data=await getJSON(url.toString());
    if(clean(data?.countryCode).toUpperCase()!=='CL')return null;
    const admin=Array.isArray(data?.localityInfo?.administrative)?data.localityInfo.administrative:[];
    const byName=pattern=>admin.find(item=>pattern.test(clean(item?.name)))?.name||'';
    // BigDataCloud puede identificar la localidad, pero no siempre la comuna.
    // Usar los campos administrativos explícitos cuando estén disponibles.
    const province=admin.find(item=>/provincia/i.test(clean(item?.description)||clean(item?.name)));
    const commune=admin.find(item=>/comuna|municipalidad|municipio/i.test(clean(item?.description)));
    return {
      comuna:clean(commune?.name)||clean(data?.locality),
      provincia:clean(province?.name),
      region:clean(data?.principalSubdivision)||clean(byName(/Regi[oó]n/i))
    };
  }
  async function reverseGeocode(latitude,longitude){
    let result=null;
    try{result=await reverseNominatim(latitude,longitude);}
    catch(error){console.warn('No disponible Nominatim:',error);}
    if(result?.comuna&&result?.region)return result;
    try{
      const fallback=await reverseBigDataCloud(latitude,longitude);
      if(fallback){
        result={comuna:result?.comuna||fallback.comuna,
          provincia:result?.provincia||fallback.provincia,
          region:result?.region||fallback.region};
      }
    }catch(error){console.warn('No disponible geocodificación de respaldo:',error);}
    return result&&Object.values(result).some(Boolean)?result:null;
  }
  locate.addEventListener('click',()=>{
    if(!navigator.geolocation||!window.isSecureContext){note.textContent='Tu navegador no permite acceder a la ubicación en este contexto. Usa HTTPS o escribe tu comuna y región.';return;}
    locate.disabled=true;locate.textContent='Buscando ubicación…';
    navigator.geolocation.getCurrentPosition(async position=>{
      if(token!==locationRequestToken||!panel.isConnected)return;
      const {latitude,longitude,accuracy}=position.coords;
      const coords=latitude.toFixed(5)+', '+longitude.toFixed(5);
      const map='https://www.google.com/maps?q='+encodeURIComponent(latitude+','+longitude);
      const accuracyText=Number.isFinite(accuracy)?' (precisión aproximada: '+Math.round(accuracy)+' m)':'';
      let administrative=null;
      locate.textContent='Identificando comuna y región…';
      try{administrative=await reverseGeocode(latitude,longitude);}
      catch(error){console.warn('No se pudo identificar la división administrativa',error);}
      if(token!==locationRequestToken||!panel.isConnected)return;
      const parts=[];
      if(administrative?.comuna)parts.push('Comuna: '+administrative.comuna);
      if(administrative?.provincia)parts.push('Provincia: '+administrative.provincia);
      if(administrative?.region)parts.push('Región: '+administrative.region);
      const description=(parts.length?parts.join('. ')+'. ':'')+'Coordenadas: '+coords+accuracyText+'. Mapa: '+map;
      if(parts.length){
        confirmLocation(description,{
          comuna:administrative?.comuna||null,provincia:administrative?.provincia||null,
          region:administrative?.region||null,pais:'Chile',latitud:latitude,longitud:longitude,
          precision_metros:Number.isFinite(accuracy)?accuracy:null,url_google_maps:map,fuente_ubicacion:'navegador'
        });
        add('Zona identificada de forma aproximada. Confirma tu comuna antes de solicitar un despacho o instalación.',false);
      }else{
        // Mantener el formulario disponible en vez de cerrar el panel sin comuna.
        pendingCoordinates={comuna:null,provincia:null,region:null,pais:'Chile',latitud:latitude,longitud:longitude,precision_metros:Number.isFinite(accuracy)?accuracy:null,url_google_maps:map,fuente_ubicacion:'navegador'};
        customerLocation={description,data:pendingCoordinates};
        updateWhatsappQuestion(last||question);
        offerTeamConfirmation(last||question);
        locate.disabled=false;
        locate.textContent='📍 Volver a intentar';
        prompt.textContent='Se obtuvieron las coordenadas, pero los servicios no identificaron la comuna. Escríbela junto con tu región para completar la ubicación.';
        note.textContent='Se obtuvo la ubicación, pero no fue posible confirmar la comuna. Escríbela junto a tu región para continuar.';
        input.focus({preventScroll:true});
        anchorResponse(panel);
      }
    },error=>{
      if(token!==locationRequestToken||!panel.isConnected)return;
      locate.disabled=false;locate.textContent='📍 Compartir mi ubicación';
      note.textContent=error.code===1?'No se autorizó el acceso a la ubicación. Puedes escribir tu comuna y región.':error.code===3?'La solicitud de ubicación tardó demasiado. Intenta nuevamente o escribe tu comuna y región.':'No fue posible obtener tu ubicación. Puedes escribir tu comuna y región.';
    },{enableHighAccuracy:false,timeout:12000,maximumAge:60000});
  });
  anchorResponse(panel);
}

function removeScrollSpace(){M.querySelectorAll('.response-scroll-space').forEach(el=>el.remove());}
function add(t,user){const d=document.createElement('div');d.className='msg'+(user?' user':'');d.textContent=t;M.append(d);return d;}
const pinnedQuestion=$('pinnedQuestion'),pinnedText=$('pinnedText');
function showPinnedQuestion(text){
  pinnedText.textContent=text;
  pinnedQuestion.classList.add('visible');
  pinnedQuestion.scrollTop=0;
}
function hidePinnedQuestion(){
  pinnedQuestion.classList.remove('visible');
  pinnedText.textContent='';
}
function anchorResponse(element){
  // La pregunta vive fuera de .messages. La respuesta puede desplazarse sin ocultarla.
  removeScrollSpace();
  requestAnimationFrame(()=>{
    if(!element.isConnected)return;
    M.scrollTop+=element.getBoundingClientRect().top-M.getBoundingClientRect().top-12;
  });
}
// Escritura progresiva: solo las respuestas del asistente, no los mensajes del cliente.
// Un mensaje nuevo termina la animación anterior; Limpiar la cancela sin reponerlo.
const reducedMotion=matchMedia('(prefers-reduced-motion: reduce)');
let activeTyping=null;
function stopTyping(complete=false){
  if(!activeTyping)return;
  const state=activeTyping;
  activeTyping=null;
  clearTimeout(state.timer);
  state.node.classList.remove('is-typing');
  if(complete&&state.node.isConnected){
    state.node.textContent=state.text;
    if(state.after)state.after();
    anchorResponse(state.node);
  }
}
function typeAssistant(text,after=null){
  stopTyping(true);
  const node=add('',false);
  if(reducedMotion.matches){
    node.textContent=text;
    if(after)after();
    anchorResponse(node);
    return node;
  }
  const state={node,text,after,index:0,timer:null};
  activeTyping=state;
  node.classList.add('is-typing');
  // Anclar al inicio antes de iniciar la escritura, sin desplazar hacia el final.
  anchorResponse(node);
  const step=()=>{
    if(activeTyping!==state||!node.isConnected)return;
    // Dos caracteres por iteración: animación legible sin esperas excesivas.
    state.index=Math.min(text.length,state.index+2);
    node.textContent=text.slice(0,state.index);
    if(state.index<text.length){
      state.timer=setTimeout(step,20);
    }else{
      activeTyping=null;
      node.classList.remove('is-typing');
      if(state.after)state.after();
      anchorResponse(node);
    }
  };
  state.timer=setTimeout(step,160);
  return node;
}
function clearConversation(){
  stopTyping(false);
  M.replaceChildren();
  TEAM_ACTION.replaceChildren();TEAM_ACTION.classList.remove('has-action');
  LOCATION_SAVE_ACTION.replaceChildren();LOCATION_SAVE_ACTION.classList.remove('has-action');
  hidePinnedQuestion();
  add(GREETING,false);
  M.scrollTop=0;
  Q.value='';last='';pendingMeasure=null;calderaVolume=null;unconfirmedVolume=null;customerLocation=null;locationConversationId=(window.crypto?.randomUUID?.()||('rpv-'+Date.now()+'-'+Math.random().toString(36).slice(2)));++locationRequestToken;
  WA.href='https://wa.me/56964170206?text='+encodeURIComponent('Hola, estaba conversando con el Asistente RPV en su sitio web y quisiera continuar por WhatsApp.');
  Q.focus({preventScroll:true});
}
let catalogSendGeneration=0;
$('clearChat').addEventListener('click',()=>{++catalogSendGeneration;clearConversation();});
async function send(t){
  t=t.trim();if(!t)return;
  const generation=++catalogSendGeneration;
  await loadKnowledge();
  if(generation!==catalogSendGeneration||!C.classList.contains('open'))return;
  stopTyping(true);
  last=t;
  M.querySelectorAll('.location-request,.team-confirmation').forEach(el=>el.remove());
  TEAM_ACTION.replaceChildren();TEAM_ACTION.classList.remove('has-action');
  ++locationRequestToken;
  removeScrollSpace();
  M.querySelectorAll('.msg.user.current-question').forEach(el=>el.classList.remove('current-question'));
  const currentQuestion=add(t,true);
  currentQuestion.classList.add('current-question');
  showPinnedQuestion(t);
  M.scrollTop=M.scrollHeight;
  const entered=numericInput(t);
  const previousContext=pendingMeasure;
  const commercialReply=window.RPVCatalogo.answer(t);
  let reply=null,route=null,nextContext=null;
  if(commercialReply!==null){reply=commercialReply;}else if(previousContext==='confirmar_litros'){
    route=volumeConfirmationReply(t);
    if(route){reply=route.reply;nextContext=route.next;}
  }else if(previousContext==='caldera_tipo_aplicacion'){
    const kind=norm(t);
    if(/\b(tinaja|tina|recipiente|deposito|piscina|otro|agua)\b/.test(kind)){
      reply='Gracias por aclararlo. Debido al volumen poco habitual, corresponde confirmar la compatibilidad técnica directamente con nuestro equipo antes de recomendar una caldera. ¿Quieres que te ayudemos a consultar por este caso?';
    }else{
      reply='¿Se trata de una tinaja, un recipiente pequeño o una aplicación diferente? Así evitamos recomendarte un equipo incorrecto.';
      nextContext='caldera_tipo_aplicacion';
    }
  }else{
    route=productContext(t,previousContext,entered);
    if(route){reply=route.reply;nextContext=route.next;}
    else if(previousContext==='aislacion')reply=insulationAnswer(t);
    else if(previousContext==='caldera_frecuencia'){
      const freq=calderaFrequency(t);
      if(freq)reply=calderaAdvice(calderaVolume,freq);
      else if(entered){
        if(entered.unit==='m2'){
          reply='Para elegir la caldera necesitamos la capacidad en litros, no metros cuadrados. ¿Cuántos litros tiene tu tinaja?';
          nextContext='litros';
        }else{
          reply=measureAnswer(entered,'litros');
          if(unconfirmedVolume!==null)nextContext='confirmar_litros';
          else{calderaVolume=entered.value;nextContext='caldera_frecuencia';}
        }
      }
    }
    else if(previousContext&&entered)reply=measureAnswer(entered,previousContext);
  }
  if(!reply)reply=respond(t);
  pendingMeasure=nextContext;
  if(commercialReply===null&&nextContext===null){
    if(previousContext==='m2'&&entered&&reply.includes('aislación'))pendingMeasure='aislacion';
    else if(previousContext==='litros'&&entered&&(!entered.unit||entered.unit==='litros')){
      if(unconfirmedVolume!==null)pendingMeasure='confirmar_litros';
      else if(entered.value<=1000000){
        calderaVolume=entered.value;
        const freq=calderaFrequency(t);
        if(freq)reply=calderaAdvice(calderaVolume,freq);
        else pendingMeasure='caldera_frecuencia';
      }
    }else if(previousContext==='caldera_frecuencia'&&reply.includes('¿La usarás'))pendingMeasure='caldera_frecuencia';
    else if(!entered && previousContext!=='confirmar_litros' && previousContext!=='caldera_tipo_aplicacion'){
      pendingMeasure=measureContext(t,reply);
      if(pendingMeasure==='litros'){calderaVolume=null;unconfirmedVolume=null;}
    }
  }
  if(!route&&generalCatalogReply(reply)){
    reply=reply.trim()+' ¿Te interesan las tinajas, las calderas o las estufas a pellets? Puedes responder con el producto que buscas.';
    pendingMeasure='producto';
  }
   updateWhatsappQuestion(t);
   Q.value='';
   const askLocation=needsLocation(t,reply)&&!customerLocation;
   const askTeam=needsTeamConfirmation(reply);
   // Renderizar la acción en forma inmediata, sin depender de la animación de escritura.
   // El historial de mensajes puede desplazarse, pero la acción permanece visible.
   if(askTeam)offerTeamConfirmation(t,reply);
   typeAssistant(reply,()=>{
     if(askLocation)offerLocation(t);
   });
}
$('form').addEventListener('submit',e=>{e.preventDefault();send(Q.value)});document.querySelectorAll('[data-q]').forEach(b=>b.addEventListener('click',()=>send(b.dataset.q)));
function updateSticky(){if(!mobile.matches||C.classList.contains('open')||L.classList.contains('dragged')){if(!mobile.matches)L.classList.remove('sticky');return;}L.classList.toggle('sticky',anchor.getBoundingClientRect().top<9);}
window.addEventListener('scroll',updateSticky,{passive:true});window.addEventListener('resize',updateSticky);window.addEventListener('pageshow',updateSticky);
function openChat(){if(preventLaunchClick)return;L.style.display='none';H.style.display='none';C.classList.add('open');if(mobile.matches){savedScroll=scrollY;document.documentElement.classList.add('locked');document.body.classList.add('locked');document.body.style.top='-'+savedScroll+'px';B.classList.add('show');C.setAttribute('aria-modal','true');}loadKnowledge();requestAnimationFrame(()=>{if(C.classList.contains('open'))Q.focus({preventScroll:true});});}
function closeChat(){C.classList.remove('open');B.classList.remove('show');document.documentElement.classList.remove('locked');document.body.classList.remove('locked');document.body.style.top='';C.setAttribute('aria-modal','false');if(mobile.matches)window.scrollTo(0,savedScroll);L.style.display='';H.style.display='';C.classList.remove('dragged');L.classList.remove('dragged');for(let el of [L,C]){el.style.left='';el.style.right='';el.style.top='';el.style.bottom='';}requestAnimationFrame(updateSticky);}
L.addEventListener('click',openChat);$('close').addEventListener('click',closeChat);B.addEventListener('click',closeChat);B.addEventListener('touchmove',e=>e.preventDefault(),{passive:false});window.addEventListener('keydown',e=>{if(e.key==='Escape'&&C.classList.contains('open'))closeChat();});
function drag(el,handle){let st=null,move=false;handle.addEventListener('pointerdown',e=>{if(e.target.closest('button')&&el!==L)return;const rect=el.getBoundingClientRect();let absolute=getComputedStyle(el).position==='absolute';const bound=absolute?el.offsetParent.getBoundingClientRect():{left:0,top:0,width:innerWidth,height:innerHeight};st={x:e.clientX,y:e.clientY,left:rect.left-bound.left,top:rect.top-bound.top,bound};move=false;handle.setPointerCapture(e.pointerId);});handle.addEventListener('pointermove',e=>{if(!st)return;const dx=e.clientX-st.x,dy=e.clientY-st.y;if(Math.abs(dx)+Math.abs(dy)>6)move=true;if(!move)return;el.classList.add('dragged');el.style.left=Math.max(0,Math.min(st.bound.width-el.offsetWidth,st.left+dx))+'px';el.style.top=Math.max(0,Math.min(st.bound.height-el.offsetHeight,st.top+dy))+'px';el.style.right='auto';el.style.bottom='auto';});handle.addEventListener('pointerup',()=>{if(move&&el===L){preventLaunchClick=true;setTimeout(()=>preventLaunchClick=false,180);}st=null;});handle.addEventListener('pointercancel',()=>{st=null;});}drag(L,L);drag(C,$('drag'));requestAnimationFrame(updateSticky);
})();

/* WEB: muestra solo la tarjeta individual del producto señalado. */
(()=>{
  const panel=document.getElementById('productDetailPanel');
  const cards=[...document.querySelectorAll('#products > .cards > .card')];
  if(!panel||panel.dataset.disabled==='true'||cards.length!==3)return;
  const desktop=matchMedia('(min-width:768px) and (hover:hover) and (pointer:fine)');
  const detail=[
    'Conoce alternativas de calefacción para tinajas. Nuestro equipo puede orientarte sobre compatibilidad, instalación y opciones para tu proyecto.',
    'Disfruta momentos de descanso y bienestar al aire libre. Consulta por alternativas, características y opciones adecuadas para tu espacio.',
    'Una opción para dar calidez y confort a tu hogar. Consulta por modelos, características, instalación y cuidados.'
  ];
  let current=null,closeTimer=null;
  function position(card){
    const r=card.getBoundingClientRect();
    const w=panel.offsetWidth,h=panel.offsetHeight;
    const left=Math.max(12,Math.min(innerWidth-w-12,r.left+(r.width-w)/2));
    const above=r.top-h-10;
    const top=above>=12?above:Math.max(12,Math.min(innerHeight-h-12,r.top));
    panel.style.left=left+'px';panel.style.top=top+'px';
  }
  function open(index){
    if(!desktop.matches)return;
    clearTimeout(closeTimer);
    const card=cards[index];
    if(current!==index){
      cards.forEach(c=>c.classList.remove('detail-active'));
      card.classList.add('detail-active');
      const h=card.querySelector('h3');
      const intro=card.querySelector('p');
      const link=card.querySelector('a');
      panel.replaceChildren();
      const body=document.createElement('div');body.className='product-detail-body';
      const kicker=document.createElement('div');kicker.className='product-detail-kicker';kicker.textContent='Conoce nuestras soluciones';
      const title=document.createElement('h3');title.textContent=h.textContent;
      const summary=document.createElement('p');summary.className='product-detail-summary';summary.textContent=intro.textContent;
      const more=document.createElement('p');more.className='product-detail-extra';more.textContent=detail[index];
      const divider=document.createElement('div');divider.className='product-detail-divider';
      const label=document.createElement('p');label.className='product-detail-label';label.textContent='Te asesoramos en';
      const topics=document.createElement('ul');topics.className='product-detail-topics';
      const options=[['Compatibilidad','Instalación','Cotización'],['Modelos y opciones','Características','Cotización'],['Modelos y opciones','Instalación','Cotización']];
      options[index].forEach(value=>{const item=document.createElement('li');item.textContent=value;topics.append(item)});
      const action=link.cloneNode(true);const actionLabel=action.querySelector('span');if(actionLabel)actionLabel.textContent='Consultar por WhatsApp';
      body.append(kicker,title,summary,more,divider,label,topics,action);panel.append(body);
      current=index;
    }
    panel.classList.add('is-open');panel.setAttribute('aria-hidden','false');
    position(card);
  }
  function close(){
    clearTimeout(closeTimer);current=null;
    panel.classList.remove('is-open');panel.setAttribute('aria-hidden','true');
    cards.forEach(c=>c.classList.remove('detail-active'));
  }
  function delayClose(){clearTimeout(closeTimer);closeTimer=setTimeout(()=>{
    if(!panel.matches(':hover')&&!cards.some(c=>c.matches(':hover')||c.contains(document.activeElement))&&!panel.contains(document.activeElement))close();
  },210)}
  cards.forEach((card,index)=>{
    card.addEventListener('pointerenter',()=>open(index));
    card.addEventListener('pointerleave',delayClose);
    card.addEventListener('focusin',()=>open(index));
    card.addEventListener('focusout',delayClose);
  });
  panel.addEventListener('pointerenter',()=>clearTimeout(closeTimer));
  panel.addEventListener('pointerleave',delayClose);
  panel.addEventListener('focusout',delayClose);
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
  desktop.addEventListener('change',close);
  window.addEventListener('scroll',()=>{if(current!==null){const r=cards[current].getBoundingClientRect();if(r.bottom<0||r.top>innerHeight)close();else position(cards[current])}},{passive:true});
  window.addEventListener('resize',()=>{if(current!==null){if(!desktop.matches)close();else position(cards[current])}},{passive:true});
})();
