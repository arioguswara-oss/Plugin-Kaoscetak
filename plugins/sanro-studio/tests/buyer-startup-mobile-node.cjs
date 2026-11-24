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
q('#sanro-shirt-color').value='#ffffff';q('#sanro-sleeve').value='short';
const config={product_id:7,pricing_profile:'cotton_combed_24s',safe_areas:{front:{width:32,height:48},back:{width:32,height:48}},categories:{A5:[14.8,21],A4:[21,29.7],A3:[29.7,42],'A3+':[32,48]},mockups:{front:'https://example.invalid/merchant-front.jpg'}};
const original=JSON.stringify(config.safe_areas);
const root={querySelector:q,querySelectorAll(selector){return selector==='[data-side]'?sides:[];}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/buyer.js','utf8'),{
 document:{getElementById:()=>root},window:{SANRO_STUDIO_EDITOR_CONFIG:config,SanroStudioEstimate:require('../assets/estimate.js'),addEventListener(){}},
 requestAnimationFrame:fn=>fn(),Intl,URL,console
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
config.mockup_matrix={'#171717':{S:{front:'https://example.invalid/black-s-front.jpg',back:'https://example.invalid/black-s-back.jpg'},L:{front:'https://example.invalid/black-l-front.jpg',back:'https://example.invalid/black-l-back.jpg'}}};
q('#sanro-mockup-size').value='L';q('#sanro-mockup-size').handlers.change();assert.match(shirt.style.backgroundImage,/black-l-front/);
sides[1].handlers.click();assert.match(shirt.style.backgroundImage,/black-l-back/);
q('#sanro-mockup-size').value='S';q('#sanro-mockup-size').handlers.change();assert.match(shirt.style.backgroundImage,/black-s-back/);
q('#sanro-shirt-color').value='#ffffff';q('#sanro-shirt-color').handlers.change();assert.equal(shirt.style.backgroundImage,'');
assert.equal(JSON.stringify(config.safe_areas),original);
console.log('PASS: 15 actual buyer startup, legacy/variant size/side/color mapping and immutable print-area assertions');
