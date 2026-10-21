const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/reviews.js','utf8');
const helper=source.slice(source.indexOf(' function syncRequest()'),source.indexOf(' function reviewView('));
const handlers=source.slice(source.indexOf(' if(!config.reviews_ready)'),source.lastIndexOf(' loadHistory();'));
const events={},changes={},classes=new Set(),calls=[];
const request={classList:{toggle(name,on){on?classes.add(name):classes.delete(name);}},focus(){}};
const confirm={hidden:true},status={textContent:''},generate={focus(){this.focused=true;}},cancel={};
const root={wgppLoadedSelection:{start:'2026-09-20',end:'2026-10-19',comparison:'prior'},wgppEvidenceReady:true,
 addEventListener(name,fn){events[name]=fn;},querySelector(selector){return selector==='[data-review-cancel]'?cancel:{textContent:''};}};
const context={root,request,confirm,status,generate,form:{addEventListener(name,fn){changes[name]=fn;}},history:{},config:{reviews_ready:true,can_review:true},
 selection:null,requestId:null,busy:false,quoteVersion:0,crypto:{randomUUID:()=> 'request-id'},date:s=>s,
 api:async(operation)=>{calls.push(operation);return {can_generate:true,remaining:2,limit:4};},loadHistory:async()=>{},showReport(){}};
vm.createContext(context);vm.runInContext(helper+'(function(){'+handlers+'})();',context);
(async()=>{
 assert.equal(request.disabled,false);
 await request.onclick();assert.equal(request.disabled,true);assert.equal(request.textContent,'✓ Request ready');assert(classes.has('wgpa-review-request-ready'));assert(generate.focused);assert.equal(confirm.hidden,false);
 events['wgpp:evidence']();assert.equal(request.disabled,true,'provider updates cannot re-enable the completed request step');
 await request.onclick();assert.deepEqual(calls,['preview'],'repeated request does not reserve or generate a Review');
 cancel.onclick();assert.equal(request.disabled,false);assert.equal(confirm.hidden,true);assert(!classes.has('wgpa-review-request-ready'));assert(status.textContent.includes('No Review allowance was used'));
 await request.onclick();changes.change();assert.equal(request.textContent,'Request Pro Analytics Review');assert.equal(confirm.hidden,true);assert.equal(request.disabled,true,'date changes invalidate the request');
 root.wgppLoadedSelection={comparison:'prior'};events['wgpp:snapshot']();assert.equal(request.disabled,false);
 context.api=async()=>{throw Error('Allowance unavailable');};await request.onclick();assert.equal(request.disabled,false);assert(status.textContent.includes('Allowance unavailable'));
 console.log('PASS request-ready, cancellation, evidence updates, date invalidation and preview failures preserve the Review allowance');
})().catch(error=>{console.error(error);process.exitCode=1;});
