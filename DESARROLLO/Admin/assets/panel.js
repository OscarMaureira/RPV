document.addEventListener('DOMContentLoaded',()=>{
  const menuButton=document.querySelector('.menu-toggle');
  const sidebar=document.querySelector('#sidebar');
  if(menuButton&&sidebar)menuButton.addEventListener('click',()=>{
    const open=sidebar.classList.toggle('open');
    menuButton.setAttribute('aria-expanded',String(open));
  });

  document.addEventListener('click',event=>{
    if(sidebar&&sidebar.classList.contains('open')&&!event.target.closest('#sidebar')&&!event.target.closest('.menu-toggle')){
      sidebar.classList.remove('open');
      if(menuButton)menuButton.setAttribute('aria-expanded','false');
    }
  });

  document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{
    if(!confirm(form.dataset.confirm||'¿Confirmas esta accion?'))event.preventDefault();
  }));

  const deleteDialog=document.querySelector('#delete-opinion-dialog');
  let deleteForm=null;
  document.querySelectorAll('[data-delete-opinion]').forEach(form=>form.addEventListener('submit',event=>{
    if(!deleteDialog||typeof deleteDialog.showModal!=='function'){
      if(!confirm('¿Esta seguro de que desea eliminar esta opinion? La opinion permanecera registrada, pero no podra recuperarse ni realizar nuevas acciones sobre ella.'))event.preventDefault();
      return;
    }
    event.preventDefault();
    deleteForm=form;
    deleteDialog.showModal();
  }));
  deleteDialog?.querySelector('[data-dialog-cancel]')?.addEventListener('click',()=>{deleteForm=null;deleteDialog.close();});
  deleteDialog?.querySelector('[data-dialog-confirm]')?.addEventListener('click',()=>{const form=deleteForm;deleteForm=null;deleteDialog.close();form?.submit();});

  const detailPanel=document.querySelector('#audit-detail-panel');
  const detailContent=detailPanel?.querySelector('[data-audit-detail-content]');
  const showAuditDetail=row=>{
    if(!detailPanel||!detailContent)return;
    const template=document.getElementById(row.dataset.detailTemplate||'');
    if(!template)return;
    detailContent.replaceChildren(template.content.cloneNode(true));
    detailPanel.classList.add('open');
    document.querySelectorAll('.audit-row.selected').forEach(item=>item.classList.remove('selected'));
    row.classList.add('selected');
    if(window.matchMedia('(max-width: 1000px)').matches)detailPanel.scrollIntoView({behavior:'smooth',block:'start'});
  };
  document.querySelectorAll('.audit-row').forEach(row=>{
    row.addEventListener('click',()=>showAuditDetail(row));
    row.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();showAuditDetail(row);}});
  });
  detailPanel?.querySelector('[data-close-audit-detail]')?.addEventListener('click',()=>{
    detailPanel.classList.remove('open');
    document.querySelectorAll('.audit-row.selected').forEach(item=>item.classList.remove('selected'));
    detailContent.replaceChildren(Object.assign(document.createElement('p'),{className:'muted',textContent:'Selecciona una fila para consultar su detalle.'}));
  });

  const stack=document.querySelector('#toast-stack');
  if(!stack)return;
  document.body.classList.add('toasts-ready');
  document.querySelectorAll('.content > .alert').forEach(alert=>{
    alert.classList.add('toast');
    const close=document.createElement('button');
    close.type='button';
    close.className='toast-close';
    close.setAttribute('aria-label','Cerrar notificacion');
    close.textContent='×';
    const dismiss=()=>{
      alert.classList.add('toast-leaving');
      window.setTimeout(()=>alert.remove(),180);
    };
    close.addEventListener('click',dismiss);
    alert.append(close);
    stack.append(alert);
    if(alert.classList.contains('success'))window.setTimeout(dismiss,4500);
    else if(alert.classList.contains('warning'))window.setTimeout(dismiss,9000);
  });
});
