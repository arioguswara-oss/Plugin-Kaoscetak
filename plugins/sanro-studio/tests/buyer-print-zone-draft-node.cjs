'use strict';
// Pure layout regression: no WordPress, HTTP, WooCommerce order, or DB writes.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../assets/buyer.js'),'utf8');
const start=source.indexOf(' function visualPrintZone(name){');
const middle=source.indexOf(' function render(){',start);
const draftStart=source.indexOf(' function exportTextDraft(ignoreImages=false,allowEmpty=false){');
const draftEnd=source.indexOf(' // Derived private JPEG preview',draftStart);
assert(start>=0&&middle>start&&draftStart>middle&&draftEnd>draftStart,
 'Source geometry/draft functions must be present');
const factory=new Function('env',
 'const {config,safe,text,sides,categories,qtyInputs,shirtColor,q,boundMasterRefs,Image,document}=env;'
 +source.slice(start,middle)+source.slice(draftStart,draftEnd)
 +'\nreturn {visualPrintZone,withPrintZone,exportTextDraft,exportBoundDraft};');
const initial={left:'27%',top:'22%',width:'46%',height:'54%'};
const safe={
 style:{...initial},
 getBoundingClientRect(){return {
  width:4*parseFloat(this.style.width),
  height:4.2*parseFloat(this.style.height)
 }}
};
const text={
 textContent:'',hidden:true,style:{fontSize:'22px',left:'10%',top:'10%'},
 getBoundingClientRect(){return {width:90,height:20}}
};
const side=()=>({
 text:'SANRO',fontSize:22,textX:10,textY:10,
 textColor:'#171717',category:'A3',
 url:null,file:null,pending:null,imageSize:40,imageX:5,imageY:5
});
const sides={front:side(),back:side()};
const zones={
 front:{x:27,y:22,width:46,height:54},
 back:{x:25,y:25,width:30,height:50}
};
const config={
 safe_areas:{front:{width:30,height:40},back:{width:30,height:40}},
 print_zones:zones
};
const categories={A3:[30,40]};
const boundMasterRefs={front:'owner-bound-front',back:'owner-bound-back'};
class ImageProbe{
 constructor(){this.naturalWidth=200;this.naturalHeight=200}
 set src(value){this._src=value;this.onload&&this.onload()}
 get src(){return this._src}
}
const runtime=factory({config,safe,text,sides,categories,
 qtyInputs:[{dataset:{size:'M'},value:'1'}],
 shirtColor:{value:'#ffffff'},q:()=>({value:'short'}),
 boundMasterRefs,Image:ImageProbe,document:{fonts:{load:async()=>[{}]}}});
let checks=0;
const check=(ok,label)=>{assert(ok,label);checks++};
const equal=(actual,expected,label)=>{
 assert.deepEqual(actual,expected,label);checks++
};
const near=(actual,expected,label)=>{
 assert(Math.abs(actual-expected)<1e-6,label);checks++
};
(async()=>{
 const result=runtime.exportTextDraft();
 near(result.front.objects[0].width,90*30/184,'front text uses 46 percent front width');
 near(result.back.objects[0].width,90*30/120,'back text uses 30 percent back width');
 check(result.front.objects[0].width<result.back.objects[0].width,
  'different visual zones have independent centimeter measurements');
 equal(safe.style,initial,'export restores currently visible print zone');
 equal(text.style,{fontSize:'22px',left:'10%',top:'10%'},
  'export restores visible text editor styles');
 runtime.withPrintZone('back',()=>{
  equal(safe.style.width,'30%','inactive zone is applied for measurement');
 });
 equal(safe.style,initial,'temporary back measurement restores visible front');
 assert.throws(()=>runtime.withPrintZone('back',()=>{throw new Error('layout')}),
  /layout/);
 checks++;
 equal(safe.style,initial,'failed layout measurement restores current side');
 const invalid={...config.print_zones.back};
 config.print_zones.back={x:80,y:20,width:40,height:50};
 equal(runtime.visualPrintZone('back'),
  {x:27,y:22,width:46,height:54},
  'invalid zone safely uses legacy default');
 config.print_zones.back=invalid;
 config.print_zones={};
 equal(runtime.visualPrintZone('back'),
  {x:27,y:22,width:46,height:54},
  'legacy product without zone uses prior default');
 config.print_zones=zones;
 sides.front.url='blob:front';sides.back.url='blob:back';
 const bound=await runtime.exportBoundDraft();
 const frontImg=bound.front.objects.find(obj=>obj.type==='image');
 const backImg=bound.back.objects.find(obj=>obj.type==='image');
 near(frontImg.height,12*(184/(54*4.2))*(40/30),
  'front master geometry uses front preview aspect');
 near(backImg.height,12*(120/(50*4.2))*(40/30),
  'back master geometry uses back preview aspect');
 check(frontImg.height>backImg.height,'front and back image heights differ appropriately');
 equal(safe.style,initial,'master export restores active zone');
 delete boundMasterRefs.back;
 await assert.rejects(()=>runtime.exportBoundDraft(),/Master privat/);
 checks++;
 equal(safe.style,initial,'master authorization failure preserves active zone');
 check(source.includes('const bounds=withPrintZone(name,()=>safe.getBoundingClientRect());'),
  'derived preview also selects correct side');
 console.log('PASS: '+checks+' side-specific print-zone draft geometry checks');
})().catch(err=>{
 console.error('FAIL: side-specific zone draft geometry: '+err.message);
 process.exitCode=1;
});
