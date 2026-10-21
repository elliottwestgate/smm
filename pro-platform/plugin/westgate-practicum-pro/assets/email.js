(()=>{'use strict';
document.querySelectorAll('.wgpa').forEach(root=>{
 const section=root.querySelector('[data-email]');if(!section)return;
 const config=JSON.parse(root.dataset.config),form=root.querySelector('.wgpa-controls'),status=section.querySelector('[data-email-status]'),results=section.querySelector('[data-email-results]');
 let generation=0,timer=null,polls=0,compare=true,request=null;
 const node=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
 const num=v=>v===null?'Unavailable':new Intl.NumberFormat(undefined,{maximumFractionDigits:2}).format(v);
 const delta=(v,p)=>!compare?'':p===null||v===null?'Comparison unavailable':p===0?'No percentage baseline':((v/p-1)>=0?'↑ ':'↓ ')+Math.abs((v/p-1)*100).toFixed(1)+'%';
 function render(data){
  root.wgppCampaignChannel?.('email',data);
  results.replaceChildren();
  const provider=data.provider||'your email provider';const messages={not_selected:'Choose your email provider under Account → Connections.',not_used:'Email reporting is turned off for this company.',not_connected:'Connect '+provider+' under Account → Connections to view email results.',queued:'Collecting campaign reporting from '+provider+'…',processing:'Collecting campaign reporting from '+provider+'…',failed:'Email collection failed. Check authorization and retry after five minutes.'};
  if(data.status!=='complete'){status.textContent=data.message||messages[data.status]||'Email reporting unavailable.';return;}
  compare=data.ranges.comparison!==null;
  status.textContent=`${provider} · campaigns last sent ${data.ranges.current.join(' to ')}${compare?' · compared with '+data.ranges.comparison.join(' to '):' · Comparison off'} · ${data.timezone} · collected ${data.collected_at}`;
  results.append(node('p','Email bot filtering: Not verified. Opens and clicks may include automated activity. No additional bot exclusions have been applied by this tool.'));
  const current=data.current.metrics,prior=data.comparison?.metrics||{open_rate:null,click_rate:null},cards=node('div',undefined,'wgpa-headline');
  [['sends','Sends'],['delivered','Delivered'],['clicks','Clicks'],['optouts','Unsubscribes']].forEach(([k,label])=>{const card=node('div',undefined,'wgpa-result');card.append(node('span',label),node('strong',num(current[k])),node('span',delta(current[k],prior[k])));cards.append(card);});results.append(cards);
  const chart=node('section');chart.append(node('h4','Email response rates'));
  const max=Math.max(1,...['open_rate','click_rate'].flatMap(k=>[current[k]||0,prior[k]||0]));
  ['open_rate','click_rate'].forEach(k=>{const row=node('div',undefined,'wgpa-email-rate');row.append(node('h4',k==='open_rate'?'Open rate':'Click rate'));
   [[current[k],'Current','#0071bc'],[prior[k],'Comparison','#6d899e']].filter((_,i)=>compare||i===0).forEach(([v,label,color])=>{row.append(node('p',`${label}: ${num(v)}${v===null?'':'%'}`));if(v!==null){const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 100 3');svg.setAttribute('role','img');svg.setAttribute('aria-label',label+' '+num(v)+' percent');const bar=document.createElementNS(svg.namespaceURI,'rect');bar.setAttribute('width',String(v/max*100));bar.setAttribute('height','3');bar.setAttribute('fill',color);svg.append(bar);row.append(svg);}});
   if(compare)row.append(node('p',current[k]===null||prior[k]===null?'Comparison unavailable.':(current[k]-prior[k]).toFixed(2)+' percentage points'));chart.append(row);
  });results.append(chart);
  const table=node('table');table.className='wgpa-email-table';table.append(node('caption','Email campaign totals'));
  const head=node('thead'),hr=node('tr');(compare?['Measure','Selected period','Comparison period','Change']:['Measure','Selected period']).forEach(t=>{const th=node('th',t);th.scope='col';hr.append(th);});head.append(hr);table.append(head);
  const body=node('tbody');
  [['sends','Emails sent'],['delivered','Delivered'],['opens','Opens'],['clicks','Clicks'],['bounces','Bounces'],['optouts','Unsubscribes']].forEach(([k,label])=>{const tr=node('tr');tr.append(node('th',label),node('td',num(current[k])));if(compare)tr.append(node('td',num(prior[k])),node('td',delta(current[k],prior[k])));body.append(tr);});table.append(body);results.append(table);
  results.append(node('p',`Combined totals for ${data.current.campaigns.length} campaigns sent in the selected period${compare?'; '+data.comparison.campaigns.length+' in the comparison period':''}.`));
  results.append(node('p','Campaigns are included by their most recent send date. Responses include all opens and clicks recorded for those campaigns when collected, including responses after the selected dates. Opens and clicks are counted once per person within each campaign; the same person may be counted again in another campaign. Rates use emails sent. Delivered means sent minus bounced.'));
  results.append(node('p','Opens and clicks may include privacy or automated activity; bot filtering status is not verified by this connector. Email clicks are not website sessions or goal events, and this report does not claim person-level attribution. Subscriber-list size, growth and demographic profiles are not included in this campaign-only pilot.'));
 }
 async function load(){clearTimeout(timer);if(request)request.abort();const controller=new AbortController();request=controller;const timeout=setTimeout(()=>controller.abort(),45000);const id=++generation;status.textContent='Loading selected email dates… Existing results remain visible until the report is ready.';const params=new URLSearchParams(new FormData(form));params.set('action','wgpp_email');params.set('nonce',config.nonce);if(config.workspace)params.set('workspace',String(config.workspace));
  try{const response=await fetch(config.url+'?'+params,{credentials:'same-origin',cache:'no-store',signal:controller.signal}),json=await response.json();if(id!==generation)return;if(!json.success)throw new Error(json.data?.message||'Email reporting unavailable.');if(['queued','processing'].includes(json.data.status)){if(polls++<60)timer=setTimeout(load,10000);else root.wgppStage('email',()=>render({status:'failed',message:'Email collection is still pending. Website results are available; Apply dates to retry email reporting.'}));}else if(json.data.status==='failed'){root.wgppStage('email',()=>render(json.data));}else{status.textContent='Email results ready. Waiting for the remaining report data…';root.wgppStage('email',()=>render(json.data));}}
  catch(e){if(id===generation){root.wgppStage('email',()=>render({status:'failed',message:e.name==='AbortError'?'Email request timed out. Website results are available; Apply dates to retry email reporting.':e.message}));}}finally{clearTimeout(timeout);if(request===controller)request=null;}
 }
 form.addEventListener('submit',()=>{polls=0;load();});
 form.addEventListener('change',()=>{generation++;clearTimeout(timer);if(request)request.abort();status.textContent='Apply dates to load email campaign results for the new selection.';});
 load();
});
})();
