(()=>{'use strict';document.querySelectorAll('.wgpa').forEach(root=>{
 const panel=root.querySelector('[data-trends]');if(!panel)return;
 const config=JSON.parse(root.dataset.config),status=panel.querySelector('[data-trend-status]'),charts=panel.querySelector('[data-trend-charts]'),retry=panel.querySelector('[data-trend-retry]');
 let selected=null,generation=0,timer=null,request=null,loaded=false,polls=0;
 const node=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;return n;};
 const number=v=>v===null?'Unavailable':new Intl.NumberFormat(undefined,{maximumFractionDigits:2}).format(v);
 const short=d=>new Intl.DateTimeFormat('en-US',{month:'2-digit',day:'2-digit',year:'2-digit',timeZone:'UTC'}).format(new Date(d+'T00:00:00Z'));
 function chart(points,title,series,percent=false){
  const section=node('section');section.className='wgpa-trend-chart';section.append(node('h4',title));
  const legend=node('p');series.forEach(([key,label,color])=>{const item=node('span',label);item.style.color=color;item.style.marginRight='18px';legend.append(item);});section.append(legend);
  const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 900 230');svg.setAttribute('role','img');svg.setAttribute('aria-label',title+' for the selected period; values available below.');
  const add=(tag,attrs,text)=>{const e=document.createElementNS(svg.namespaceURI,tag);Object.entries(attrs).forEach(([k,v])=>e.setAttribute(k,v));if(text)e.textContent=text;svg.append(e);return e;};
  const max=Math.max(1,...points.flatMap(p=>series.map(([k])=>p[k]??0))),x=i=>65+i*815/Math.max(1,points.length-1),y=v=>190-v/max*165;
  [0,.5,1].forEach(f=>{add('line',{x1:65,y1:y(max*f),x2:880,y2:y(max*f),stroke:'#cbdbe8'});add('text',{x:58,y:y(max*f)+4,'text-anchor':'end',fill:'#074b78','font-size':12},number(max*f)+(percent?'%':''));});
  const ticks=[...new Set([0,Math.floor((points.length-1)/2),points.length-1])];ticks.forEach(i=>{if(points[i])add('text',{x:x(i),y:217,'text-anchor':i===0?'start':i===points.length-1?'end':'middle',fill:'#074b78','font-size':12},short(points[i].from));});
  series.forEach(([key,label,color])=>{let path='',connected=false;points.forEach((p,i)=>{if(p[key]===null){connected=false;return;}path+=(connected?' L ':' M ')+x(i)+' '+y(p[key]);connected=true;const dot=add('circle',{cx:x(i),cy:y(p[key]),r:3,fill:color});dot.append(nodeNS('title',(p.from===p.to?short(p.from):short(p.from)+'–'+short(p.to))+' · '+label+': '+number(p[key])+(percent?'%':'')));});add('path',{d:path,fill:'none',stroke:color,'stroke-width':2.5});});
  section.append(svg);charts.append(section);
 }
 function nodeNS(tag,text){const n=document.createElementNS('http://www.w3.org/2000/svg',tag);n.textContent=text;return n;}
 function render(data){
  charts.replaceChildren();const points=data.points;
  status.textContent=(data.dimension==='date'?'Daily':data.dimension==='isoYearIsoWeek'?'Weekly (Monday–Sunday)':'Monthly')+' results · '+data.timezone+'. First and last intervals may be partial. Selected period only; no comparison-period overlay.';
  if(data.limited)status.textContent+=' Google reports some results are limited; missing values are not treated as zero.';
  chart(points,'Traffic',[['sessions','Sessions','#0071bc'],['users','Users','#763f93']]);
  chart(points,'Goal events',[['form_events','Form events','#0071bc'],['phone_events','Phone events','#b85b15']]);
  chart(points,'Goal events per 100 sessions',[['goal_rate','Goals per 100 sessions','#0071bc']],true);
  chart(points,'Audience balance',[['new_users','New users','#0071bc'],['returning_users','Returning users','#763f93']]);
  const detail=node('details');detail.append(node('summary','View timeline values'));const table=node('table');table.className='wgpa-trend-table';table.append(node('caption','Timeline values · goal events are recorded form and phone actions'));const head=node('thead'),tr=node('tr');['Date','Sessions','Users','New','Returning','Forms','Phone','Goals / 100'].forEach(t=>{const th=node('th',t);th.scope='col';tr.append(th);});head.append(tr);table.append(head);const body=node('tbody');points.forEach(p=>{const r=node('tr');const date=node('th',p.from===p.to?short(p.from):short(p.from)+'–'+short(p.to));date.scope='row';r.append(date);['sessions','users','new_users','returning_users','form_events','phone_events','goal_rate'].forEach(k=>r.append(node('td',k==='goal_rate' && p[k]!==null?p[k].toFixed(2):number(p[k]))));body.append(r);});table.append(body);detail.append(table);charts.append(detail);
  charts.append(node('p','Users are measured within each interval and must not be added to obtain unique users for the full period. New and returning users can overlap. Goal events are recorded actions, not verified customers. Service-area filtering and additional bot screening are not active. Campaign markers are not connected yet.'));
 }
 async function load(){
  if(!selected||!panel.open||loaded)return;clearTimeout(timer);const id=generation,controller=new AbortController();request=controller;const timeout=setTimeout(()=>controller.abort(),45000);retry.hidden=true;status.textContent='Loading performance over time… Charts appear when all timeline data is ready.';
  const params=new URLSearchParams(selected);params.set('action','wgpp_trends');params.set('nonce',config.nonce);if(config.workspace)params.set('workspace',String(config.workspace));
  try{const response=await fetch(config.url+'?'+params,{credentials:'same-origin',cache:'no-store',signal:controller.signal}),json=await response.json();if(id!==generation)return;if(!json.success)throw new Error(json.data?.message||'Timeline unavailable.');if(json.data.status==='complete'){render(json.data);loaded=true;}else if(json.data.status==='queued'){if(polls++<20)timer=setTimeout(load,1200);else throw new Error('Timeline collection is taking longer than expected. Retry to continue.');}else{status.textContent=json.data.message||'Timeline unavailable.';}}
  catch(e){if(id===generation){status.textContent=e.name==='AbortError'?'Timeline request timed out. Retry to continue.':e.message;retry.hidden=false;}}
  finally{clearTimeout(timeout);if(request===controller)request=null;}
 }
 root.addEventListener('wgpp:snapshot',e=>{++generation;clearTimeout(timer);if(request)request.abort();selected=e.detail.selection;loaded=false;polls=0;charts.replaceChildren();retry.hidden=true;status.textContent=selected?'Open to load performance over time for the displayed dates.':'Timeline will be available once the selected report is complete.';load();});
 panel.addEventListener('toggle',()=>{if(panel.open)load();else{++generation;clearTimeout(timer);if(request)request.abort();}});
 retry.addEventListener('click',()=>{polls=0;load();});
});})();
