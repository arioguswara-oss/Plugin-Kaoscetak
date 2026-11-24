'use strict';
// Local real Chromium/mobile DOM + canvas; WP transport simulated. No hosting/site/browser session accessed.
const {chromium}=require('playwright'),http=require('node:http'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto'),{execFileSync}=require('node:child_process');
const php=process.env.SANRO_TEST_PHP||'php';
const gd=process.env.SANRO_TEST_GD;
const html=execFileSync(php,[...(gd?['-d','extension='+gd]:[]),path.join(__dirname,'native-buyer-fixture.php')],{encoding:'utf8'});
assert(html.startsWith('<!doctype html>'),html.slice(0,250));const assets=path.resolve(__dirname,'../assets');
let checks=0;const check=(v,label)=>{assert(v,label);checks++;};
const server=http.createServer((req,res)=>{
 if(req.url.startsWith('/assets/')){const file=path.resolve(assets,decodeURIComponent(req.url.split('?')[0].slice(8)));if(!file.startsWith(assets+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'font/ttf');res.end(fs.readFileSync(file));}
 else{res.setHeader('Content-Type','text/html');res.end(html);}
});
(async()=>{
 await new Promise(resolve=>server.listen(18752,'127.0.0.1',resolve));const browser=await chromium.launch({executablePath:process.env.SANRO_TEST_CHROMIUM||undefined,headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage({viewport:{width:390,height:844},deviceScaleFactor:1});const errors=[];page.on('pageerror',e=>errors.push(e.message));let fixture=null,processed=false,preview={},variantPosts=0,collectionSaved=false;
  await page.route('https://example.test/**',async route=>{fixture=fixture||await page.evaluate(()=>window.NATIVE_FIXTURE);await route.fulfill({contentType:'image/png',body:Buffer.from(fixture.original,'base64'),headers:{'Access-Control-Allow-Origin':'*'}});});
  await page.route('**/wp-json/sanro-studio/v1/**',async route=>{
   fixture=fixture||await page.evaluate(()=>window.NATIVE_FIXTURE);const request=route.request(),url=new URL(request.url()),part=url.pathname.replace('/wp-json/sanro-studio/v1',''),body=request.postDataJSON();let value={valid:true};
   const activeRef=processed?fixture.processedRef:fixture.draft.master_refs.front;
   if(part==='/drafts/'+fixture.draft.design_id&&request.method()==='GET')value={...fixture.draft,master_refs:{front:activeRef}};
   else if(part.includes('/masters/')||part.includes('/master-read/'))value={valid:true,file_id:activeRef,mime:'image/png',image_base64:processed?fixture.processed:fixture.original,sha256:activeRef.match(/-([a-f0-9]{64})\.png$/)[1]};
   else if(part.includes('/remove-background/')){processed=!body.restore;value={valid:true,file_id:processed?fixture.processedRef:fixture.draft.master_refs.front};}
   else if(part.includes('/previews/')&&request.method()==='POST'){
    const side=part.includes('/front')?'front':'back';if(part.includes('/variant/'))variantPosts++;
    const data=body.image_base64;value={valid:true,file_id:fixture.draft.design_id+'-'+side+'-'+crypto.createHash('sha256').update(Buffer.from(data,'base64')).digest('hex')+'.jpg',mime:'image/jpeg',bytes:Buffer.from(data,'base64').length};preview[side]={...value,image_base64:data};
   }else if(part.includes('/preview-read/'))value=preview[part.endsWith('/front')?'front':'back'];
   else if(part==='/drafts/'+fixture.draft.design_id+'/collection'){collectionSaved=true;value={valid:true,id:fixture.draft.design_id,collection_token:'c'.repeat(64)};}
   else if(part==='/collection')value={valid:true,entries:[],cursor:null};
   else if(part.endsWith('/share'))value={valid:true,share_token:'d'.repeat(64)};
   else return route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({valid:false,message:'Unexpected fixture request '+part})});
   await route.fulfill({contentType:'application/json',body:JSON.stringify(value)});
  });
  await page.goto('http://127.0.0.1:18752/?sanro_studio_editor=1&product_id=7');
  await page.waitForFunction(()=>document.querySelector('#sanro-save-status').textContent.includes('berhasil dibuka'));
  check((await page.locator('#sanro-shirt-color').inputValue())==='#abcdef','configured palette restored');
  check((await page.locator('.sanro-shirt').evaluate(e=>e.style.backgroundImage)).includes('mockup-11'),'front S mapped mockup');
  await page.selectOption('#sanro-mockup-size','L');check((await page.locator('.sanro-shirt').evaluate(e=>e.style.backgroundImage)).includes('mockup-13'),'preview size switches product mapping');
  await page.locator('button[data-side="back"]').click();check((await page.locator('.sanro-shirt').evaluate(e=>e.style.backgroundImage)).includes('mockup-14'),'back L mapped independently');await page.locator('button[data-side="front"]').click();
  await page.waitForFunction(()=>!document.querySelector('#sanro-remove-background').disabled);await page.click('#sanro-remove-background');await page.waitForFunction(()=>document.querySelector('#sanro-background-status').textContent.includes('Latar polos dihapus'));
  check(processed,'native remove-background UI calls owner-bound endpoint');check(await page.locator('#sanro-remove-background').isDisabled(),'actual processed alpha disables duplicate removal');
  await page.click('#sanro-restore-background');await page.waitForFunction(()=>document.querySelector('#sanro-background-status').textContent.includes('Gambar asli dipulihkan'));check(!processed,'restore UI preserves original');
  await page.locator('[data-title]').fill('Mobile native collection');await page.locator('[data-save]').click();
  await page.waitForFunction(()=>document.querySelector('#sanro-collection [data-status]').textContent.includes('Snapshot tersimpan'),{timeout:15000});
  check(collectionSaved&&variantPosts===4,'collection save first uploads both sides for two active sizes');
  check((await page.evaluate(()=>sessionStorage.getItem('sanro-collection-session')))==='c'.repeat(64),'collection token remains session-scoped');
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),'mobile viewport has no horizontal overflow');
  check(errors.length===0,'no buyer startup/async errors: '+errors.join(';'));
  console.log('PASS: '+checks+' real Chromium mobile/editor/canvas/collection/background/mapping checks (local simulated WP transport)');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>server.close());
