(()=>{
  const section=document.querySelector('#opiniones-clientes');
  const viewport=section?.querySelector('.customer-opinions__viewport');
  const track=section?.querySelector('[data-opinions-track]');
  const previous=section?.querySelector('[data-opinions-prev]');
  const next=section?.querySelector('[data-opinions-next]');
  const status=section?.querySelector('[data-opinions-status]');
  if(!section||!viewport||!track||!previous||!next)return;
  let opinions=[];
  const cardStep=()=>track.firstElementChild?track.firstElementChild.getBoundingClientRect().width+16:viewport.clientWidth;
  const current=()=>Math.min(opinions.length-1,Math.max(0,Math.round(viewport.scrollLeft/cardStep())));
  const update=()=>{
    const index=current();
    if(status)status.textContent=opinions.length?`${index+1} de ${opinions.length}`:'';
    previous.disabled=index<=0;
    next.disabled=index>=opinions.length-1;
  };
  const move=direction=>viewport.scrollBy({left:cardStep()*direction,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  previous.addEventListener('click',()=>move(-1));
  next.addEventListener('click',()=>move(1));
  viewport.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'){event.preventDefault();move(-1);}if(event.key==='ArrowRight'){event.preventDefault();move(1);}});
  let ticking=false;
  viewport.addEventListener('scroll',()=>{if(!ticking){requestAnimationFrame(()=>{update();ticking=false;});ticking=true;}},{passive:true});
  window.addEventListener('resize',update,{passive:true});
  fetch('/opiniones/listado.php',{cache:'no-store',headers:{Accept:'application/json'}}).then(response=>{if(!response.ok)throw new Error('opiniones');return response.json();}).then(result=>{
    opinions=Array.isArray(result.opiniones)?result.opiniones:[];
    if(!opinions.length)return;
    const fragment=document.createDocumentFragment();
    opinions.forEach(opinion=>{
      const article=document.createElement('article');article.className='customer-opinion';
      const stars=document.createElement('div');stars.className='customer-opinion__stars';stars.setAttribute('aria-label',`${opinion.calificacion} de 5 estrellas`);stars.textContent='★'.repeat(Math.max(1,Math.min(5,Number(opinion.calificacion)||1)));
      const quote=document.createElement('blockquote');quote.className='customer-opinion__quote';quote.textContent=opinion.comentario;
      const name=document.createElement('p');name.className='customer-opinion__name';name.textContent=opinion.nombre;
      article.append(stars,quote,name);
      if(typeof opinion.empresa==='string'&&opinion.empresa.trim()&&typeof opinion.url_empresa==='string'){
        let website=null;
        try{
          const candidate=new URL(opinion.url_empresa);
          if(candidate.protocol==='https:'||candidate.protocol==='http:')website=candidate;
        }catch(_){/* Una URL inválida impide mostrar toda la sección comercial. */}
        if(website){
        const business=document.createElement('div');business.className='customer-opinion__business';
        const label=document.createElement('span');label.textContent='Conoce su negocio';
        const company=document.createElement('strong');company.textContent=opinion.empresa.trim();
        business.append(label,company);
        const link=document.createElement('a');link.href=website.href;link.target='_blank';link.rel='noopener noreferrer nofollow ugc';link.textContent='Visitar sitio web';link.setAttribute('aria-label',`Visitar el sitio web de ${opinion.empresa.trim()}`);business.append(link);
        article.append(business);
        }
      }
      fragment.append(article);
    });
    track.replaceChildren(fragment);section.hidden=false;update();
  }).catch(()=>{section.hidden=true;});
})();
