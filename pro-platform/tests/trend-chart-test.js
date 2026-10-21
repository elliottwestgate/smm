const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element{constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.style={};}append(...nodes){this.children.push(...nodes);}replaceChildren(){this.children=[];}setAttribute(k,v){this.attrs[k]=String(v);} }
const source=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/trends.js','utf8');
const code=source.slice(source.indexOf(' const node='),source.indexOf(' async function load'));
const charts=new Element('div'),status=new Element('p');const document={createElement:tag=>new Element(tag),createElementNS:(ns,tag)=>new Element(tag)};
vm.runInNewContext(code+`;render({dimension:'date',timezone:'America/Chicago',limited:false,points:[{from:'2026-10-01',to:'2026-10-01',sessions:0,users:0,new_users:0,returning_users:0,form_events:0,phone_events:0,goal_rate:null},{from:'2026-10-02',to:'2026-10-02',sessions:100,users:90,new_users:70,returning_users:25,form_events:8,phone_events:2,goal_rate:10}]});`,{charts,status,document,Intl,Date,Math,Set});
const all=[];function walk(n){all.push(n);n.children.forEach(walk);}walk(charts);
assert.equal(all.filter(n=>n.tag==='svg').length,4);
assert(!JSON.stringify(all.map(n=>n.attrs)).match(/NaN|Infinity/));
assert.equal(all.filter(n=>n.tag==='tbody')[0].children.length,2);
assert(status.textContent.includes('Daily'));
console.log('PASS trend charts render finite coordinates, missing rates, readable daily labels and accessible values');
