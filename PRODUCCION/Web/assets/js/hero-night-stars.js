(()=>{
  const hero=document.querySelector('.hero');
  const canvas=hero?.querySelector('.hero-stars');
  const image=new Image();
  if(!hero||!canvas)return;
  const ctx=canvas.getContext('2d',{alpha:true});
  if(!ctx)return;

  const reducedMotion=matchMedia('(prefers-reduced-motion: reduce)');
  let width=0,height=0,dpr=1,frame=0,stars=[],running=false;
  const seedRandom=()=>{let seed=481516;return()=>{seed=(seed*16807)%2147483647;return(seed-1)/2147483646}};
  const random=seedRandom();

  function resize(){
    const rect=hero.getBoundingClientRect();
    width=Math.max(1,rect.width);height=Math.max(1,rect.height);
    dpr=Math.min(window.devicePixelRatio||1,2);
    canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr);
    canvas.style.width=width+'px';canvas.style.height=height+'px';
    render(performance.now());
  }

  function makeStars(){
    const source=document.createElement('canvas');
    source.width=image.naturalWidth;source.height=image.naturalHeight;
    const sourceCtx=source.getContext('2d',{willReadFrequently:true});
    sourceCtx.drawImage(image,0,0);
    const pixels=sourceCtx.getImageData(0,0,source.width,source.height).data;
    let attempts=0;
    while(stars.length<88&&attempts++<12000){
      const x=380+random()*960,y=random()*365;
      const i=(Math.floor(y)*source.width+Math.floor(x))*4;
      const r=pixels[i],g=pixels[i+1],b=pixels[i+2];
      // Poner puntos solo sobre el cielo azul para que no aparezcan en ramas ni edificios.
      if(b<95||b-r<22||b-g<5||g-r<2)continue;
      stars.push({x,y,radius:.55+random()*1.05,opacity:.48+random()*.48,phase:random()*Math.PI*2,speed:.7+random()*1.7});
    }
  }

  function render(time){
    if(!width||!height)return;
    ctx.setTransform(dpr,0,0,dpr,0,0);
    ctx.clearRect(0,0,width,height);
    if(hero.dataset.light!=='night'||!stars.length)return;
    const scale=Math.max(width/image.naturalWidth,height/image.naturalHeight);
    const ox=(width-image.naturalWidth*scale)*.5;
    const oy=(height-image.naturalHeight*scale)*.49;
    const t=time/1000;
    for(const star of stars){
      const x=ox+star.x*scale,y=oy+star.y*scale;
      if(x<0||x>width||y<0||y>height)continue;
      const twinkle=reducedMotion.matches?1:.68+.32*Math.sin(t*star.speed+star.phase);
      const alpha=star.opacity*twinkle;
      const radius=Math.max(.65,star.radius*scale);
      ctx.beginPath();ctx.arc(x,y,radius,0,Math.PI*2);
      ctx.fillStyle=`rgba(232,244,255,${alpha})`;ctx.fill();
      if(radius>1.15){
        ctx.beginPath();ctx.arc(x,y,radius*2.8,0,Math.PI*2);
        ctx.fillStyle=`rgba(190,220,255,${alpha*.12})`;ctx.fill();
      }
    }
  }

  function tick(time){
    if(!running)return;
    render(time);
    frame=requestAnimationFrame(tick);
  }
  function refresh(){
    const shouldAnimate=hero.dataset.light==='night'&&!reducedMotion.matches;
    if(shouldAnimate&&!running){running=true;frame=requestAnimationFrame(tick)}
    if(!shouldAnimate&&running){running=false;cancelAnimationFrame(frame);render(performance.now())}
    else if(!running)render(performance.now());
  }
  function ready(){makeStars();resize();refresh()}

  image.src='/assets/images/backgrounds/fondo.webp';
  if(image.complete&&image.naturalWidth)ready();
  else image.addEventListener('load',ready,{once:true});
  window.addEventListener('resize',resize,{passive:true});
  new MutationObserver(refresh).observe(hero,{attributes:true,attributeFilter:['data-light']});
  reducedMotion.addEventListener?.('change',refresh);
})();
