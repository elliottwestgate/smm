const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../plugin/westgate-practicum-pro/assets/analytics.js','utf8');
const sync=source.slice(source.indexOf('function syncDates(){'),source.indexOf("form.addEventListener('submit'"));
const form={elements:{preset:{value:'90'},start:{value:'2026-09-17'},end:{value:'2026-10-16'}}};
vm.runInNewContext(sync+';syncDates();',{form,Date,Number});
assert.equal(form.elements.start.value,'2026-07-19');assert.equal(form.elements.start.readOnly,true);
form.elements.preset.value='30';form.elements.end.value='2024-03-01';
vm.runInNewContext(sync+';syncDates();',{form,Date,Number});assert.equal(form.elements.start.value,'2024-02-01');
form.elements.preset.value='custom';form.elements.start.value='2024-01-03';
vm.runInNewContext(sync+';syncDates();',{form,Date,Number});assert.equal(form.elements.start.value,'2024-01-03');assert.equal(form.elements.start.readOnly,false);
console.log('PASS 90-day visible dates, leap-year calendar arithmetic, editable custom dates');

// Company switching adds a form before the date controls. Initialize the real binding.
const dateForm={elements:{preset:{value:'30'},start:{value:'2026-09-19'},end:{value:'2026-10-18'},comparison:{value:'prior'}}};
const companyForm={elements:{company_id:{value:'8841'}}};
const initialization=source.match(/const config=JSON\.parse\(root\.dataset\.config\),form=.*?;/)[0];
const bound=vm.runInNewContext('(()=>{'+initialization+'return form;})()',{root:{dataset:{config:'{}'},querySelector(selector){return selector==='.wgpa-controls'?dateForm:selector==='form'?companyForm:{};}}});
assert.equal(bound,dateForm,'Date changes bind to reporting controls even when a company form comes first');
