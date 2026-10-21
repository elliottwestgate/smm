const fs=require('fs'),vm=require('vm'),assert=require('assert');
function fixture(){
 const replacements=[],removed=[],clonedImages=[{alt:'🏁'},{alt:'🔥'}];
 clonedImages.forEach(n=>n.replaceWith=t=>replacements.push(t.textContent));
 const details=[{open:false}],copy={hidden:true,append(){},querySelectorAll(selector){if(selector==='img.emoji,img.wp-smiley')return clonedImages;if(selector==='details')return details;return [];},querySelector(selector){return {remove(){removed.push(selector);}};}};
 const styles=[],body=[],links=[],printCalls=[];
 const doc={title:'',createTextNode(text){return {textContent:text};},createElement(tag){return {tagName:tag,append(){},prepend(){}};},body:{append(n){body.push(n);},prepend(n){body.unshift(n);}},head:{append(n){if(n.tagName==='style')styles.push(n.textContent);if(n.tagName==='link'){links.push(n);n.onload();}}}};
 const popup={document:doc,focus(){},print(){printCalls.push(true);}};
 return {copy,replacements,removed,details,styles,body,links,printCalls,popup};
}
const window={WGPPPrintReady:(popup,title)=>{assert(title);popup.print();},WGPPExportName:()=> 'V1Moto_Message-Magnet-Performance_timestamp'};
const config={styles:'https://example.com/analytics.css'};
const el=()=>({});
const reportSource=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/analytics.js','utf8');
const begin=reportSource.indexOf("root.querySelector('[data-print]').addEventListener('click',()=>{");
const reportHandler=reportSource.slice(begin,reportSource.indexOf('\nfunction syncDates()',begin));
let report=fixture(),reportClick;
vm.runInNewContext(reportHandler,{root:{cloneNode:()=>report.copy,querySelector:()=>({addEventListener(type,fn){reportClick=fn;}})},state:{analysis:{},snapshot:{company:'V1Moto'}},report:{hidden:false},printReady:true,window:{...window,open:()=>report.popup},status:{},config,el,Intl,Date});
reportClick();
assert.deepEqual(report.replacements,['🏁','🔥']);assert.equal(report.printCalls.length,1);assert(report.details[0].open);assert(report.styles[0].includes('max-height:250px'));assert(report.styles[0].includes('max-height:160px'));assert.equal(report.popup.opener,null);
console.log('PASS Report export replaces page-sized emoji images with their original text and bounds graphics');
const reviewSource=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/reviews.js','utf8');
const start=reviewSource.indexOf('   print.onclick=()=>{'),end=reviewSource.indexOf('\n   saved.hidden=false',start);
for(const support of [false,true]){
 const f=fixture(),button={};vm.runInNewContext(reviewSource.slice(start,end),{print:button,window:{...window,open:()=>f.popup},status:{},context:{company:'V1Moto'},snap:{},saved:{cloneNode:()=>f.copy},config,includeSupport:{checked:support},Date});button.onclick();
 assert.deepEqual(f.replacements,['🏁','🔥']);assert.equal(f.printCalls.length,1);assert.equal(f.copy.hidden,false);assert.equal(f.removed.includes('.wgpa-review-appendix'),!support);assert(f.styles[0].includes('break-inside:auto'));assert(f.styles[0].includes('height:250px!important'));assert.equal(f.popup.opener,null);
}
console.log('PASS Review export preserves supporting-results option, keeps emoji text and allows long sections to paginate');
