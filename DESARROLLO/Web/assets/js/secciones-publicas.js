(()=>{
  'use strict';
  const routes={
    tinajas:'/tinajas/',
    calderas:'/calderas/',
    estufas_pellets:'/estufas-a-pellets/'
  };
  const style=document.createElement('link');
  style.rel='stylesheet';
  style.href='/assets/css/secciones-publicas.css?v=20261126-1';
  document.head.append(style);

  const pathOf=anchor=>{
    try{return new URL(anchor.href,document.baseURI).pathname.replace(/index\.html$/,'');}
    catch{return '';}
  };

  const disableAccess=(route,root=document)=>{
    const anchors=[];
    if(root instanceof HTMLAnchorElement&&root.hasAttribute('href'))anchors.push(root);
    if(root.querySelectorAll)anchors.push(...root.querySelectorAll('a[href]'));
    anchors.forEach(anchor=>{
      if(pathOf(anchor)!==route)return;
      anchor.removeAttribute('href');
      anchor.setAttribute('aria-disabled','true');
      anchor.setAttribute('tabindex','-1');
      anchor.setAttribute('title','Seccion temporalmente no disponible');
      anchor.classList.add('section-link-disabled');
    });
  };

  fetch('/api/secciones.php',{headers:{Accept:'application/json'},credentials:'same-origin'})
    .then(response=>{if(!response.ok)throw new Error('configuracion no disponible');return response.json();})
    .then(states=>{
      const inactiveRoutes=Object.entries(routes).filter(([key])=>states[key]===false).map(([,route])=>route);
      inactiveRoutes.forEach(route=>disableAccess(route));
      if(inactiveRoutes.length){
        new MutationObserver(mutations=>mutations.forEach(mutation=>mutation.addedNodes.forEach(node=>{
          if(!(node instanceof Element))return;
          inactiveRoutes.forEach(route=>disableAccess(route,node));
        }))).observe(document.documentElement,{childList:true,subtree:true});
      }
      document.documentElement.dataset.sectionsReady='true';
    })
    .catch(()=>{document.documentElement.dataset.sectionsReady='error';});
})();
