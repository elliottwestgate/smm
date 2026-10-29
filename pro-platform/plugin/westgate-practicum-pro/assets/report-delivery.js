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

(()=>{'use strict';document.querySelectorAll('[data-delivery-live]').forEach(box=>{
 const config=JSON.parse(box.dataset.deliveryConfig),status=box.querySelector('[data-delivery-progress]'),history=box.querySelector('[data-delivery-history]'),button=box.querySelector('[data-delivery-check]');
 let pending=box.dataset.pending==='1',busy=false,timer=null;
 const el=(tag,text)=>{const n=document.createElement(tag);n.textContent=text;return n;};
 const visible=()=>!document.hidden && !box.closest('[hidden]');
 const later=()=>{clearTimeout(timer);if(pending)timer=setTimeout(()=>check(false),15000);};
 async function check(force){
  if(busy)return;if(!force&&!visible()){later();return;}
  busy=true;button.disabled=true;status.textContent='Checking delivery and preparing queued reports…';
  const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),80000);
  try{
   const body=new URLSearchParams({action:'wgpp_delivery_progress',nonce:config.nonce,workspace:String(config.workspace),advance:'1'});
   const response=await fetch(config.url,{method:'POST',body,credentials:'same-origin',cache:'no-store',signal:controller.signal});const json=await response.json();
   if(!json.success)throw new Error(json.data?.message||'Delivery status is unavailable.');
   const jobs=json.data.jobs||[];pending=jobs.some(j=>['queued','processing','sending'].includes(j.status));
   const open=new Set([...history.querySelectorAll('details[open]')].map(d=>d.dataset.job));history.replaceChildren();
   for(const job of jobs){const d=document.createElement('details');d.dataset.job=job.id;d.open=open.has(job.id)||jobs.length===1;d.append(el('summary',job.status.charAt(0).toUpperCase()+job.status.slice(1)+' · '+job.created+' · '+job.type),el('p',job.period),el('p',job.message),el('p','Last activity: '+job.updated+' · Preparation attempts: '+job.attempts));for(const [email,state]of Object.entries(job.recipients))d.append(el('p',email+' · '+state));history.append(d);}
   if(!jobs.length)history.append(el('p','No report emails yet.'));
   const waiting=jobs.find(j=>['queued','processing','sending'].includes(j.status));status.textContent=waiting?waiting.message+' This page checks automatically while open.':jobs.some(j=>['failed','unknown'].includes(j.status))?'No pending deliveries. Check the failed or interrupted entries below.':'No pending deliveries. Check your inbox for accepted emails.';
  }catch(error){status.textContent=error.name==='AbortError'?'Preparation is still taking time. The worker may still be running; this page will check again.':'Could not check delivery. '+error.message;}
  finally{clearTimeout(timeout);busy=false;button.disabled=false;later();}
 }
 button.addEventListener('click',()=>check(true));window.addEventListener('hashchange',()=>{if(pending&&visible())check(false);});document.addEventListener('visibilitychange',()=>{if(pending&&visible())check(false);});if(pending)check(false);
});})();
