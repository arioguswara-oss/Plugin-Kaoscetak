'use strict';
// Isolated browser-free regression for buyer image binding and derived preview.
// No WordPress, HTTP, draft storage, buyer data or payment side effects.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../assets/buyer.js'),'utf8');
const start=source.indexOf(' async function exportBoundDraft(){');
const end=source.indexOf(' if(saveButton&&config.draft_api){',start);
assert(start>=0&&end>start,'SANRO buyer image functions missing');
let drawCalls=0,textCalls=0,previewWidth=0,canvasWrites=0;
const d={
  url:'blob:owner-bound',file:null,pending:null,text:'',textColor:'#171717',
  fontSize:22,textX:10,textY:10,imageX:10,imageY:20,imageSize:40
};
const refs={front:'verified-server-bound-id'};
class ImageProbe {
  constructor(){this.naturalWidth=200;this.naturalHeight=200;}
  set src(url){this._src=url;if(this.onload)this.onload();}
  get src(){return this._src;}
}
class FakeReader {
  readAsDataURL(){this.result='data:image/jpeg;base64,cHJldmlldw==';this.onload();}
}
const env={
  sides:{front:d,back:{url:null,text:''}},
  config:{safe_areas:{front:{width:30,height:40}}},
  boundMasterRefs:refs,
  safe:{getBoundingClientRect:()=>({width:200,height:200})},
  shirtColor:{value:'#ffffff'},
  exportTextDraft:()=>({quantities:{M:1},shirt_color:'#ffffff',long_sleeve:false}),
  Image:ImageProbe,FileReader:FakeReader,
  document:{createElement:()=>({width:0,height:0,getContext(){
    return {
      fillRect(){},drawImage(){drawCalls++;},fillText(){textCalls++;},
      toBlob(cb){canvasWrites++;previewWidth=this.canvasWidth||previewWidth;
        // Test resolution fallback: 640px is too large; 480px succeeds.
        cb({size:previewWidth===640?70000:1200});}
    };
  }})}
};
// Track current preview canvas width from the mock context itself.
env.document.createElement=()=>{
 const canvas={width:0,height:0};
 canvas.getContext=()=>({
   fillRect(){},drawImage(){drawCalls++;},fillText(){textCalls++;},
   toBlob(cb){canvasWrites++;previewWidth=canvas.width;
     cb({size:canvas.width===640?70000:1200});}
 });
 return canvas;
};
const built=vm.runInNewContext(source.slice(start,end)+
  '\n({exportBoundDraft,makeDesignPreview})',env,{
    timeout:2000,filename:'sanro-buyer-image-preview.js'
  });
let checks=0;
function check(ok,message){assert(ok,message);checks++;}

(async()=>{
  check(await built.makeDesignPreview('front')==='cHJldmlldw=='
    &&drawCalls>0&&previewWidth===480&&canvasWrites>4,
    'bound image creates a derived JPEG with 50 KiB fallback');
  d.file={name:'new-unbound.png'};
  await assert.rejects(()=>built.exportBoundDraft(),/Unggah gambar baru/);
  checks++; // A replacement must never reuse the old master ID.
  await assert.rejects(()=>built.makeDesignPreview('front'),/Unggah dan simpan master/);
  checks++;
  d.file=null;delete refs.front;
  await assert.rejects(()=>built.exportBoundDraft(),/Master privat/);
  checks++;
  await assert.rejects(()=>built.makeDesignPreview('front'),/Unggah dan simpan master/);
  checks++;
  d.url=null;d.text='SANRO QA';
  check(await built.makeDesignPreview('front')==='cHJldmlldw=='&&textCalls>0,
    'text preview stays supported without a master');
  d.text='';
  check(await built.makeDesignPreview('front')===null,
    'empty side does not create a preview');
  console.log('PASS: '+checks+' private DTF image-preview and master-binding checks');
})().catch(error=>{
  console.error('FAIL private DTF preview regression: '+String(error.message).slice(0,240));
  process.exitCode=1;
});
