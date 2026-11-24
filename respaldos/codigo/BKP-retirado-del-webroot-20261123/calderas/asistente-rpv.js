// Asistente RPV | Version 2026-11-10-7 | ubicacion discreta y guardado automatico
(()=>{'use strict';
const $=id=>document.getElementById(id), L=$('launcher'),C=$('chat'),B=$('backdrop'),M=$('messages'),Q=$('question'),WA=$('chatWa'),S=$('status'),H=$('hint'),anchor=$('anchor'),mobile=matchMedia('(max-width:767px)');
let sections=[],loaded=false,loadAttempted=false,last='',savedScroll=0,preventLaunchClick=false;
const stop=new Set('el la los las un una unos unas de del y o a al por para con en es son se que como cuál cuales qué cual quiero saber me mi sus sobre ustedes tienen hay está estan más puedo podrian favor información informacion necesito hola gracias buen buenas producto productos'.split(' '));
const norm=t=>t.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const tok=t=>[...new Set((norm(t).match(/[a-z0-9]+/g)||[]).map(w=>({tinajas:'tinaja',calderas:'caldera',estufas:'estufa',pellets:'pellet',precios:'precio',modelos:'modelo',envios:'envio',despachos:'despacho',garantias:'garantia',instalaciones:'instalacion',cotizaciones:'cotizacion'}[w]||w)))].filter(w=>w.length>2&&!stop.has(w));
const aliases={tinaja:['tinaja','tinas','spa','jacuzzi','banera','bano','agua'],caldera:['caldera','calentador','calefactor','quemador'],pellet:['pellet','estufa','calefaccion','calor'],precio:['precio','valor','costo','cotizacion','cotizar','presupuesto'],envio:['envio','despacho','transporte','region','entrega'],instalacion:['instalacion','montaje','instalar'],garantia:['garantia','reparacion','posventa'],contacto:['contacto','telefono','whatsapp','correo']};
function expand(words){let out=new Set(words);for(const [key,syn] of Object.entries(aliases)){if(syn.some(w=>out.has(w))) {out.add(key);syn.forEach(w=>out.add(w));}}return out;}
function parseMarkdown(md){const parts=[];let current=null;for(const line of md.split(/\r?\n/)){let match=line.match(/^#{2,3}\s+(.+)$/);if(match){if(current&&current.text.trim())parts.push(current);current={title:match[1].trim(),text:''};continue;}if(/^#\s+/.test(line)||/^>/.test(line))continue;if(!current)current={title:'Información general',text:''};if(line.trim())current.text+=line.replace(/^[-*]\s*/, '').trim()+'\n';}if(current&&current.text.trim())parts.push(current);return parts;}
async function loadKnowledge(){if(loadAttempted)return;loadAttempted=true;S.textContent='Cargando conocimiento…';try{const r=await fetch('./conocimiento-rpv.md',{cache:'no-cache'});if(!r.ok)throw Error('HTTP '+r.status);const md=await r.text();sections=parseMarkdown(md);if(sections.length<2)throw Error('Archivo Markdown sin secciones');loaded=true;S.classList.remove('is-error');S.textContent='';}catch(e){loaded=false;S.classList.add('is-error');S.textContent='⚠ Para leer el .md, abre la página desde un servidor local o web';console.warn('RPV knowledge load error',e);}}
function respond(question){if(!loaded)return 'No pude leer el archivo de conocimiento. Para activar las respuestas desde el .md, abre esta página desde un servidor web (consulta el archivo README). Si lo prefieres, puedes continuar por WhatsApp.';
 const terms=expand(tok(question));let hits=[];for(const sec of sections){const head=expand(tok(sec.title));const text=expand(tok(sec.text));let score=0;for(const term of terms){if(head.has(term))score+=7;else if(text.has(term))score+=1;}if(score>0)hits.push({score,sec});}
 hits.sort((a,b)=>b.score-a.score);if(!hits.length||hits[0].score<2)return 'No encuentro una respuesta específica a esa consulta en nuestra información actual. Para evitar darte datos incorrectos, te recomiendo conversar con nuestro equipo por WhatsApp.';
 let best=hits[0].sec;let chunks=best.text.replace(/\n+/g,' ').split(/(?<=[.!?])\s+/).map(x=>x.trim()).filter(Boolean);let selected=[];for(let line of chunks){if(line.length>250){selected.push(line.slice(0,245)+'…');continue;}selected.push(line);if(selected.join(' ').length>370)break;}let reply=selected.join(' ');if(reply.length>560)reply=reply.slice(0,557)+'…';return reply;}
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
    const response=await fetch('./guardar-ubicacion.php',{
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
  Q.value='';last='';customerLocation=null;locationConversationId=(window.crypto?.randomUUID?.()||('rpv-'+Date.now()+'-'+Math.random().toString(36).slice(2)));++locationRequestToken;
  WA.href='https://wa.me/56964170206?text='+encodeURIComponent('Hola, estaba conversando con el Asistente RPV en su sitio web y quisiera continuar por WhatsApp.');
  Q.focus({preventScroll:true});
}
$('clearChat').addEventListener('click',clearConversation);
function send(t){
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
  const reply=respond(t);
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

/* Movimiento localizado: SOLO hojas verdes; troncos y construccion inmoviles. */
(()=>{
  const hero=document.querySelector('.hero');
  const canvas=hero?.querySelector('.hero-effects');
  if(!canvas)return;
  const ctx=canvas.getContext('2d',{alpha:true});
  if(!ctx)return;
  const motion=window.matchMedia('(prefers-reduced-motion: reduce)');
  const image=new Image();
  image.src='assets/fondo.webp';
  let width=0,height=0,dpr=1,frame=0,running=false,visible=true,last=0;
  let leaves=[],mobileLeaves=[];
  // Areas marcadas sobre la imagen original 1600x893. Se excluye el tronco
  // vertical derecho (aprox. x 1320-1410) y las zonas construidas.
  const patches=[
    {x:0,y:0,w:185,h:570,amp:6.5,speed:.91,phase:.1},
    {x:205,y:0,w:335,h:178,amp:9,speed:1.08,phase:1.5},
    {x:530,y:0,w:460,h:192,amp:10,speed:.86,phase:3},
    {x:1175,y:8,w:135,h:355,amp:6,speed:1.03,phase:2.1},
    {x:1418,y:0,w:182,h:700,amp:7.5,speed:.97,phase:3.7},
    {x:1220,y:510,w:378,h:300,amp:6.8,speed:.82,phase:4.2}
  ];
  // En pantallas estrechas las hojas colgantes oscuras sobre el cielo
  // requieren mascaras distintas: el verde puro no las detecta.
  // Son recortes solo de la copa superior, sin incluir ningun tronco.
  const mobilePatches=[
    {x:565,y:0,w:105,h:212,amp:58,speed:1.82,phase:.2,mobile:true},
    {x:652,y:0,w:112,h:240,amp:64,speed:1.66,phase:1.4,mobile:true},
    {x:745,y:0,w:110,h:238,amp:68,speed:1.92,phase:2.5,mobile:true},
    {x:837,y:0,w:108,h:228,amp:65,speed:1.75,phase:3.6,mobile:true},
    {x:920,y:0,w:110,h:212,amp:56,speed:2.03,phase:4.7,mobile:true},
    {x:1008,y:0,w:85,h:184,amp:48,speed:1.83,phase:5.5,mobile:true}
  ];  const mobileScreen=window.matchMedia('(max-width:767px)');
  // Mascaras de vegetacion: conserva solo tonos verdes/verde-amarillentos;
  // troncos marrones, metal, vidrio y construcciones quedan transparentes.
  function makePatch(p){
    const c=document.createElement('canvas');c.width=p.w;c.height=p.h;
    const g=c.getContext('2d',{willReadFrequently:true});
    g.drawImage(image,p.x,p.y,p.w,p.h,0,0,p.w,p.h);
    const im=g.getImageData(0,0,p.w,p.h),d=im.data;
    for(let i=0;i<d.length;i+=4){
      const r=d[i],green=d[i+1],blue=d[i+2];
      const high=Math.max(r,green,blue),low=Math.min(r,green,blue);
      const chroma=high-low;
      // Detectar el verde, incluso hojas verde-oliva con luz amarilla.
      const greenBias=Math.min(green-r*.87,green-blue*.94);
      let confidence=Math.max(0,Math.min(1,(greenBias-3)/17));
      confidence*=Math.max(0,Math.min(1,(chroma-12)/27));
      // En la copa colgante: tambien seleccionar hojas casi negras
      // recortadas contra el cielo azul, manteniendo transparente el cielo.
      if(p.mobile){
        const darkLeaf=Math.max(0,Math.min(1,(148-high)/66));
        const blueSky=Math.max(0,Math.min(1,(130-blue)/65));
        const leafTone=Math.max(0,Math.min(1,(green-blue+22)/36));
        confidence=Math.max(confidence, darkLeaf*blueSky*leafTone);
      }else if(green<36||greenBias<3)confidence=0;
      const px=(i/4)%p.w,py=Math.floor(i/4/p.w);
      const edge=Math.min(px,py,p.w-1-px,p.h-1-py);
      confidence*=Math.min(1,Math.max(0,edge/16));
      // Opacidad suficientemente baja para evitar doble imagen artificial.
      d[i+3]=Math.round(225*confidence);
    }
    g.putImageData(im,0,0);
    return {...p,sprite:c};
  }
  function resize(){
    const r=hero.getBoundingClientRect();
    width=Math.max(1,r.width);height=Math.max(1,r.height);
    dpr=Math.min(devicePixelRatio||1,2);
    canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr);
    canvas.style.width=width+'px';canvas.style.height=height+'px';
  }
  // Las posiciones de origen siguen el borde superior de la tinaja.
  const emitters=[
    {x:702,y:552,phase:.2},{x:767,y:549,phase:1.2},
    {x:828,y:546,phase:2.2},{x:890,y:548,phase:3.2},
    {x:948,y:550,phase:4.4},{x:1017,y:550,phase:5.5}
  ];
  function draw(ms){
    if(!running)return;
    frame=requestAnimationFrame(draw);
    if(ms-last<33)return;
    last=ms;
    ctx.setTransform(dpr,0,0,dpr,0,0);
    ctx.clearRect(0,0,width,height);
    const scale=Math.max(width/image.naturalWidth,height/image.naturalHeight);
    const ox=(width-image.naturalWidth*scale)*.5;
    const oy=(height-image.naturalHeight*scale)*.51;
    const t=ms/1000;
    // En escritorio se conserva el balanceo suave aprobado.
    // En movil las hojas colgantes de arriba se mecen de manera evidente.
    const isMobile=mobileScreen.matches;
    for(const p of (isMobile?leaves.filter(q=>q.x<205||q.x>1100):leaves)){
      const sway=Math.sin(t*p.speed+p.phase)*p.amp+Math.sin(t*p.speed*.45+p.phase)*p.amp*.25;
      const lift=Math.cos(t*p.speed*.75+p.phase)*1.3;
      ctx.save();ctx.globalAlpha=.84;
      ctx.drawImage(p.sprite,ox+(p.x+sway)*scale,oy+(p.y+lift)*scale,p.w*scale,p.h*scale);
      ctx.restore();
    }
    if(isMobile){
      for(const p of mobileLeaves){
        // Balanceo desde el extremo superior, como ramas con viento.
        const angle=Math.sin(t*p.speed+p.phase)*.22
                   +Math.sin(t*p.speed*.51+p.phase*.8)*.065;
        const drift=(Math.sin(t*p.speed+p.phase)*.84
                   +Math.sin(t*p.speed*.57+p.phase*.6)*.16)*p.amp;
        const x=ox+p.x*scale,y=oy+p.y*scale;
        ctx.save();
        ctx.globalAlpha=.97;
        ctx.translate(x+p.w*scale*.5,y);
        ctx.rotate(angle);
        ctx.drawImage(p.sprite,-p.w*scale*.5+drift*scale*.50,0,p.w*scale,p.h*scale);
        ctx.restore();
      }
    }
    // Vapor con corrientes onduladas y nubosidad mas visible sobre el agua.
    ctx.save();ctx.globalCompositeOperation='screen';
    for(const e of emitters){
      for(let n=0;n<9;n++){
        const progress=(t*.15+n/9+e.phase*.12)%1;
        const fade=Math.pow(Math.sin(Math.PI*progress),.8);
        const sway=Math.sin(t*.94+n*.82+e.phase)*35+Math.sin(progress*7+e.phase)*22;
        const x=ox+(e.x+sway+progress*31)*scale;
        const y=oy+(e.y-progress*355)*scale;
        const radius=(20+progress*53)*scale;
        if(x+radius<0||x-radius>width||y+radius<0||y-radius>height)continue;
        const alpha=.31*fade;
        const mist=ctx.createRadialGradient(x,y,0,x,y,radius);
        mist.addColorStop(0,`rgba(251,253,252,${alpha})`);
        mist.addColorStop(.35,`rgba(245,250,249,${alpha*.65})`);
        mist.addColorStop(.8,`rgba(234,244,242,${alpha*.12})`);
        mist.addColorStop(1,'rgba(234,244,242,0)');
        ctx.fillStyle=mist;
        ctx.beginPath();ctx.ellipse(x,y,radius*.72,radius*1.48,Math.sin(t*.42+e.phase)*.14,0,Math.PI*2);ctx.fill();
      }
      // Hilo de vapor: trayectoria serpenteante que sube desde la tinaja.
      const baseX=ox+e.x*scale,baseY=oy+e.y*scale;
      ctx.beginPath();
      for(let j=0;j<=16;j++){
        const f=j/16;
        const xx=baseX+(Math.sin(t*.7+e.phase+f*5.6)*27+f*36)*scale;
        const yy=baseY-f*225*scale;
        if(j===0)ctx.moveTo(xx,yy);else ctx.lineTo(xx,yy);
      }
      ctx.strokeStyle='rgba(242,250,248,.09)';ctx.lineWidth=19*scale;ctx.lineCap='round';
      ctx.filter=`blur(${Math.max(6,12*scale)}px)`;ctx.stroke();ctx.filter='none';
    }
    ctx.restore();
  }
  function refresh(){
    const active=!motion.matches&&visible&&image.complete&&image.naturalWidth>0;
    if(active&&!running){running=true;last=0;frame=requestAnimationFrame(draw)}
    if(!active&&running){running=false;cancelAnimationFrame(frame);ctx.clearRect(0,0,canvas.width,canvas.height)}
  }
  function ready(){leaves=patches.map(makePatch);mobileLeaves=mobilePatches.map(makePatch);resize();refresh()}
  image.addEventListener('load',ready);
  if(image.complete&&image.naturalWidth)ready();
  window.addEventListener('resize',resize,{passive:true});
  document.addEventListener('visibilitychange',()=>{visible=!document.hidden;refresh()});
  motion.addEventListener('change',refresh);
  if('IntersectionObserver'in window){
    new IntersectionObserver(entries=>{visible=entries[0].isIntersecting&&!document.hidden;refresh()},{threshold:0}).observe(hero);
  }
})();

/* WEB: muestra solo la tarjeta individual del producto señalado. */
(()=>{
  const panel=document.getElementById('productDetailPanel');
  const cards=[...document.querySelectorAll('#products > .cards > .card')];
  if(!panel||cards.length!==3)return;
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
