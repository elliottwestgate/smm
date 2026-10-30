(()=>{'use strict';document.querySelectorAll('.wgpa').forEach((root,index)=>{
 let currentPrimary='report',currentAccount='company',restoring=true;
 const remember=()=>{if(!restoring)window.history.replaceState(null,'',window.location.pathname+window.location.search+(currentPrimary==='account'?'#account-'+currentAccount:'#'+currentPrimary));};
 function tabs(buttonSelector,panelSelector,attribute,panelAttribute,prefix,onSelect){
  const buttons=[...root.querySelectorAll(buttonSelector)],panels=[...root.querySelectorAll(panelSelector)];if(!buttons.length)return;
  const parent=buttons[0].parentElement;parent.setAttribute('role','tablist');
  const select=(key,focus=false)=>{buttons.forEach(b=>{const active=b.getAttribute(attribute)===key;b.setAttribute('aria-selected',String(active));b.tabIndex=active?0:-1;if(active&&focus)b.focus();});panels.forEach(p=>p.hidden=p.getAttribute(panelAttribute)!==key);if(onSelect)onSelect(key);};
  buttons.forEach((b,i)=>{const key=b.getAttribute(attribute),panel=panels.find(p=>p.getAttribute(panelAttribute)===key);b.id=prefix+'-tab-'+key;b.setAttribute('role','tab');b.setAttribute('aria-controls',prefix+'-panel-'+key);if(panel){panel.id=prefix+'-panel-'+key;panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',b.id);panel.tabIndex=0;}
   b.addEventListener('click',()=>select(key));b.addEventListener('keydown',e=>{let target;if(e.key==='ArrowRight')target=(i+1)%buttons.length;if(e.key==='ArrowLeft')target=(i+buttons.length-1)%buttons.length;if(e.key==='Home')target=0;if(e.key==='End')target=buttons.length-1;if(target!==undefined){e.preventDefault();select(buttons[target].getAttribute(attribute),true);}});
  });select(buttons[0].getAttribute(attribute));return select;
 }
 const select=tabs('[data-primary-tab]','[data-primary-panel]','data-primary-tab','data-primary-panel','wgpa-'+index,key=>{root.querySelector('[data-shared-dates]').hidden=key==='account';root.querySelector('[data-print]').hidden=key!=='report';const links=root.querySelector('.wgpa-report-links');if(links)links.hidden=key!=='report';currentPrimary=key;root.wgppSyncActions?.();remember();});
 const accountSelect=tabs('[data-account-tab]','[data-account-panel]','data-account-tab','data-account-panel','wgpa-account-'+index,key=>{currentAccount=key;remember();});
 const restore=()=>{const hash=window.location.hash;if(/^#account-(profile|company|connections|foundations|users|delivery|billing)$/.test(hash)){select('account');accountSelect(hash.slice(9));if(hash==='#account-users'){const team=root.querySelector('#team');if(team)team.open=true;}if(hash==='#account-company'){const profile=root.querySelector('[data-account-panel="company"] details');if(profile)profile.open=true;}}else if(hash==='#review'||hash==='#report'){select(hash.slice(1));}};
 const connections=root.querySelector('[data-account-panel="connections"]');
 const focusConnection=()=>{if(currentPrimary!=='account'||currentAccount!=='connections'||!connections)return;const service=new URLSearchParams(window.location.search).get('connection');if(!['google','email','facebook','instagram'].includes(service))return;const target=connections.querySelector('[data-connection="'+service+'"]');if(!target)return;if(target.tagName==='DETAILS')target.open=true;else if(service==='google'){const details=target.querySelector('details');if(details)details.open=true;}target.style.scrollMarginTop='calc(var(--wgpa-sticky-top, 0px) + var(--wgpa-sticky-height, 0px) + 12px)';const notice=root.querySelector('[data-account-notice]');if(notice){target.before(notice);notice.style.scrollMarginTop='calc(var(--wgpa-sticky-top, 0px) + var(--wgpa-sticky-height, 0px) + 12px)';notice.tabIndex=-1;notice.focus({preventScroll:true});}requestAnimationFrame(()=>{(notice||target).scrollIntoView({block:'start'});});};
 restore();restoring=false;focusConnection();window.addEventListener('hashchange',()=>{restoring=true;restore();restoring=false;focusConnection();});
 if(connections){connections.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{form.setAttribute('aria-busy','true');const action=form.querySelector('[name="action"]')?.value||'';let message=form.querySelector('[data-connection-progress]');if(!message){message=document.createElement('p');message.dataset.connectionProgress='';message.setAttribute('role','status');form.append(message);}message.textContent=action.endsWith('_start')?'Opening authorization. You will return to this connection when finished…':'Saving and verifying this connection. Please wait…';requestAnimationFrame(()=>form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button=>button.disabled=true));}));window.addEventListener('pageshow',()=>{connections.querySelectorAll('form[aria-busy="true"]').forEach(form=>{form.removeAttribute('aria-busy');form.querySelectorAll('button').forEach(button=>button.disabled=false);form.querySelector('[data-connection-progress]')?.remove();});});}
 const sticky=root.querySelector('[data-sticky-controls]'),links=root.querySelector('.wgpa-report-links');if(sticky&&links){sticky.append(links);links.hidden=currentPrimary!=='report';}
 // Both exports occupy the same stable location below the date controls.
 const actions=document.createElement('div');actions.className='wgpa-export-bar';actions.dataset.exportBar='';
 const reportExport=document.createElement('div');reportExport.className='wgpa-export-pane';reportExport.dataset.reportExport='';
 const reportLabel=document.createElement('strong');reportLabel.textContent='Report';
 const reportPrint=root.querySelector('[data-print]'),oldPrintHome=reportPrint.parentElement;
 reportPrint.textContent='Print / Save Report';reportPrint.classList.add('wgpa-export-button');
 reportExport.append(reportLabel,reportPrint);oldPrintHome.remove();
 const reviewExport=document.createElement('div');reviewExport.className='wgpa-export-pane';reviewExport.dataset.reviewExport='';
 actions.append(reportExport,reviewExport);if(sticky)sticky.insertBefore(actions,links||null);
 const progress=document.createElement('div');progress.className='wgpa-operation-status';progress.dataset.workspaceProgress='';progress.setAttribute('role','status');progress.setAttribute('aria-live','polite');progress.hidden=true;if(sticky)sticky.append(progress);
 const reportStatus=root.querySelector('[data-status]'),reviewStatus=root.querySelector('[data-review-status]');
 function progressTone(message){
  if(/failed|timed out|unavailable|could not|longer than expected|not configured/i.test(message))return 'error';
  if(/loading|collecting|waiting|preparing|generating|checking|opening|still processing/i.test(message))return 'loading';
  if(/dates changed|select apply dates/i.test(message))return 'notice';
  return '';
 }
 root.wgppSyncActions=()=>{
  const reviewOpen=!root.querySelector('[data-saved-review]').hidden;
  actions.hidden=currentPrimary==='account'||(currentPrimary==='review'&&!reviewOpen);
  reportExport.hidden=currentPrimary!=='report';reviewExport.hidden=currentPrimary!=='review'||!reviewOpen;
  const source=currentPrimary==='report'?reportStatus:currentPrimary==='review'&&!reviewOpen?reviewStatus:null;
  const message=source?.textContent||'',tone=progressTone(message);
  progress.hidden=!tone;progress.dataset.tone=tone;
  const label=tone==='loading'?'In progress':tone==='error'?'Needs attention':'Dates not applied';
  const text=tone?label+' — '+message:'';if(progress.textContent!==text)progress.textContent=text;
  reportStatus.classList.toggle('wgpa-status-promoted',source===reportStatus&&!!tone);
  reviewStatus.classList.toggle('wgpa-status-promoted',source===reviewStatus&&!!tone);
 };
 for(const node of [reportStatus,reviewStatus])new MutationObserver(()=>root.wgppSyncActions()).observe(node,{childList:true,characterData:true,subtree:true});
 root.wgppSyncActions();
 const offsets=()=>{const bar=document.getElementById('wpadminbar');const offset=bar&&['fixed','sticky'].includes(getComputedStyle(bar).position)?Math.max(0,bar.getBoundingClientRect().bottom):0;root.style.setProperty('--wgpa-sticky-top',offset+'px');root.style.setProperty('--wgpa-sticky-height',(sticky?.getBoundingClientRect().height||0)+'px');};
 offsets();window.addEventListener('resize',offsets);if(sticky)new ResizeObserver(offsets).observe(sticky);
 const targets={overview:'[data-campaign]',awareness:'[data-campaign-stage="awareness"]',acquisition:'[data-campaign-stage="acquisition"]',retention:'[data-campaign-stage="retention"]',timeline:'[data-trends]',sources:'[data-sources]',audience:'[data-audience]',goals:'[data-goals]',email:'[data-email]',facebook:'[data-facebook]',instagram:'[data-instagram]'};
 root.querySelectorAll('[data-report-jump]').forEach(button=>button.addEventListener('click',()=>{const target=root.querySelector(targets[button.dataset.reportJump]);if(!target || target.closest('[hidden]'))return;if(target.tagName==='DETAILS')target.open=true;target.scrollIntoView({behavior:'smooth',block:'start'});}));
 // Development imports stay available under Account rather than before the report.
 const account=root.querySelector('[data-primary-panel="account"]');let sibling=root.nextElementSibling;
 while(sibling?.classList.contains('wgpa-import')){const next=sibling.nextElementSibling;account.append(sibling);sibling=next;}
});})();
