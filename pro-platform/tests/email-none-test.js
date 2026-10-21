const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element{constructor(tag,text=''){this.tag=tag;this.textContent=text;this.children=[];this.attrs={};}append(...n){this.children.push(...n);}replaceChildren(){this.children=[];}setAttribute(k,v){this.attrs[k]=String(v);}}
const source=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/email.js','utf8');
const code=source.slice(source.indexOf(' const node='),source.indexOf(' async function load'));
const results=new Element('div'),status=new Element('p'),document={createElement:tag=>new Element(tag),createElementNS:(ns,tag)=>new Element(tag)};
const metrics={sends:100,delivered:98,opens:30,clicks:5,bounces:2,optouts:1,open_rate:30,click_rate:5};
vm.runInNewContext('let compare=true;'+code+';render(data);',{data:{status:'complete',ranges:{current:['2026-09-17','2026-10-16'],comparison:null},timezone:'America/Chicago',collected_at:'2026-10-18',current:{metrics,campaigns:[{}]},comparison:null},root:{},results,status,document,Intl,Date,Math});
const all=[];function walk(n){all.push(n);n.children.forEach(walk);}walk(results);
assert.equal(all.find(n=>n.tag==='thead').children[0].children.length,2);
assert.equal(all.filter(n=>n.tag==='svg').length,2);
assert(!all.some(n=>n.textContent==='Comparison period'));
assert(status.textContent.includes('Comparison off'));
console.log('PASS email current-only report renders without comparison rows, bars or changes');
