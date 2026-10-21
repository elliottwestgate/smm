const fs=require('fs'),vm=require('vm'),assert=require('assert');
const input={type:'password'};let click;const button={textContent:'Show password',attributes:{},addEventListener:(event,fn)=>{click=fn;},closest:()=>({querySelector:()=>input}),setAttribute(k,v){this.attributes[k]=v;}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/account.js','utf8'),{document:{querySelectorAll:()=>[button]}});
click();assert.equal(input.type,'text');assert.equal(button.textContent,'Hide password');assert.equal(button.attributes['aria-pressed'],'true');
click();assert.equal(input.type,'password');assert.equal(button.textContent,'Show password');assert.equal(button.attributes['aria-pressed'],'false');
console.log('PASS Show/Hide password toggles input and accessible state');
