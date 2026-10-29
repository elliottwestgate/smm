const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element{constructor(){this.handlers={};this.children=[];this.value='';this.disabled=false;}addEventListener(k,f){this.handlers[k]=f;}append(...x){this.children.push(...x);}focus(){this.focused=true;}setAttribute(){}closest(){return form;}}
const form=new Element(),manual=new Element(),review=new Element(),dates=new Element(),recipients=new Element(),pane=new Element(),reviewPane=new Element();review.options=[{value:''},{value:'saved-review'}];
const inputs=Object.fromEntries(['start','end','comparison'].map(k=>[k,new Element()]));
manual.querySelector=s=>s==='[name="review_id"]'?review:s==='[data-delivery-dates]'?dates:s==='[name="recipients"]'?recipients:inputs[/name="([^"]+)"/.exec(s)?.[1]];
dates.querySelectorAll=()=>Object.values(inputs);
const root=new Element();root.wgppLoadedSelection=null;root.querySelector=s=>s==='[data-delivery-manual]'?manual:s==='[data-report-export]'?pane:s==='[data-review-export]'?reviewPane:null;
const window={location:{hash:''}};
vm.runInNewContext(fs.readFileSync('pro-platform/plugin/westgate-practicum-pro/assets/report-delivery.js','utf8'),{document:{querySelectorAll:selector=>selector==='[data-delivery-live]'?[]:[root],createElement:()=>new Element()},window});
const email=pane.children[0];assert(email.disabled);root.wgppLoadedSelection={start:'2026-10-01',end:'2026-10-28',comparison:'none'};root.handlers['wgpp:snapshot']();assert(!email.disabled);email.handlers.click();assert.equal(inputs.start.value,'2026-10-01');assert.equal(inputs.comparison.value,'none');assert.equal(window.location.hash,'account-delivery');assert(recipients.focused);
review.value='saved-review';review.handlers.change();assert(dates.hidden);assert(inputs.start.disabled);review.value='';review.handlers.change();assert(!dates.hidden);assert(!inputs.start.disabled);
root.handlers['wgpp:review-open']({detail:{id:'saved-review'}});reviewPane.children[0].handlers.click();assert.equal(review.value,'saved-review');assert(dates.hidden);console.log('PASS Applied report dates, saved Review selection and private delivery navigation');
