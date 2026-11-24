/* Recorrido ilustrativo: no representa el avance real de una cotización. */
(()=>{
  'use strict';
  const section=document.querySelector('.project-guide');
  if(!section)return;
  const steps=[...section.querySelectorAll('.guide-step')];
  const goal=section.querySelector('.guide-goal');
  const replay=section.querySelector('.guide-replay');
  if(steps.length!==3||!goal||!replay)return;
  const reduced=matchMedia('(prefers-reduced-motion:reduce)');
  const label=replay.querySelector('span');
  let timers=[],visible=false,paused=false;
  function clearTimers(){timers.forEach(clearTimeout);timers=[];}
  function show(index){
    section.style.setProperty('--journey-progress',String(index/2));
    steps.forEach((step,i)=>{
      step.classList.toggle('is-reached',i<=index);
      step.classList.toggle('is-current',i===index);
    });
    goal.classList.toggle('is-reached',index===2);
  }
  function play(){
    clearTimers();
    show(0);
    timers.push(setTimeout(()=>show(1),3000));
    timers.push(setTimeout(()=>show(2),6000));
    timers.push(setTimeout(update,11000));
  }
  function update(){
    clearTimers();
    replay.hidden=reduced.matches;
    if(label)label.textContent=paused?'Reanudar recorrido':'Pausar recorrido';
    if(reduced.matches){show(2);return;}
    if(visible&&!document.hidden&&!paused)play();
  }
  replay.addEventListener('click',()=>{paused=!paused;update();});
  reduced.addEventListener('change',update);
  document.addEventListener('visibilitychange',update);
  if('IntersectionObserver' in window){
    const observer=new IntersectionObserver(entries=>{
      visible=entries.some(entry=>entry.isIntersecting);
      update();
    },{threshold:0.15});
    observer.observe(section);
  }else{visible=true;}
  update();
})();
