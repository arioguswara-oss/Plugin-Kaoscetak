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
 'const {config,safe,text,sides,categories,qtyInputs,shirtColor,q,boundMasterRefs,Image,document,geometry}=env;const legacyLongSleeve=false;'
 +source.slice(start,middle)+source.slice(source.indexOf(' function validationMessage'),source.indexOf(' function clampPosition'))+source.slice(draftStart,draftEnd)
 +'\nreturn {visualPrintZone,withPrintZone,exportTextDraft,exportBoundDraft,assertPrintSize};');
const initial={left:'27%',top:'22%',width:'46%',height:'54%'};
const layout={hidden:false};
const safe={
 style:{...initial},
 closest(){return layout;},
 getBoundingClientRect(){return layout.hidden ? {width:0,height:0} : {
  width:4*parseFloat(this.style.width),
  height:4.2*parseFloat(this.style.height)
 }}
};
const text={
 textContent:'',hidden:true,style:{fontSize:'22px',left:'10%',top:'10%',transform:''},
 getBoundingClientRect(){return {width:90,height:20}}
};
const side=()=>({
 text:'SANRO',fontSize:22,textX:10,textY:10,
 textColor:'#171717',textRotation:0,imageRotation:0,imageAspect:1,
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
 boundMasterRefs,Image:ImageProbe,geometry:require('../assets/estimate.js'),document:{fonts:{load:async()=>[{}]}}});
let checks=0;
const check=(ok,label)=>{assert(ok,label);checks++};
const equal=(actual,expected,label)=>{
 assert.deepEqual(actual,expected,label);checks++
};
const near=(actual,expected,label)=>{
 assert(Math.abs(actual-expected)<1e-6,label);checks++
};
async function checkInitialImageSave(){
 const from=source.indexOf("  saveButton.addEventListener('click',async()=>{");
 const to=source.indexOf("\n }\n image.addEventListener('load'",from);
 assert.ok(from>=0&&to>from);
 const saveStatus={replaceChildren(message){this.textContent=message;},append(button){this.next=button;}};
 const button={disabled:false,addEventListener(_event,handler){this.click=handler;}};
 const calls=[];
 const hash='a'.repeat(64);
 const factory=new Function('env',`
  const {saveButton,saveStatus,fetch,document}=env;
  const config={product_id:53,master_upload_enabled:true,draft_api:{url:'/drafts',nonce:'test'}};
  const sides={front:{file:{},url:'blob:local'},back:{}},boundMasterRefs={};
  let saving=false,uploadingMaster=false,savedId=null,ownerToken=null,editRevision=2,savedRevision=0,lastKnownDesignHash=null;
  const ensureProductionFont=async()=>{};
  const exportTextDraft=(ignoreImages,allowEmpty)=>({ignoreImages,allowEmpty});
  const sessionStorage={setItem(){}},draftStorageKey='test';
  ${source.slice(from,to)}
  return ()=>({savedRevision,lastKnownDesignHash});`);
 const saved=factory({saveButton:button,saveStatus,document:{
  createTextNode:message=>message,createElement:()=>({addEventListener(){}})
 },async fetch(url,options){
  calls.push({url,options});
  return {ok:true,json:async()=>calls.length===1
   ?{valid:true,design_id:'synthetic',owner_token:'fixture'}
   :{valid:true,design_hash:hash}};
 }});
 await button.click();
 equal(calls.length,2,'save sends one draft request and verifies readback');
 equal(JSON.parse(calls[0].options.body).design,{ignoreImages:true,allowEmpty:true},
  'image draft bootstraps with metadata only');
 check(saveStatus.textContent.includes('Draft tersimpan dan terverifikasi'),
  'image save displays verified success instead of a scope error');
 equal(saveStatus.next.textContent,'Lanjut unggah gambar','image save offers the next action');
 equal(saved(),{savedRevision:2,lastKnownDesignHash:hash},'save retains the verified revision');
}
(async()=>{
 const result=runtime.exportTextDraft();
 near(result.front.objects[0].width,90*30/(390*0.46),'front text uses 46 percent front width');
 near(result.back.objects[0].width,90*30/(390*0.30),'back text uses 30 percent back width');
 check(result.front.objects[0].width<result.back.objects[0].width,
  'different visual zones have independent centimeter measurements');
 equal(safe.style,initial,'export restores currently visible print zone');
 equal(text.style,{fontSize:'22px',left:'10%',top:'10%',transform:''},
  'export restores visible text editor styles');
 runtime.withPrintZone('back',()=>{
  equal(safe.style.width,'30%','inactive zone is applied for measurement');
 });
 equal(safe.style,initial,'temporary back measurement restores visible front');
 assert.throws(()=>runtime.withPrintZone('back',()=>{throw new Error('layout')}),
  /layout/);
 checks++;
 equal(safe.style,initial,'failed layout measurement restores current side');
 layout.hidden=true;
 check(runtime.withPrintZone('back',()=>safe.getBoundingClientRect().width)>0,
  'preview measures the layout while Desain Saya is open');
 check(layout.hidden,'preview measurement restores the hidden editor');
 assert.throws(()=>runtime.withPrintZone('back',()=>{throw new Error('layout')}),
  /layout/);
 check(layout.hidden,'failed preview measurement also restores the hidden editor');
 layout.hidden=false;
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
 near(frontImg.height,12,'front master uses physical image aspect');
 near(backImg.height,12,'back master uses the same physical image aspect');
 check(!('category' in bound.front)&&!('category' in bound.back),'draft sends geometry without a price category');
 assert.throws(()=>runtime.assertPrintSize('front',[
  {x:0,y:0,width:12,height:12},{x:28,y:0,width:6,height:6}
 ]),/melewati area cetak/);
 checks++;
 equal(safe.style,initial,'master export restores active zone');
 delete boundMasterRefs.back;
 await assert.rejects(()=>runtime.exportBoundDraft(),/Master privat/);
 checks++;
 equal(safe.style,initial,'master authorization failure preserves active zone');
 check(source.includes('const bounds=withPrintZone(name,()=>safe.getBoundingClientRect());'),
  'derived preview also selects correct side');
 await checkInitialImageSave();
 console.log('PASS: '+checks+' side-specific print-zone draft geometry checks');
})().catch(err=>{
 console.error('FAIL: side-specific zone draft geometry: '+err.message);
 process.exitCode=1;
});
