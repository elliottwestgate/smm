/* One evidence model for the live Report and frozen saved Review. */
(()=>{'use strict';
 const numeric=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const ratio=(n,d,scale=1)=>numeric(n)!==null&&numeric(d)!==null&&d>0?n/d*scale:null;
 const percent=(n,p)=>numeric(n)!==null&&numeric(p)!==null&&p!==0?(n/p-1)*100:null;
 const nf=v=>numeric(v)===null?'Unavailable':v.toLocaleString('en-US',{maximumFractionDigits:2});
 const delta=(v,p,compare)=>!compare?'':percent(v,p)===null?'No percentage baseline':(v>p?'↑ ':v<p?'↓ ':'')+Math.abs(percent(v,p)).toFixed(1)+'%';
 function rowChange(row){
  if(row.current===null||row.previous===null)return '';
  const d=row.current-row.previous,sign=d>0?'+':'';
  if(row.unit==='%')return sign+nf(d)+' pp';
  if(row.label.endsWith('net followers'))return sign+nf(d)+' vs prev.';
  if(row.previous===0)return row.current===0?'No change':'From zero';
  return delta(row.current,row.previous,true);
 }
 function sources(snapshot,kind){
  if(!Array.isArray(snapshot?.sources))return null;
  return snapshot.sources.filter(row=>{const channel=String(row.channel||'').toLowerCase();return kind==='social'?channel==='organic social':channel==='email';}).reduce((sum,row)=>numeric(row.sessions)===null?NaN:sum+row.sessions,0);
 }
 function model(snapshot,channels={}){
  const current=snapshot.current||{},previous=snapshot.comparison||{},m=current.metrics||{},p=previous.metrics||{},compare=!!snapshot.ranges?.comparison;
  const metric=(label,now,before,basis,unit='')=>({label,current:numeric(now),previous:numeric(before),basis,unit});
  const social=sources(current,'social'),priorSocial=sources(previous,'social'),emailVisits=sources(current,'email'),priorEmailVisits=sources(previous,'email');
  const awareness={key:'awareness',title:'Awareness',role:'Earn attention for the Brand',rows:[],notes:[],posts:[]};
  for(const key of ['facebook','instagram']){
   const data=channels[key],name=key==='facebook'?'Facebook':'Instagram';
   if(data?.status!=='complete'){awareness.notes.push(name+': '+(data?.message||(['queued','processing'].includes(data?.status)?'Loading the selected dates…':'Reporting unavailable for these dates.')));continue;}
   awareness.rows.push(metric(name+' views',data.current?.views?.total,data.comparison?.views?.total,'Platform views · includes repeat views'));
   awareness.rows.push(metric(name+' followers',data.current?.followers?.total,data.comparison?.followers?.total,key==='instagram'?'Current total at collection; previous historical total may be unavailable':'End-of-period total'));
   awareness.rows.push(metric(name+' net followers',data.current?.followers?.net,data.comparison?.followers?.net,'Net movement in the selected period'));
   const top=data.posts?.top?.[0];if(top)awareness.posts.push({channel:name,text:top.text,views:numeric(top.views),date:top.date,complete:!!data.posts.complete});
  }
  awareness.notes.push('Views show visibility, not unique people, Brand recall or proven engagement. Facebook and Instagram are kept separate.');
  const acquisition={key:'acquisition',title:'Acquisition',role:'Bring the audience into the Brand hub',rows:[metric('Website sessions',m.sessions,p.sessions,'All website sessions, including any paid traffic'),metric('New users',m.new_users,p.new_users,'GA4 users first seen in the selected period'),metric('Organic social sessions',social,priorSocial,'GA4 Organic Social channel only'),metric('Social share of website sessions',ratio(social,m.sessions,100),ratio(priorSocial,p.sessions,100),'Organic Social sessions ÷ all website sessions','%'),metric('Form events',m.form_events,p.form_events,'Recorded events; not unique leads'),metric('Phone events',m.phone_events,p.phone_events,'Recorded events; not verified completed calls'),metric('Goal events / 100 sessions',ratio(numeric(m.form_events)!==null&&numeric(m.phone_events)!==null?m.form_events+m.phone_events:null,m.sessions,100),ratio(numeric(p.form_events)!==null&&numeric(p.phone_events)!==null?p.form_events+p.phone_events:null,p.sessions,100),'Form + phone events ÷ sessions × 100')],notes:['Social views and website sessions have different audiences and measurement bases. No view-to-visit conversion rate is inferred.','Source-to-goal and page-level funnel steps are not supplied; overall events cannot be assigned to a post or source.'],posts:[]};
  const retention={key:'retention',title:'Retention',role:'Give people a reason to return',rows:[metric('Returning users',m.returning_users,p.returning_users,'Returning website visitors; not confirmed repeat customers'),metric('Returning users / 100 new users',ratio(m.returning_users,m.new_users,100),ratio(p.returning_users,p.new_users,100),'Balance indicator; users may appear in both groups'),metric('Email-attributed sessions',emailVisits,priorEmailVisits,'GA4 Email channel only; not all email clicks or return visits')],notes:[],posts:[]};
  const email=channels.email;
  if(email?.status==='complete')for(const [key,label,unit] of [['sends','Emails sent',''],['clicks','Email clicks',''],['click_rate','Email click rate','%']])retention.rows.push(metric(label,email.current?.metrics?.[key],email.comparison?.metrics?.[key],'Campaigns sent in each period; cumulative responses through collection',unit));
  else retention.notes.push('Email: '+(email?.message||'Reporting unavailable for these dates.'));
  retention.notes.push('Email clicks are not returning users. Privacy and automated activity may affect email response; no person-level attribution is supplied.');
  const goal=acquisition.rows.find(r=>r.label==='Goal events / 100 sessions');
  const handoffs=[{title:'Awareness → Acquisition',evidence:'Organic social: '+nf(social)+' website sessions'+(compare?' · Previous '+nf(priorSocial):''),meaning:'Compare attention with hub visits. Post-specific tagged links are needed to connect the two; their ratio is not a conversion rate.'},{title:'Acquisition → Goal',evidence:nf(goal.current)+' goal events / 100 sessions'+(compare?' · Previous '+nf(goal.previous):''),meaning:goal.current===0?'No form or phone events were recorded. Verify the configured tracking before interpreting this as no enquiries.':compare&&goal.current!==null&&goal.previous!==null&&goal.current<goal.previous?'Goal-event frequency is below the previous period. Check the message-to-action handoff and event tracking.':'Read form and phone events separately. Journey steps and unique leads are not measured here.'},{title:'Retention → Return',evidence:nf(m.returning_users)+' returning users'+(compare?' · Previous '+nf(p.returning_users):''),meaning:compare&&numeric(m.returning_users)!==null&&numeric(p.returning_users)!==null&&m.returning_users<p.returning_users?'Fewer returning visitors are recorded. Read the email response separately and test the return invitation.':'Read return visits alongside email response. Returning visitors do not establish customer loyalty or purchases.'}];
  return {compare,stages:[awareness,acquisition,retention],handoffs};
 }
 function render(target,snapshot,channels={},options={}){
  const data=model(snapshot,channels),node=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
  target.replaceChildren();target.className='wgpa-campaign'+(options.review?' wgpa-campaign-review':'');
  target.append(node('p','THE SOCIAL MEDIA MAGNET LOOP','wgpa-campaign-kicker'),node('h3',options.review?'What the loop tells us':'Awareness → Acquisition → Retention'),node('p','Read the Brand Axis through all three stages. The Message Matrix must align the audience, content and channel handoffs around the Brand.','wgpa-campaign-intro'));
  const grid=node('div',undefined,'wgpa-campaign-grid');target.append(grid);
  for(const [i,stage] of data.stages.entries()){
   const card=node('section',undefined,'wgpa-campaign-stage');card.dataset.campaignStage=stage.key;card.append(node('span','0'+(i+1),'wgpa-campaign-number'),node('h4',stage.title),node('p',stage.role,'wgpa-campaign-role'));
   const t=node('table');t.className='wgpa-campaign-table';t.append(node('caption',stage.title+' evidence'));const head=node('thead'),hr=node('tr');for(const label of data.compare?['Measure','Current','Previous']:['Measure','Current']){const th=node('th',label);th.scope='col';hr.append(th);}head.append(hr);t.append(head);const body=node('tbody');
   const reviewMeasures={awareness:['Facebook views','Instagram views'],acquisition:['Website sessions','Organic social sessions','Goal events / 100 sessions'],retention:['Returning users','Email clicks','Email click rate']};const displayRows=options.review?stage.rows.filter(row=>reviewMeasures[stage.key].includes(row.label)):stage.rows;
   for(const row of displayRows){const tr=node('tr'),label=node('th',row.label);label.scope='row';label.title=row.basis;const currentCell=node('td');currentCell.append(node('strong',nf(row.current)+(row.current===null?'':row.unit)));if(data.compare)currentCell.append(node('small',rowChange(row),'wgpa-campaign-change'));tr.append(label,currentCell);if(data.compare)tr.append(node('td',nf(row.previous)+(row.previous===null?'':row.unit)));body.append(tr);}t.append(body);if(stage.rows.length)card.append(t);
   for(const post of stage.posts)card.append(node('p',post.channel+' · '+(post.complete?'Top post':'Available high-view post')+': “'+String(post.text||'Untitled').slice(0,100)+'” · '+nf(post.views)+' lifetime views.','wgpa-campaign-post'));
   for(const note of stage.notes.filter(n=>/^(Facebook|Instagram|Email):/.test(n)))card.append(node('p',note,'wgpa-campaign-availability'));
   const section=options.sections?.[stage.key];if(section){card.append(node('strong','What it means'),node('p',section.assessment),node('strong','Move to Make'),node('p',section.direction),node('strong','Check the result'),node('p',section.measure));}
   const notes=node('details');notes.append(node('summary','Basis and gaps'));for(const note of [...new Set([...stage.rows.map(r=>r.basis),...stage.notes])])notes.append(node('p',note));card.append(notes);grid.append(card);
  }
  const handoffs=node('section',undefined,'wgpa-campaign-handoffs');handoffs.append(node('h4','Where the loop connects'));for(const row of data.handoffs){const item=node('div',undefined,'wgpa-campaign-handoff');item.append(node('strong',row.title),node('b',row.evidence),node('p',row.meaning));handoffs.append(item);}target.append(handoffs);
  if(options.review)target.append(node('p','Evidence frozen when this Review was created. New connections or dates do not rewrite it.','wgpa-small-note'));
  return data;
 }
 window.WGPPCampaign={model,render,percent,rowChange};
})();
(()=>{'use strict';
document.querySelectorAll('.wgpa').forEach(root=>{
const config=JSON.parse(root.dataset.config),form=root.querySelector('.wgpa-controls'),status=root.querySelector('[data-status]'),report=root.querySelector('[data-report]');
let campaignSnapshot=null,campaignChannels={};
let generation=0,state=null,poll=null,polls=0,request=null,comparisonEnabled=true,printReady=false;
function setPrint(ready,message=''){if(ready&&campaignSnapshot&&['facebook','instagram'].some(k=>campaignChannels[k]?.status==='loading')){ready=false;message='Loading Awareness evidence. Printing is available when collection completes.';}printReady=ready;const button=root.querySelector('[data-print]');button.disabled=!ready;button.title=message;const note=root.querySelector('[data-print-status]');if(note){note.textContent=message;note.hidden=!message;}}
let buffered={};
root.wgppBegin=()=>{buffered={};root.wgppEvidenceReady=false;root.setAttribute('aria-busy','true');};
root.wgppStage=(kind,commit)=>{buffered[kind]=commit;if(buffered.analytics&&(!root.querySelector('[data-email]')||buffered.email)){const ready=buffered;buffered={};ready.analytics();if(ready.email)ready.email();root.setAttribute('aria-busy','false');}};
root.wgppBegin();
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
const value=n=>n===null?'Unavailable':new Intl.NumberFormat(undefined,{maximumFractionDigits:2}).format(n);
const reportDate=d=>{const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(d||'');return m?m[2]+'/'+m[3]+'/'+m[1]:d;};
const change=(n,baseline)=>!comparisonEnabled?'':n===null?(baseline===0?'No percentage baseline':'Comparison unavailable'):(n>0?'↑ ':n<0?'↓ ':'')+Math.abs(n).toFixed(1)+'%';
function row(parent,label,n,delta){const item=el('div',undefined,'wgpa-result');item.append(el('span',label),el('strong',value(n)),el('span',change(delta)));parent.append(item);}
function draw(analysis=null,target=null){if(!analysis&&!state)return;const a=analysis||state.analysis,svg=target||root.querySelector('[data-loop]'),w=960,h=420,c=w/2,mid=210;
 if(!a)return;
 const x=a.horizontal,y=a.vertical,dx=x===null?0:Math.tanh(x/100),dy=y===null?0:Math.tanh(y/100),eff=a.goal_change===null?0:Math.tanh(a.goal_change/100),bx=c+dx*w*.20,by=mid-dy*45;
 const left=w*(.40-.10*dx),right=w*(.40+.10*dx),top=80-12*eff,bottom=h-80+12*eff,scale=Math.min(w*.14,60)/Math.max(20.1,Math.abs(x||0),Math.abs(y||0));
 svg.setAttribute('viewBox',`0 0 ${w} ${h}`);svg.replaceChildren();
 const add=(tag,attrs,text)=>{const n=document.createElementNS('http://www.w3.org/2000/svg',tag);Object.entries(attrs).forEach(([k,v])=>n.setAttribute(k,v));if(text)n.textContent=text;svg.append(n);return n;};
 add('title',{},'Relative Brand movement; displacement is schematic.');
 if(comparisonEnabled)add('path',{d:`M ${c} ${mid} C ${c-w*.40} 65 ${c-w*.40} ${h-65} ${c} ${mid} C ${c+w*.40} 65 ${c+w*.40} ${h-65} ${c} ${mid}`,class:'baseline',fill:'none'});
 add('path',{d:`M ${c} ${mid} C ${c-left} ${top} ${c-left} ${bottom} ${c} ${mid} C ${c+right} ${top} ${c+right} ${bottom} ${c} ${mid}`,transform:`translate(0,${-dy*45})`,class:'current-loop',fill:'none'});
 add('circle',{cx:bx,cy:by,r:42,class:'brand-circle'});
 if(config.brand_logo){const clipId='wgpp-brand-'+Math.random().toString(36).slice(2);const defs=add('defs',{}),clip=document.createElementNS(svg.namespaceURI,'clipPath'),circle=document.createElementNS(svg.namespaceURI,'circle');clip.id=clipId;circle.setAttribute('cx',bx);circle.setAttribute('cy',by);circle.setAttribute('r',37);clip.append(circle);defs.append(clip);add('image',{href:config.brand_logo,x:bx-37,y:by-37,width:74,height:74,preserveAspectRatio:'xMidYMid meet','clip-path':'url(#'+clipId+')'});}else add('text',{x:bx,y:by+5,'text-anchor':'middle'},'Brand');
 add('text',{x:12,y:28,class:'quadrant'},'Acquisition traffic');add('text',{x:w-12,y:28,'text-anchor':'end',class:'quadrant'},'Retention traffic');
 add('text',{x:c,y:75,'text-anchor':'middle',class:'movement-title'},'Traffic '+change(y));
 add('text',{x:c,y:98,'text-anchor':'middle'},!comparisonEnabled?'Sessions • comparison off':y===null?'Sessions • direction unavailable':y===0?'Sessions • unchanged':'Sessions • moving '+(y>0?'up':'down'));
 const arrow=(sx,sy,ex,ey)=>{const angle=Math.atan2(ey-sy,ex-sx),head=Math.min(5,Math.hypot(ex-sx,ey-sy)*.5);add('path',{d:`M ${sx} ${sy} L ${ex} ${ey} M ${ex-head*Math.cos(angle-.6)} ${ey-head*Math.sin(angle-.6)} L ${ex} ${ey} L ${ex-head*Math.cos(angle+.6)} ${ey-head*Math.sin(angle+.6)}`,class:'movement-arrow',fill:'none'});};
 if(x!==null&&x!==0){const start=bx+(x<0?-45:45);arrow(start,by,start+Math.sign(x)*Math.abs(x)*scale,by);}
 if(y!==null&&y!==0){const start=by-45,end=start-Math.abs(y)*scale;y>0?arrow(bx,start,bx,end):arrow(bx,end,bx,start);}
 add('text',{x:x<0?12:w-12,y:315,'text-anchor':x<0?'start':'end',class:'movement-title'},'Audience balance '+change(x));
 add('text',{x:x<0?12:w-12,y:339,'text-anchor':x<0?'start':'end'},'Returning-to-new user ratio • '+(!comparisonEnabled?'comparison off':x===null?'direction unavailable':x<0?'moving left':x>0?'moving right':'unchanged'));
 add('text',{x:12,y:385,class:'quadrant'},'Acquisition traffic');
 add('text',{x:w-12,y:385,'text-anchor':'end',class:'quadrant'},'Retention traffic');
}
function renderProfile(profile,total){
 const target=root.querySelector('[data-profile]');
 const demographics=el('div',undefined,'wgpa-columns');
 target.append(demographics);
 function bars(parent,title,rows,denominator){
   const section=el('section');section.append(el('h4',title));parent.append(section);
   if(rows==null){section.append(el('p','Data unavailable for this range.'));return;}
   if(!rows.length){section.append(el('p','No audience rows returned.'));return;}
   const max=Math.max(...rows.map(r=>r.sessions),1);
   const sum=rows.reduce((n,r)=>n+r.sessions,0);
   const shown=rows.length>12?[...rows].sort((a,b)=>b.sessions-a.sessions).slice(0,12):rows;
   shown.forEach(r=>{
     const item=el('div',undefined,'wgpa-profile-row');
     item.append(el('span',r.label),el('strong',value(r.sessions)+' sessions'));
     const ns='http://www.w3.org/2000/svg',track=document.createElementNS(ns,'svg'),bar=document.createElementNS(ns,'rect');
     track.setAttribute('class','wgpa-track');track.setAttribute('viewBox','0 0 100 2');track.setAttribute('preserveAspectRatio','none');track.setAttribute('aria-hidden','true');
     bar.setAttribute('width',String(r.sessions/max*100));bar.setAttribute('height','2');
     bar.setAttribute('fill',/unknown|not set/i.test(r.label)?'#86a4b8':'#0071bc');
     track.append(bar);item.append(track);section.append(item);
   });
   if(shown.length<rows.length)section.append(el('p','Showing the 12 largest rows of '+rows.length+'. Totals below use all returned rows.'));
   const unknown=rows.filter(r=>/unknown|not set/i.test(r.label)).reduce((n,r)=>n+r.sessions,0);
   section.append(el('p',denominator>0?'Unknown: '+(unknown/denominator*100).toFixed(1)+'% of all sessions.':'Session denominator unavailable.'));
   if(total!==null && sum!==total)section.append(el('p','Dimension rows total '+value(sum)+'; property sessions '+value(total)+'. Reconcile before treating these as exclusive shares.'));
 }
 bars(demographics,'Age ranges',profile.age,total);
 bars(demographics,'Gender',profile.gender,total);
 const locationGroup=el('details',undefined,'wgpa-profile-group');
 locationGroup.append(el('summary','Additional location data'),el('p','Approximate visitor locations, not confirmed customers or service-area demand.'));target.append(locationGroup);
 const locations=el('div',undefined,'wgpa-columns');locationGroup.append(locations);
 bars(locations,'Country',profile.country,total);
 bars(locations,'City',profile.city,total);
 const technologyGroup=el('section',undefined,'wgpa-profile-group');
 technologyGroup.append(el('h4','Technology'));target.append(technologyGroup);
 bars(technologyGroup,'Device platform',profile.device,total);
 target.append(el('p','Age and gender may cover only part of the audience. Unknown is retained. Location does not establish residence or customer status.'));
}
function updateCampaign(){const evidenceReady=!['facebook','instagram'].some(k=>campaignChannels[k]?.status==='loading');if(root.wgppEvidenceReady!==evidenceReady){root.wgppEvidenceReady=evidenceReady;root.dispatchEvent(new CustomEvent('wgpp:evidence'));}const target=root.querySelector('[data-campaign]');if(target&&campaignSnapshot)window.WGPPCampaign.render(target,campaignSnapshot,campaignChannels);if(state?.analysis&&root.wgppLoadedSelection)setPrint(true);}
root.wgppCampaignChannel=(key,data)=>{campaignChannels[key]=data;updateCampaign();};
function render(data){form.elements.start.value=data.ranges.current[0];form.elements.end.value=data.ranges.current[1];state=data;comparisonEnabled=data.ranges.comparison!==null;const a=data.analysis,s=data.snapshot;report.hidden=!a;setPrint(false,'No completed report for these dates.');root.wgppLoadedSelection=null;if(!a){status.textContent=`No exact-range data for ${data.ranges.current.map(reportDate).join(' to ')}. ${data.collection?.current==='failed'?'Google collection failed. Check the reporting details below.':['queued','processing'].includes(data.collection?.current)?'Collecting the selected Google reports… This view updates automatically.':'Check your connections and select a period with available data.'}`;if(data.collection_detail?.current)status.textContent+=' '+data.collection_detail.current;return;}
 root.querySelector('[data-company]').textContent=s.company;
 campaignSnapshot={current:s,comparison:data.comparison_snapshot,ranges:data.ranges};campaignChannels={facebook:{status:'loading',message:'Loading the selected dates…'},instagram:{status:'loading',message:'Loading the selected dates…'}};
 const filtering=root.querySelector('[data-filter-status]');filtering.replaceChildren();
 const strip=el('p',s.mode==='connected'?'Known bots: GA4 excluded · Additional screening: Not connected · Market filter: Off':'Known bots: Unverified · Additional screening: Not connected · Market filter: Off','wgpa-filter-summary');
 filtering.append(strip);
 const details=el('details');details.append(el('summary','Filtering details'));
 details.append(el('p',s.mode==='connected'?'GA4 automatically excludes known bots; the excluded volume is not supplied.':'Bot filtering status is not verified for this imported snapshot.'));
 details.append(el('p','Additional bot screening is not connected. Remaining visits have not been verified as human traffic.'));
 details.append(el('p','Service-area filtering is not applied. Out-of-market visits remain included; geography alone does not establish bot activity.'));
 filtering.append(details);

 status.textContent=`${data.ranges.current.map(reportDate).join(' to ')}${comparisonEnabled?' · compared with '+data.ranges.comparison.map(reportDate).join(' to '):' · Comparison off'} · ${s.timezone} · ${s.mode==='imported'?'Saved snapshot — not automatically refreshed':'Connected provider'}${!comparisonEnabled||data.comparison_available?'':' · Comparison unavailable'}`;
 if(!data.comparison_available && data.collection_detail?.comparison)status.textContent+=' · Comparison: '+data.collection_detail.comparison;
 form.elements.start.value=data.ranges.current[0];form.elements.end.value=data.ranges.current[1];
 root.wgppLoadedSelection=(!comparisonEnabled||data.comparison_available)?Object.fromEntries(new FormData(form)):null;
 updateCampaign();root.dispatchEvent(new CustomEvent('wgpp:snapshot',{detail:{selection:root.wgppLoadedSelection}}));
 for(const key of ['profile','rate','headline','audience','goals','sources','notes'])root.querySelector(`[data-${key}]`).replaceChildren();
 [['sessions','Sessions'],['users','Users'],['form_events','Form events'],['phone_events','Phone events']].forEach(([key,label])=>row(root.querySelector('[data-headline]'),label,a.metrics[key],a.changes[key]));
 root.querySelector('.wgpa-movement h3').textContent='Brand Axis · Balance of the Loop';
 row(root.querySelector('[data-rate]'),'Goal events per 100 sessions',a.goal_rate,a.goal_change);
 if(comparisonEnabled)root.querySelector('[data-rate]').append(el('p','Previous: '+value(a.prior_goal_rate)+' recorded form and phone events per 100 sessions.'));
 [['new_users','New users'],['returning_users','Returning users']].forEach(([key,label])=>row(root.querySelector('[data-audience]'),label,a.metrics[key],a.changes[key]));
 [['form_events','Form events'],['phone_events','Phone events']].forEach(([key,label])=>row(root.querySelector('[data-goals]'),label,a.metrics[key],a.changes[key]));
 renderProfile(s.profile||{},s.metrics.sessions);
 (data.source_summary||a.sources).forEach(source=>{const details=el('details');const summary=el('summary',`${source.name} · ${value(source.current)} sessions · ${change(source.change,source.comparison)}`);details.append(summary,el('p',source.evidence.join('; ')));root.querySelector('[data-sources]').append(details);});
 const sum=a.sources.reduce((n,r)=>n+r.current,0);
 const notes=[...s.notes,'Brand Axis: the SMM model of Loyalty and Engagement in environmental and competitive contexts. The diagram uses session change and change in the returning-to-new-user ratio as directional signals; it does not directly measure those qualities or prove their causes.','Message Matrix: target audiences × content buckets × channels. Use content signals to Develop, Reframe or Create a related sequence in the publishing calendar.',`Source rows: ${value(sum)} sessions; property total: ${value(a.metrics.sessions)}.`, 'New and returning users may overlap; returning users are website visitors, not confirmed repeat customers.','Goal events are recorded actions, not unique customers or verified calls. Definitions and historical continuity require verification.','Marker displacement and loop deformation are schematic. Arrow shafts use a shared percentage scale; horizontal and vertical metrics have different denominators.'];
 notes.forEach(n=>root.querySelector('[data-notes]').append(el('p',n)));
 root.querySelector('[data-direction]').textContent=comparisonEnabled?'Awareness, Acquisition and Retention inform Brand Axis balance. This diagram plots session change and returning-to-new user ratio. Current loop in blue; previous baseline dashed blue-gray. These inform Brand alignment; they do not directly measure Loyalty or Engagement.':'Comparison is off. The Brand marker is centered; no directional movement is inferred.';
 draw();setPrint(true);}
async function load(){clearTimeout(poll);if(request)request.abort();request=new AbortController();const controller=request;const timeout=setTimeout(()=>controller.abort(),45000);root.wgppLoadedSelection=null;root.dispatchEvent(new CustomEvent('wgpp:snapshot',{detail:{selection:null}}));const id=++generation;status.textContent=state?.analysis?'Loading selected dates… The previous report remains visible until the new report is ready.':'Loading selected dates… The report will appear when collection is complete.';setPrint(false,'Loading selected report…');const params=new URLSearchParams(new FormData(form));params.set('action','wgpp_snapshot');params.set('nonce',config.nonce);if(config.workspace)params.set('workspace',String(config.workspace));try{const response=await fetch(config.url+'?'+params.toString(),{credentials:'same-origin',cache:'no-store',signal:controller.signal});const json=await response.json();if(id!==generation)return;if(!json.success)throw new Error(json.data?.message||'Report unavailable.');
const pending=Object.values(json.data.collection||{}).some(v=>['queued','processing'].includes(v));
if(pending&&polls<120){polls++;poll=setTimeout(load,1500);}
else if(pending){status.textContent='Collection is taking longer than expected. The previous report has not been changed. Apply dates to retry.';root.setAttribute('aria-busy','false');}
else if(Object.values(json.data.collection||{}).includes('failed')){status.textContent='Selected dates could not be loaded. '+Object.values(json.data.collection_detail||{}).join(' ')+' The previous report has not been changed.';root.setAttribute('aria-busy','false');}
else{status.textContent='Website analytics ready. Waiting for the remaining Report data…';root.wgppStage('analytics',()=>render(json.data));}
}catch(e){if(id===generation){root.setAttribute('aria-busy','false');status.textContent=e.name==='AbortError'?'The report request timed out. Apply dates to retry.':e.message;}}finally{clearTimeout(timeout);if(request===controller)request=null;}}

root.querySelector('[data-print]').addEventListener('click',()=>{
 if(!state?.analysis || report.hidden || !printReady)return;
 const exportTitle=window.WGPPExportName(state.snapshot.company,new Date()).replace('Message-Magnet-Performance','Pro-Analytics-Report');
 const popup=window.open('',exportTitle);
 if(!popup){status.textContent='Allow a print window to save this snapshot.';return;}
 popup.opener=null;
 const d=popup.document;d.title=exportTitle;
 const viewport=d.createElement('meta');viewport.name='viewport';viewport.content='width=device-width,initial-scale=1';d.head.append(viewport);
 const copy=root.cloneNode(true);
 // WordPress emoji SVG images otherwise expand to page width in the isolated print document.
 copy.querySelectorAll('img.emoji,img.wp-smiley').forEach(n=>n.replaceWith(d.createTextNode(n.alt||'')));
 copy.querySelectorAll('form,button,.wgpa-export-bar,.wgpa-operation-status,[data-print-status],[data-review-controls],[data-primary-panel="account"],[data-primary-panel="review"],[data-primary-tabs],.wgpa-report-links').forEach(n=>n.remove());
 // Print all source details and notes, including evidence and limitations.
 copy.querySelectorAll('details').forEach(n=>n.open=true);
 // iOS can fragment details inside a grid despite break-inside. Print ordinary
 // single-column cards instead; the interactive dashboard remains expandable.
 copy.querySelectorAll('[data-sources] details').forEach(details=>{
   const card=el('article',undefined,'wgpa-print-source');
   const summary=details.querySelector('summary');
   if(summary)card.append(el('h4',summary.textContent));
   details.querySelectorAll('p').forEach(p=>card.append(p.cloneNode(true)));
   details.replaceWith(card);
 });
 const exportStamp=el('p','Exported '+new Intl.DateTimeFormat('en-US',{timeZone:'America/Chicago',dateStyle:'medium',timeStyle:'long'}).format(new Date()),'wgpa-preview-note');
 copy.append(exportStamp);
 d.body.append(copy);
 const css=d.createElement('style');
 css.textContent=`@page{size:letter portrait;margin:.5in}
 body{margin:0;background:white;font-family:Arial,sans-serif}
 .wgpa{border:0;max-width:none;margin:0;padding:0;box-shadow:none;font:10pt/1.35 Arial,sans-serif}
 .wgpa header{padding-bottom:8px;gap:12px}.wgpa header>div:last-child{white-space:nowrap}
 .wgpa img{max-width:100%;max-height:160px;object-fit:contain}.wgpa .wgpp-pro-logo{width:210px!important;height:36px!important;object-fit:cover}.wgpa img.emoji,.wgpa img.wp-smiley{width:1em!important;height:1em!important;display:inline!important;vertical-align:-.1em}.wgpa-logo{width:90px}.wgpa h2{font-size:20pt;margin:6px 0}.wgpa-title{font-size:15pt}
 .wgpa-preview-note{font-size:8pt;margin:0 0 8px}
 .wgpa h3{font-size:13pt;margin:12px 0 8px;padding-bottom:5px;break-after:avoid;page-break-after:avoid}
 .wgpa h4{font-size:11pt!important;margin:10px 0 6px;break-after:avoid}
 .wgpa-headline{margin:10px 0}.wgpa-headline strong{font-size:19pt}.wgpa-result{padding:7px}
 .wgpa-movement,.wgpa-rate,.wgpa-result,.wgpa-profile-row,.wgpa-sources details,.wgpa [data-profile] section{break-inside:avoid;page-break-inside:avoid}
 .wgpa [data-loop]{height:auto;width:100%;max-height:250px}
 .wgpa-rate{padding:6px;margin:5px 0 10px}.wgpa-rate strong{font-size:16pt}
 .wgpa-rate p,.wgpa [data-direction]{font-size:8pt;margin:5px 0}
 .wgpa-columns{gap:16px}
 .wgpa-sources{display:block;margin:10px 0}
 .wgpa-print-source{display:inline-block;width:100%;box-sizing:border-box;border:1px solid #cbdbe8;padding:7px;margin:0 0 7px;break-inside:avoid;page-break-inside:avoid}
 .wgpa-print-source h4{margin:0 0 4px;font-size:10pt!important}
 .wgpa-print-source p{margin:0;font-size:8pt;overflow-wrap:anywhere}
 .wgpa-profile-group{display:inline-block;width:100%;vertical-align:top;break-inside:avoid;page-break-inside:avoid}
 .wgpa-profile-group>h4{margin-top:8px}
 .wgpa details{padding:7px}.wgpa details p{margin:5px 0}
 .wgpa-profile-row{padding:4px 0}.wgpa-track{height:6px}
 .wgpa [data-profile] section p{font-size:8pt}
 .wgpa [data-notes]{font-size:8pt}
 button{display:none!important}
 `;
 const link=d.createElement('link');link.rel='stylesheet';
 link.href=config.styles+(config.styles.includes('?')?'&':'?')+'print=20261019-1500';
 link.onload=()=>{d.head.append(css);window.WGPPPrintReady(popup,exportTitle);};
 link.onerror=()=>{d.head.append(css);const p=d.createElement('p');p.textContent='Print styling could not load. Close this window and try again.';d.body.prepend(p);};
 d.head.append(link);
});

function syncDates(){
 const preset=form.elements.preset.value;form.elements.start.readOnly=preset!=='custom';
 if(preset==='custom')return;
 const end=form.elements.end.value;if(!/^\d{4}-\d{2}-\d{2}$/.test(end))return;
 const date=new Date(end+'T00:00:00Z');if(!Number.isFinite(date.getTime()))return;
 date.setUTCDate(date.getUTCDate()-Number(preset)+1);form.elements.start.value=date.toISOString().slice(0,10);
}
form.addEventListener('submit',e=>{e.preventDefault();syncDates();root.wgppBegin();polls=0;load();});
form.addEventListener('change',()=>{syncDates();++generation;clearTimeout(poll);if(request)request.abort();root.wgppBegin();root.setAttribute('aria-busy','false');setPrint(false,'Select Apply dates before printing.');root.wgppLoadedSelection=null;root.dispatchEvent(new CustomEvent('wgpp:snapshot',{detail:{selection:null}}));status.textContent='Dates changed. Select Apply dates to load this range. The displayed report still uses the previous dates.';});
syncDates();
root.wgppDrawLoop=(analysis,target)=>{const before=comparisonEnabled;comparisonEnabled=true;try{draw(analysis,target);}finally{comparisonEnabled=before;}};
load();
});})();
