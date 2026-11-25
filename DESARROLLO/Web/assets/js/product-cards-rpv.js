// RPV: detalle de tarjetas de productos, carga diferida.
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
