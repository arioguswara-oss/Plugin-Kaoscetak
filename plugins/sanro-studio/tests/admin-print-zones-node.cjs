'use strict';
// DOM-light admin preview regression. No WordPress, REST or database mutation.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../assets/admin-print-zones.js'),'utf8');
const listeners={};
const fields={};
for(const [side,zone] of Object.entries({
 front:{x:27,y:22,width:46,height:54},
 back:{x:20,y:20,width:40,height:50}
})){
 for(const [key,value] of Object.entries(zone)){
  const id='_sanro_studio_zone_'+side+'_'+key;
  fields[id]={id,value:String(value)};
 }
 const id='_sanro_studio_mockup_'+side;
 fields[id]={id,value:''};
}
function makeStage(){
 const guide={hidden:true,style:{}};
 const image={hidden:true,src:'',removeAttribute(k){if(k==='src')this.src=''}};
 return {guide,image,querySelector(selector){
  if(selector==='.sanro-printzone-guide')return guide;
  if(selector==='.sanro-printzone-image')return image;
  return null;
 }};
}
const stages={front:makeStage(),back:makeStage()};
const statuses={front:{textContent:''},back:{textContent:''}};
const mockups={
 front:{hidden:false,src:'https://cdn.example.test/front.jpg',
  getAttribute(name){return name==='src'?this.src:null}},
 back:{hidden:true,src:'',getAttribute(name){return name==='src'?this.src:null}}
};
const document={
 readyState:'complete',
 getElementById(id){
  if(id.startsWith('sanro-mockup-preview-'))
   return mockups[id.slice('sanro-mockup-preview-'.length)]||null;
  return fields[id]||null;
 },
 querySelector(selector){
  let match=/^\[data-sanro-printzone-side="(front|back)"\]$/.exec(selector);
  if(match)return stages[match[1]];
  match=/^\[data-sanro-zone-status="(front|back)"\]$/.exec(selector);
  return match?statuses[match[1]]:null;
 },
 addEventListener(event,fn){listeners[event]=fn}
};
vm.runInNewContext(source,{document,window:{location:{href:'https://staging.example.test/wp-admin/post.php'}},URL});
let checks=0;
function check(actual,expected,label){assert.equal(actual,expected,label);checks++}
function edit(id,value,event='input'){
 fields[id].value=String(value);
 listeners[event]({target:fields[id]});
}
check(stages.front.guide.hidden,false,'front preview initialized');
check(stages.front.guide.style.width,'46%','front saved zone visible');
check(stages.back.guide.style.width,'40%','back independent saved zone visible');
check(stages.front.image.src,'https://cdn.example.test/front.jpg','public mockup shown');
check(stages.back.image.hidden,true,'empty back mockup hidden');
edit('_sanro_studio_zone_front_x',10);
check(stages.front.guide.style.left,'10%','front position live updated');
check(stages.back.guide.style.left,'20%','editing front never moves back');
edit('_sanro_studio_zone_front_width',95);
check(stages.front.guide.hidden,true,'overflow hides unsafe guide');
check(statuses.front.textContent.includes('tidak valid'),true,'invalid numbers explained');
edit('_sanro_studio_zone_front_width',46);
check(stages.front.guide.hidden,false,'corrected guide restored');
mockups.back.hidden=false;
mockups.back.src='https://cdn.example.test/back.jpg';
edit('_sanro_studio_mockup_back',42,'change');
check(stages.back.image.src,'https://cdn.example.test/back.jpg','media chooser updates mockup');
mockups.back.hidden=true;
edit('_sanro_studio_mockup_back','', 'change');
check(stages.back.image.hidden,true,'clear media hides mockup');
mockups.back.hidden=false;
mockups.back.src='javascript:alert(1)';
edit('_sanro_studio_mockup_back',99,'change');
check(stages.back.image.hidden,true,'unsafe scheme never shown');
edit('_sanro_studio_zone_back_height','Infinity');
check(stages.back.guide.hidden,true,'infinite zone rejected');
check(statuses.back.textContent.includes('tidak valid'),true,'invalid input status visible');
console.log('PASS: '+checks+' SANRO live admin print-zone preview checks');
