// Asistente RPV | Version 2026-11-10-7 | ubicacion discreta y guardado automatico
(()=>{'use strict';
if(window.__RPV_CHAT_INITIALIZED__)return;
window.__RPV_CHAT_INITIALIZED__=true;
const $=id=>document.getElementById(id), L=$('launcher'),C=$('chat'),B=$('backdrop'),M=$('messages'),Q=$('question'),WA=$('chatWa'),S=$('status'),H=$('hint'),anchor=$('anchor'),mobile=matchMedia('(max-width:767px)');
let sections=[],loaded=false,loadAttempted=false,last='',savedScroll=0,preventLaunchClick=false;
if(!window.RPVContexto){console.error('RPV: contexto-rpv.js no está disponible');return;}
let knowledgeBase=null;
const conversationContext=window.RPVContexto.create({scope:'caldera',storageKey:'rpv-contexto-v1:calderas'});
const stop=new Set('el la los las un una unos unas de del y o a al por para con en es son se que como cuál cuales qué cual quiero saber me mi sus sobre ustedes tienen hay está estan más puedo podrian favor información informacion necesito hola gracias buen buenas producto productos'.split(' '));
const norm=t=>t.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const tok=t=>[...new Set((norm(t).match(/[a-z0-9]+/g)||[]).map(w=>({tinajas:'tinaja',calderas:'caldera',estufas:'estufa',pellets:'pellet',precios:'precio',modelos:'modelo',envios:'envio',despachos:'despacho',garantias:'garantia',instalaciones:'instalacion',cotizaciones:'cotizacion'}[w]||w)))].filter(w=>w.length>2&&!stop.has(w));
const aliases={tinaja:['tinaja','tinas','spa','jacuzzi','banera','bano','agua'],caldera:['caldera','calentador','calefactor','quemador'],pellet:['pellet','estufa','calefaccion','calor'],precio:['precio','valor','costo','cotizacion','cotizar','presupuesto'],envio:['envio','despacho','transporte','region','entrega'],instalacion:['instalacion','montaje','instalar'],garantia:['garantia','reparacion','posventa'],contacto:['contacto','telefono','whatsapp','correo']};
function expand(words){let out=new Set(words);for(const [key,syn] of Object.entries(aliases)){if(syn.some(w=>out.has(w))) {out.add(key);syn.forEach(w=>out.add(w));}}return out;}
function parseMarkdown(md){const parts=[];let current=null;for(const line of md.split(/\r?\n/)){let match=line.match(/^#{2,3}\s+(.+)$/);if(match){if(current&&current.text.trim())parts.push(current);current={title:match[1].trim(),text:''};continue;}if(/^#\s+/.test(line)||/^>/.test(line))continue;if(!current)current={title:'Información general',text:''};if(line.trim())current.text+=line.replace(/^[-*]\s*/, '').trim()+'\n';}if(current&&current.text.trim())parts.push(current);return parts;}
async function loadKnowledge(){if(loaded)return;if(loadAttempted)return;loadAttempted=true;S.textContent='Cargando conocimiento…';try{const r=await fetch('/assets/data/conocimiento-rpv.md',{cache:'no-cache'});if(!r.ok)throw Error('HTTP '+r.status);const md=await r.text();sections=parseMarkdown(md);if(sections.length<2)throw Error('Archivo Markdown sin secciones');loaded=true;S.classList.remove('is-error');S.textContent='';}catch(e){loaded=false;S.classList.add('is-error');S.textContent='⚠ Para leer el .md, abre la página desde un servidor local o web';loadAttempted=false;console.warn('RPV knowledge load error',e);}}
function respond(question){if(!loaded)return 'No pude leer el archivo de conocimiento. Para activar las respuestas desde el .md, abre esta página desde un servidor web (consulta el archivo README). Si lo prefieres, puedes continuar por WhatsApp.';
 const terms=expand(tok(question));let hits=[];for(const sec of sections){const head=expand(tok(sec.title));const text=expand(tok(sec.text));let score=0;for(const term of terms){if(head.has(term))score+=7;else if(text.has(term))score+=1;}if(score>0)hits.push({score,sec});}
 hits.sort((a,b)=>b.score-a.score);if(!hits.length||hits[0].score<2)return 'No encuentro una respuesta específica a esa consulta en nuestra información actual. Para evitar darte datos incorrectos, te recomiendo conversar con nuestro equipo por WhatsApp.';
 let best=hits[0].sec;let chunks=best.text.replace(/\n+/g,' ').split(/(?<=[.!?])\s+/).map(x=>x.trim()).filter(Boolean);let selected=[];for(let line of chunks){if(line.length>250){selected.push(line.slice(0,245)+'…');continue;}selected.push(line);if(selected.join(' ').length>370)break;}let reply=selected.join(' ');if(reply.length>560)reply=reply.slice(0,557)+'…';return reply;}

// Respuestas conversacionales: se resuelven antes de consultar el catálogo.
let previousIntent='', previousReply='', repeatCount=0;
const CATALOG_REPLY='¡Claro! En RPV Construcciones ofrecemos tres líneas de productos:\n\n🔥 Calderas: equipos para temperar agua de tinajas y piscinas pequeñas, según compatibilidad.\n\n🛁 Tinajas: modelos para 4 y 7 personas, para uso familiar o proyectos turísticos.\n\n🏠 Estufas a pellets: calefacción para espacios de aproximadamente 70 m², según las condiciones del recinto.\n\n¿Qué producto te interesa conocer: calderas, tinajas o estufas a pellets?';
let awaitingCatalogChoice=false, awaitingTinajaPeople=false;

function tinajaPeopleReply(message){
 const t=norm(message).trim();
 const m=t.match(/^(?:(?:para|somos|seremos|necesito|quiero|de|unas?|aprox(?:imadamente)?)\s+)?(\d{1,2}|cuatro|seis|siete|cinco|tres|ocho)\s*(?:personas?|puestos?)?[.!]?$/);
 if(!m)return null;
 const names={tres:3,cuatro:4,cinco:5,seis:6,siete:7,ocho:8};
 const n=names[m[1]]||Number(m[1]);
 if(n===4)return 'Para 4 personas tenemos una tinaja de referencia a $1.450.000 CLP. ¿Quieres conocer sus medidas y equipamiento?';
 if(n===7)return 'Para 7 personas tenemos una tinaja tipo jacuzzi de referencia a $2.850.000 CLP. ¿Quieres conocer sus características?';
 if(n===5||n===6)return `Entiendo que buscas una tinaja para ${n} personas. Tenemos modelos para 4 y 7 personas; la de 7 podría ajustarse a tu necesidad. ¿Quieres conocer sus características y precio?`;
 if(n>=1&&n<=3)return `Para ${n} personas, nuestro modelo más pequeño informado es el de 4 personas. ¿Te gustaría conocer sus características?`;
 if(n>=8)return `Para ${n} personas, nuestros modelos informados son de hasta 7 personas. Podemos consultar alternativas para tu proyecto. ¿Quieres contactar al equipo?`;
 return null;
}

function catalogIntent(message){
 const t=norm(message).replace(/[¡!¿?.,;:]+/g,' ').replace(/\s+/g,' ').trim();
 if(/\b(precio|precios|cuanto|valor|costo|despacho|garantia|instalacion|medidas|litros|potencia)\b/.test(t))return false;
 return /^(?:(?:hola|buenas|buenos dias|buenas tardes)\s+)?(?:quiero|quisiera|necesito|me gustaria|podrian|pueden|deseo|busco|me interesa)\s+(?:saber|conocer|informacion|informarme|ver|mirar|que me cuenten|que me muestren)(?:\s+(?:mas|algo|un poco|todo))?\s+(?:de|sobre|acerca de|los|sus|el|la|del|de los|de sus)?\s*(?:productos|catalogo|lo que venden|lo que ofrecen|sus productos|la tienda|rpv)(?:\s+(?:que tienen|disponibles))?$/.test(t)
 || /^(?:que|cuales)\s+(?:productos|cosas|articulos)\s+(?:tienen|venden|ofrecen|hay|manejan)$/.test(t)
 || /^(?:productos|catalogo|ver productos|mostrar productos|muestrame los productos|informacion de productos|informacion sobre productos|que venden|que ofrecen|que tienen|cuentame de los productos)$/.test(t);
}
function catalogChoice(message){
 const t=norm(message).replace(/[¡!¿?.,;:]+/g,' ').replace(/\s+/g,' ').trim();
 if(/^(?:las? |sobre las? |quiero (?:ver|saber de|conocer) las? |me interesan las? )?(?:calderas?|calentadores?)(?: a pellets?)?$/.test(t))return 'caldera';
 if(/^(?:las? |sobre las? |quiero (?:ver|saber de|conocer) las? |me interesan las? )?(?:tinajas?|tinas?|jacuzzi)(?:s)?$/.test(t))return 'tinaja';
 if(/^(?:las? |sobre las? |quiero (?:ver|saber de|conocer) las? |me interesan las? )?(?:estufas?)(?: a pellets?)?$/.test(t))return 'estufa';
 return null;
}
function catalogChoiceReply(choice){
 if(choice==='caldera')return 'Contamos con tres equipos de calderas para temperar agua. Para recomendarte uno, ¿cuántos litros tiene tu tinaja o piscina pequeña?';
 if(choice==='tinaja')return 'Tenemos tinajas para 4 personas ($1.450.000 CLP de referencia) y 7 personas ($2.850.000 CLP de referencia). Los valores y equipamientos deben confirmarse. ¿Cuál te interesa?';
 return 'Ofrecemos una estufa a pellets con cobertura referencial de aproximadamente 70 m². ¿Cuántos metros cuadrados necesitas calefaccionar?';
}
function conversationalResponse(message){
  const t=norm(message).replace(/[¡!¿?.,;:]+/g,' ').replace(/\s+/g,' ').trim();
  const simple=t.replace(/^(?:oye|estimados|estimado|equipo rpv)\s+/,'');
  if(/^(?:hola+|holi+|holaa+|buenas+|buenos dias|buen dia|buenas tardes|buenas noches|que tal|saludos|hey|hello|ola+)(?:\s+(?:como estan|como estas|que tal|a todos))?$/.test(simple))
    return '¡Hola! Bienvenido a RPV Construcciones. 😊 ¿Te interesan nuestras tinajas, calderas o estufas a pellets?';
  if(/^(?:gracias+|muchas gracias|mil gracias|te agradezco|vale gracias|perfecto gracias)$/.test(simple))
    return '¡De nada! ¿Hay algo más en lo que te pueda orientar?';
  if(/^(?:adios|chao+|chau+|hasta luego|nos vemos|que estes bien|hasta pronto)$/.test(simple))
    return '¡Hasta pronto! Gracias por contactar a RPV Construcciones.';
  if(/^(?:ok|okay|vale|perfecto|entendido|ya|de acuerdo|listo)$/.test(simple))
    return 'Perfecto. ¿Te gustaría consultar otro producto o necesitas una cotización?';
  return null;
}
function repeatedResponse(question, reply){
  const intent=norm(question).replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
  if(intent && intent===previousIntent && reply===previousReply){
    repeatCount++;
    if(repeatCount===1)return 'Claro, te lo confirmo: '+reply;
    return 'Es la misma información que te indiqué anteriormente. Si necesitas un dato distinto, dime qué detalle quieres aclarar; también puedes confirmarlo con nuestro equipo.';
  }
  previousIntent=intent;previousReply=reply;repeatCount=0;
  return reply;
}

const TEAM_ACTION=$('teamAction'),LOCATION_SAVE_ACTION=$('locationSaveAction');
const whatsappFor=question=>'https://wa.me/56964170206?text='+encodeURIComponent('Hola, estaba conversando con el Asistente RPV en su sitio web. Mi consulta es: '+question);
const GREETING='¡Hola! Soy el Asistente RPV. Puedo orientarte utilizando nuestra información de productos. ¿Qué deseas consultar?';

let customerLocation=null,locationRequestToken=0;
// Identificador efimero por conversacion; no incluye datos personales.
let locationConversationId=(window.crypto?.randomUUID?.()||('rpv-'+Date.now()+'-'+Math.random().toString(36).slice(2)));
const sentLocationCaptures=new Set();
async function persistLocation(data,captureId){
  if(!data||sentLocationCaptures.has(captureId))return false;
  const gps=Number.isFinite(data.latitud)&&Number.isFinite(data.longitud);
  if(!gps&&!(data.fuente_ubicacion==='manual'&&data.comuna&&data.region))return false;
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
  const note=document.createElement('div');note.className='location-note';note.textContent='Compartir tu ubicación es opcional. Si eliges «Compartir mi ubicación» y permites el acceso del navegador, RPV guardará automáticamente la comuna, provincia, región, coordenadas y precisión para gestionar tu consulta. Las coordenadas también se consultan en servicios externos para identificar tu zona. Si escribes tu comuna y región y pulsas «Usar esta ubicación», RPV también las guardará, sin coordenadas GPS. WhatsApp es independiente.';
  const actions=document.createElement('div');actions.className='location-actions';
  const locate=document.createElement('button');locate.type='button';locate.textContent='📍 Compartir mi ubicación';
  const manual=document.createElement('div');manual.className='location-manual';
  const input=document.createElement('input');input.type='text';input.placeholder='Ej: San Miguel, Región Metropolitana';input.setAttribute('aria-label','Ingresar comuna y región, separadas por una coma');input.maxLength=120;
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
    if(!data)return;
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
  function showManualLocationError(message){
    note.textContent=message;
    note.classList.add('is-error');
    note.setAttribute('role','alert');
    input.setAttribute('aria-invalid','true');
    anchorResponse(note);
    input.focus({preventScroll:true});
  }
  save.addEventListener('click',()=>{
    const value=input.value.trim();
    if(!value){showManualLocationError('Falta la ubicación. Escribe tu comuna y región; por ejemplo: San Miguel, Región Metropolitana.');return;}
    const raw=value.split(',').map(x=>x.trim());
    if(raw.length!==2||!raw[0]||!raw[1]){
      showManualLocationError('Falta la región. Escribe la comuna y luego la región separadas por una coma; por ejemplo: San Miguel, Región Metropolitana.');
      return;
    }
    note.classList.remove('is-error');note.removeAttribute('role');input.removeAttribute('aria-invalid');
    const prior=customerLocation?.data||pendingCoordinates;
    const gps=prior&&Number.isFinite(prior.latitud)&&Number.isFinite(prior.longitud);
    const data=gps?{...prior,comuna:raw[0],region:raw[1]}:{comuna:raw[0],region:raw[1],provincia:null,pais:'Chile',latitud:null,longitud:null,precision_metros:null,url_google_maps:null,fuente_ubicacion:'manual'};
    const coords=gps?' Coordenadas: '+prior.latitud.toFixed(5)+', '+prior.longitud.toFixed(5)+'. Mapa: '+prior.url_google_maps+'.':'';
    confirmLocation('Comuna y región indicadas por el cliente: '+value+'.'+coords,data);
  });
  input.addEventListener('input',()=>{if(input.getAttribute('aria-invalid')==='true'){input.removeAttribute('aria-invalid');note.classList.remove('is-error');note.removeAttribute('role');note.textContent='Formato: comuna, región. Ejemplo: San Miguel, Región Metropolitana.';}});
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
  conversationContext.reset();
  stopTyping(false);
  M.replaceChildren();
  TEAM_ACTION.replaceChildren();TEAM_ACTION.classList.remove('has-action');
  LOCATION_SAVE_ACTION.replaceChildren();LOCATION_SAVE_ACTION.classList.remove('has-action');
  hidePinnedQuestion();
  add(GREETING,false);
  M.scrollTop=0;
  Q.value='';last='';previousIntent='';previousReply='';repeatCount=0;awaitingCatalogChoice=false;awaitingTinajaPeople=false;customerLocation=null;locationConversationId=(window.crypto?.randomUUID?.()||('rpv-'+Date.now()+'-'+Math.random().toString(36).slice(2)));++locationRequestToken;
  WA.href='https://wa.me/56964170206?text='+encodeURIComponent('Hola, estaba conversando con el Asistente RPV en su sitio web y quisiera continuar por WhatsApp.');
  Q.focus({preventScroll:true});
}
$('clearChat').addEventListener('click',clearConversation);
async function send(t){
  t=t.trim();if(!t)return;
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
  {
    await loadKnowledge();
    knowledgeBase=knowledgeBase||window.RPVContexto.createKnowledgeBase(sections);
    const contextResult=conversationContext.process(t,{knowledge:knowledgeBase});
    if(contextResult.ignorado)return;
    const contextReply=contextResult.respuesta;
    updateWhatsappQuestion(t);
    Q.value='';
    const askLocation=needsLocation(t,contextReply)&&!customerLocation;
    const askTeam=needsTeamConfirmation(contextReply);
    if(askTeam)offerTeamConfirmation(t,contextReply);
    typeAssistant(contextReply,()=>{if(askLocation)offerLocation(t);});
    return;
  }
  const people=awaitingTinajaPeople?tinajaPeopleReply(t):null;
  if(people){awaitingTinajaPeople=false;updateWhatsappQuestion(t);Q.value='';typeAssistant(people);return;}
  const isCatalog=catalogIntent(t);
  const selectedCatalog=awaitingCatalogChoice?catalogChoice(t):null;
  const conversational=isCatalog?CATALOG_REPLY:selectedCatalog?catalogChoiceReply(selectedCatalog):conversationalResponse(t);
  awaitingCatalogChoice=isCatalog;
  awaitingTinajaPeople=!!(selectedCatalog==='tinaja'||/\b(?:para cuantos personas|para cuantas personas|4 o 7|cuatro o siete)\b/.test(norm(conversational||'')));
  if(!conversational)await loadKnowledge();
  const reply=conversational||repeatedResponse(t,respond(t));
  if(!conversational)awaitingTinajaPeople=/\b(?:para cuantas personas|4 o 7|cuatro o siete)\b/.test(norm(reply));
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
let sendQueue=Promise.resolve();
function enqueueSend(text){sendQueue=sendQueue.then(()=>send(text)).catch(error=>console.error('RPV chat send error',error));}
$('form').addEventListener('submit',e=>{e.preventDefault();enqueueSend(Q.value)});document.querySelectorAll('[data-q]').forEach(b=>b.addEventListener('click',()=>enqueueSend(b.dataset.q)));
function updateSticky(){if(!mobile.matches||C.classList.contains('open')||L.classList.contains('dragged')){if(!mobile.matches)L.classList.remove('sticky');return;}L.classList.toggle('sticky',anchor.getBoundingClientRect().top<9);}
window.addEventListener('scroll',updateSticky,{passive:true});window.addEventListener('resize',updateSticky);window.addEventListener('pageshow',updateSticky);
function openChat(){if(preventLaunchClick)return;L.style.display='none';H.style.display='none';C.classList.add('open');if(mobile.matches){savedScroll=scrollY;document.documentElement.classList.add('locked');document.body.classList.add('locked');document.body.style.top='-'+savedScroll+'px';B.classList.add('show');C.setAttribute('aria-modal','true');}loadKnowledge();requestAnimationFrame(()=>{if(C.classList.contains('open'))Q.focus({preventScroll:true});});}
function closeChat(){C.classList.remove('open');B.classList.remove('show');document.documentElement.classList.remove('locked');document.body.classList.remove('locked');document.body.style.top='';C.setAttribute('aria-modal','false');if(mobile.matches)window.scrollTo(0,savedScroll);L.style.display='';H.style.display='';C.classList.remove('dragged');L.classList.remove('dragged');for(let el of [L,C]){el.style.left='';el.style.right='';el.style.top='';el.style.bottom='';}requestAnimationFrame(updateSticky);}
L.addEventListener('click',openChat);$('close').addEventListener('click',closeChat);B.addEventListener('click',closeChat);B.addEventListener('touchmove',e=>e.preventDefault(),{passive:false});window.addEventListener('keydown',e=>{if(e.key==='Escape'&&C.classList.contains('open'))closeChat();});
function drag(el,handle){let st=null,move=false;handle.addEventListener('pointerdown',e=>{if(e.target.closest('button')&&el!==L)return;const rect=el.getBoundingClientRect();let absolute=getComputedStyle(el).position==='absolute';const bound=absolute?el.offsetParent.getBoundingClientRect():{left:0,top:0,width:innerWidth,height:innerHeight};st={x:e.clientX,y:e.clientY,left:rect.left-bound.left,top:rect.top-bound.top,bound};move=false;handle.setPointerCapture(e.pointerId);});handle.addEventListener('pointermove',e=>{if(!st)return;const dx=e.clientX-st.x,dy=e.clientY-st.y;if(Math.abs(dx)+Math.abs(dy)>6)move=true;if(!move)return;el.classList.add('dragged');el.style.left=Math.max(0,Math.min(st.bound.width-el.offsetWidth,st.left+dx))+'px';el.style.top=Math.max(0,Math.min(st.bound.height-el.offsetHeight,st.top+dy))+'px';el.style.right='auto';el.style.bottom='auto';});handle.addEventListener('pointerup',()=>{if(move&&el===L){preventLaunchClick=true;setTimeout(()=>preventLaunchClick=false,180);}st=null;});handle.addEventListener('pointercancel',()=>{st=null;});}drag(L,L);drag(C,$('drag'));requestAnimationFrame(updateSticky);
})();


/* RPV: carga diferida de animaciones y tarjetas, sin bloquear el asistente. */
(()=>{
  'use strict';
  const version='20261125-optimizado';
  const load=(key,file)=>{
    if(window[key])return;
    window[key]=true;
    const script=document.createElement('script');
    script.src='/assets/js/'+file+'?v='+version;
    script.async=true;
    script.onerror=()=>{window[key]=false;console.warn('RPV: no se pudo cargar '+file);};
    document.head.appendChild(script);
  };
  const hero=document.querySelector('.hero .hero-effects');
  if(hero){
    if('IntersectionObserver' in window){
      const observer=new IntersectionObserver(entries=>{
        if(entries.some(entry=>entry.isIntersecting)){
          observer.disconnect();
          load('__rpvHeroEffectsLoaded','hero-effects-rpv.js');
        }
      },{rootMargin:'150px 0px'});
      observer.observe(hero);
    }else{
      load('__rpvHeroEffectsLoaded','hero-effects-rpv.js');
    }
  }
  if(document.getElementById('productDetailPanel')){
    const start=()=>load('__rpvProductCardsLoaded','product-cards-rpv.js');
    if('requestIdleCallback' in window)requestIdleCallback(start,{timeout:1800});
    else setTimeout(start,200);
  }
})();
