'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let selected = {id:41,type:'image',sizes:{thumbnail:{url:'https://cdn.example.test/front-41.jpg'}}};
let listener;
const fields = {
  _sanro_studio_mockup_front:{value:'',changes:0,dispatchEvent(){this.changes++}},
  _sanro_studio_mockup_back:{value:'',changes:0,dispatchEvent(){this.changes++}}
};
const previews = {
  'sanro-mockup-preview-front':{src:'',hidden:true,removeAttribute(key){if(key==='src')this.src=''}},
  'sanro-mockup-preview-back':{src:'',hidden:true,removeAttribute(key){if(key==='src')this.src=''}}
};
let calls=0;
const media = () => ({
  on(name,handler){if(name==='select')this.selectionHandler=handler},
  state(){return {get:()=>({first:()=>({toJSON:()=>selected})})}},
  open(){calls++;this.selectionHandler()}
});
const document={
  addEventListener(name,fn){if(name==='click')listener=fn},
  getElementById(name){return fields[name]||previews[name]||null}
};
const sandbox={
  window:{wp:{media},location:{href:'https://staging.example.test/wp-admin/post.php'}},
  document,wp:{media},Event:class Event{constructor(type,opts){this.type=type;this.bubbles=opts.bubbles}},
  URL,Set
};
const code=fs.readFileSync(path.join(__dirname,'../assets/admin-mockups.js'),'utf8');
vm.runInNewContext(code,sandbox);
let checks=0;
function check(actual,expected,message){assert.equal(actual,expected,message);checks++}
function click(side,clear=false){
  const button={dataset:{sanroSide:side}};
  let prevented=false;
  const event={
    target:{closest(selector){
      if(clear&&selector==='.sanro-clear-mockup')return button;
      if(!clear&&selector==='.sanro-choose-mockup')return button;
      return null;
    }},
    preventDefault(){prevented=true}
  };
  listener(event);
  return prevented;
}
check(click('front'),true,'front chooser intercepts native form click');
check(fields._sanro_studio_mockup_front.value,'41','front media ID applied');
check(previews['sanro-mockup-preview-front'].hidden,false,'front preview visible');
check(previews['sanro-mockup-preview-front'].src,'https://cdn.example.test/front-41.jpg','front thumbnail source');
selected={id:42,type:'image',sizes:{medium:{url:'https://cdn.example.test/back-42.jpg'}}};
check(click('back'),true,'back chooser works');
check(fields._sanro_studio_mockup_back.value,'42','back media ID independent');
check(previews['sanro-mockup-preview-back'].src,'https://cdn.example.test/back-42.jpg','fallback to medium thumbnail');
check(click('front',true),true,'front clear works');
check(fields._sanro_studio_mockup_front.value,'','front field removed');
check(previews['sanro-mockup-preview-front'].hidden,true,'front preview hidden');
check(previews['sanro-mockup-preview-front'].src,'','front image source cleared');
check(fields._sanro_studio_mockup_back.value,'42','front clear never clears back');
selected={id:43,type:'image',url:'javascript:alert(1)',sizes:{}};
click('front');
check(previews['sanro-mockup-preview-front'].hidden,true,'unsafe URL is not previewed');
check(click('invalid-side'),false,'unknown media side ignored');
check(calls,3,'only valid choose actions open Media Library');
console.log('PASS: '+checks+' SANRO product mockup chooser/preview checks');
