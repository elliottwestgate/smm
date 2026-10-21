const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/analytics.js','utf8');
const coordinator=source.slice(source.indexOf('let buffered={}'),source.indexOf('const el='));
const root={setAttribute(){},querySelector(){return {};}};vm.runInNewContext(coordinator,{root});
let visible=[];root.wgppStage('analytics',()=>visible.push('analytics'));assert.deepEqual(visible,[]);
root.wgppStage('email',()=>visible.push('email'));assert.deepEqual(visible,['analytics','email']);
root.wgppBegin();root.wgppStage('email',()=>visible.push('stale'));root.wgppBegin();
root.wgppStage('analytics',()=>visible.push('new analytics'));assert.equal(visible.length,2);
root.wgppStage('email',()=>visible.push('new email'));assert.deepEqual(visible,['analytics','email','new analytics','new email']);
console.log('PASS report waits for both providers and discards buffered results on date changes');

// Exercise the real email request path: optional failures must release a ready
// website report, clear stale email results, and preserve company/date scope.
const emailSource=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/email.js','utf8');
const emailLoad=emailSource.slice(emailSource.indexOf(' async function load'),emailSource.indexOf(" form.addEventListener('submit'"));
(async()=>{
 for(const failure of ['provider','api','network','timeout','pending']){
  const commits=[],r={setAttribute(){},querySelector(){return {};}};
  vm.runInNewContext(coordinator,{root:r});r.wgppStage('analytics',()=>commits.push('website'));
  let rendered;
  const ctx={root:r,status:{},form:{},config:{url:'/ajax',nonce:'test',workspace:8791},URLSearchParams,AbortController,FormData:class{*[Symbol.iterator](){yield ['comparison','prior'];}},setTimeout(){return 1;},clearTimeout(){},render(data){rendered=data;commits.push('email');},fetch:async(url,opts)=>{
   assert(url.includes('workspace=8791'));assert.equal(opts.credentials,'same-origin');
   if(failure==='network')throw new Error('Network unavailable');
   if(failure==='timeout')throw Object.assign(new Error('Aborted'),{name:'AbortError'});
   return {json:async()=>failure==='api'?{success:false,data:{message:'Email unavailable'}}:{success:true,data:{status:failure==='pending'?'processing':'failed'}}};
  }};
  await vm.runInNewContext('let generation=0,timer=null,polls=60,request=null;'+emailLoad+';load();',ctx);
  assert.deepEqual(commits,['website','email'],failure+' releases website report');
  assert.equal(rendered.status,'failed');
 }
 console.log('PASS optional email failures, request errors, timeouts and exhausted polling release the website report');
})().catch(e=>{console.error(e);process.exitCode=1;});
