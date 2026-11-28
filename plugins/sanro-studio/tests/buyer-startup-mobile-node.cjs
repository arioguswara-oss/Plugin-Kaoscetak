'use strict';
// Execute the actual editor startup and side/color events without a browser,
// network, drafts or synthetic pricing implementation.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Element {
 constructor(){this.style={setProperty(k,v){this[k]=v;}};this.dataset={};this.handlers={};this.attributes={};this.value='';this.hidden=false;this.validity={valid:true};}
 addEventListener(type,handler){this.handlers[type]=handler;}
 setAttribute(k,v){this.attributes[k]=v;}
 getAttribute(k){return this.attributes[k]??null;}
 removeAttribute(k){delete this.attributes[k];}
 getBoundingClientRect(){return {width:184,height:226,left:0,top:0};}
 setCustomValidity(){}
}
const elements=new Map(),sides=['front','back'].map(side=>{const e=new Element();e.dataset.side=side;return e;});
function q(selector){if(selector==='#sanro-save-draft')return null;if(!elements.has(selector))elements.set(selector,new Element());return elements.get(selector);}
q('#sanro-shirt-color').value='#ffffff';
const sizes=['S','L'].map(size=>{const el=new Element();el.dataset.size=size;el.value='0';return el;});
const config={product_id:7,pricing_profile:'cotton_combed_24s',safe_areas:{front:{width:32,height:48},back:{width:32,height:48}},categories:{A5:[14.8,21],A4:[21,29.7],A3:[29.7,42],'A3+':[32,48]},mockups:{front:'https://example.invalid/merchant-front.jpg'}};
const original=JSON.stringify(config.safe_areas);
const root={querySelector:q,querySelectorAll(selector){return selector==='[data-side]'?sides:(selector==='[data-size]'?sizes:[]);},dispatchEvent(){}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/buyer.js','utf8'),{
 document:{getElementById:()=>root},window:{SANRO_STUDIO_EDITOR_CONFIG:config,SanroStudioEstimate:require('../assets/estimate.js'),addEventListener(){}},
 requestAnimationFrame:fn=>fn(),Intl,URL,CustomEvent:function(type,options){this.type=type;this.detail=options?.detail;},console
},{timeout:1000});
const shirt=q('.sanro-shirt');
assert.equal(shirt.attributes['data-side'],'front');
assert.equal(shirt.attributes['data-mockup'],'true');
assert.match(shirt.style.backgroundImage,/merchant-front/);
sides[1].handlers.click();
assert.equal(shirt.attributes['data-side'],'back');
assert.equal(shirt.attributes['data-mockup'],'false');
assert.equal(shirt.style.backgroundImage,'');
q('#sanro-shirt-color').value='#171717';q('#sanro-shirt-color').handlers.change();
assert.equal(shirt.style['--sanro-shirt-color'],'#171717');
assert.equal(sides[1].attributes['aria-pressed'],'true');
sides[0].handlers.click();assert.equal(shirt.attributes['data-mockup'],'true');
assert.equal(JSON.stringify(config.safe_areas),original);
config.color_mockups={'#171717':{front:'https://example.invalid/black-front.jpg',back:'https://example.invalid/black-back.jpg'}};
q('#sanro-shirt-color').handlers.change();assert.match(shirt.style.backgroundImage,/black-front/);
sides[1].handlers.click();assert.match(shirt.style.backgroundImage,/black-back/);
sizes[1].value='3';sizes[1].handlers.input();sizes[0].value='2';sizes[0].handlers.input();
assert.match(shirt.style.backgroundImage,/black-back/);
assert.equal(q('#sanro-qty-total').textContent,'Total: 5 pcs');
q('#sanro-shirt-color').value='#ffffff';q('#sanro-shirt-color').handlers.change();assert.equal(shirt.style.backgroundImage,'');
assert.equal(JSON.stringify(config.safe_areas),original);
const designText=q('.sanro-text'),letterInput=q('#sanro-text-input');
designText.getBoundingClientRect=function(){
 const font=parseFloat(this.style.fontSize);
 return {width:this.textContent.length*font*0.47,height:font*1.15};
};
sides[0].handlers.click();
sizes[0].value='1';sizes[1].value='0';sizes[0].handlers.input();
letterInput.handlers.input({target:{value:'SANRO QA!'}});
assert.match(q('#sanro-price-estimate').textContent,/72[.]000/);
q('#sanro-font-size').handlers.input({target:{value:'12'}});
assert.match(q('#sanro-price-estimate').textContent,/62[.]000/);
q('#sanro-text-rotation').handlers.input({target:{value:'90'}});
assert.match(designText.style.transform,/matrix[(]/);
sides[1].handlers.click();
letterInput.handlers.input({target:{value:'QA'}});
assert.match(q('#sanro-price-estimate').textContent,/77[.]000/);
assert(!elements.has('#sanro-category'),'a tier selector was never requested by buyer JS');
console.log('PASS: buyer startup, canonical mockups, resize/rotation, and automatic side pricing');
