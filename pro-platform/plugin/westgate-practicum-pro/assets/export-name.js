(function(){
'use strict';
function exportName(company,now){
 const parts=new Intl.DateTimeFormat('en-CA',{timeZone:'America/Chicago',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'}).formatToParts(now||new Date());
 const p=Object.fromEntries(parts.map(v=>[v.type,v.value]));
 const name=String(company||'Company').normalize('NFKD').replace(/[^a-zA-Z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,70)||'Company';
 return name+'_Message-Magnet-Performance_'+p.year+'-'+p.month+'-'+p.day+'_'+p.hour+'-'+p.minute+'-'+p.second+'_CT';
}
if(typeof module!=='undefined'&&module.exports)module.exports=exportName;
else {
 window.WGPPExportName=exportName;
 window.WGPPPrintReady=function(popup,title){
  const d=popup.document;d.title=title;popup.name=title;
  const bar=d.createElement('div');bar.className='wgpp-pdf-save-bar';
  const label=d.createElement('label');label.textContent='PDF filename';
  const field=d.createElement('input');field.value=title+'.pdf';field.readOnly=true;field.setAttribute('aria-label','PDF filename');field.onclick=()=>field.select();label.append(field);
  const button=d.createElement('button');button.type='button';button.textContent='Print / Save PDF';
  button.onclick=()=>{d.title=title;popup.focus();popup.requestAnimationFrame(()=>popup.setTimeout(()=>{d.title=title;popup.print();},200));};
  const note=d.createElement('p');note.textContent='Choose Save as PDF in the print dialog. If your browser does not fill in the filename, select and copy the name above.';
  bar.append(label,button,note);d.body.prepend(bar);
  const style=d.createElement('style');style.textContent='.wgpp-pdf-save-bar{font:14px/1.4 Arial,sans-serif;color:#074b78;background:#eaf5fb;padding:14px 18px;border-bottom:2px solid #29abe2;display:flex;align-items:end;gap:12px;flex-wrap:wrap}.wgpp-pdf-save-bar label{display:grid;gap:5px;flex:1 1 420px;font-weight:bold}.wgpp-pdf-save-bar input{box-sizing:border-box;width:100%;font:13px Arial,sans-serif;padding:9px;border:1px solid #7999b2;border-radius:4px;background:white;color:#074b78}.wgpp-pdf-save-bar button{font:600 14px Arial,sans-serif;border:0;border-radius:5px;padding:11px 16px;background:#0071bc;color:white;cursor:pointer}.wgpp-pdf-save-bar p{width:100%;margin:0;font-size:12px}@media print{.wgpp-pdf-save-bar{display:none!important}}';d.head.append(style);
  // Give the browser a painted, titled document before it snapshots PDF metadata.
  button.onclick();
 };
}
})();
