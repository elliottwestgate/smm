(()=>{'use strict';document.querySelectorAll('.wgpa').forEach(root=>{
 const manual=root.querySelector('[data-delivery-manual]');if(!manual)return;
 const review=manual.querySelector('[name="review_id"]'),dates=manual.querySelector('[data-delivery-dates]');
 const sync=()=>{dates.hidden=!!review.value;dates.querySelectorAll('input,select').forEach(el=>el.disabled=!!review.value);};review.addEventListener('change',sync);sync();
 const pane=root.querySelector('[data-report-export]');
 if(pane){const button=document.createElement('button');button.type='button';button.textContent='Email this report';button.disabled=!root.wgppLoadedSelection;pane.append(button);
  root.addEventListener('wgpp:snapshot',()=>{button.disabled=!root.wgppLoadedSelection;});
  button.addEventListener('click',()=>{const selection=root.wgppLoadedSelection;if(!selection)return;review.value='';sync();for(const key of ['start','end','comparison']){manual.querySelector('[name="'+key+'"]').value=selection[key];}window.location.hash='account-delivery';manual.querySelector('[name="recipients"]').focus();});
 }
 root.addEventListener('wgpp:review-open',event=>{const pane=root.querySelector('[data-review-export]'),id=event.detail?.id;if(!pane||!id||![...review.options].some(o=>o.value===id))return;const button=document.createElement('button');button.type='button';button.textContent='Email this Review';button.addEventListener('click',()=>{review.value=id;sync();window.location.hash='account-delivery';manual.querySelector('[name="recipients"]').focus();});pane.append(button);});
 manual.closest('form').addEventListener('submit',()=>{const message=document.createElement('p');message.setAttribute('role','status');message.textContent='Queueing your private report. You will return to Report Delivery…';manual.append(message);});
});})();
