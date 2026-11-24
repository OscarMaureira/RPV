/* Movimiento localizado: SOLO hojas verdes; troncos y construccion inmoviles. */
(()=>{
  const hero=document.querySelector('.hero, .intro');
  const canvas=hero?.querySelector('.hero-effects');
  if(!canvas)return;
  const ctx=canvas.getContext('2d',{alpha:true});
  if(!ctx)return;
  const motion=window.matchMedia('(prefers-reduced-motion: reduce)');
  const image=new Image();
  image.src='/assets/images/backgrounds/fondo.webp';
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
