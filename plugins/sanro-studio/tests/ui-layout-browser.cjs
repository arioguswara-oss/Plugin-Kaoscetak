'use strict';
// Local browser layout regression for the revised Studio CSS. Uses representative
// markup only; no WordPress, staging URL, credentials, draft or order data.
const assert=require('node:assert/strict');
const path=require('node:path');
const fs=require('node:fs');
const {chromium}=require('playwright');

const assets=path.resolve(__dirname,'../assets');
const html=`<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body class="sanro-studio-page">
<div id="wpadminbar"></div><div id="adminmenumain"></div><div class="site-header">JurnalFX</div><hr>
<main id="sanro-studio-buyer" class="sanro-buyer sanro-buyer-editor">
 <header class="sanro-appbar"><h1>SANRO Studio</h1></header><h2 class="sanro-editor-product">Kaos Lengan Pendek</h2>
 <nav class="sanro-workflow"><span>1. Pilih Produk</span><span>2. Atur Desain</span><span>3. Desain Saya</span></nav>
 <div class="sanro-mobile-jumps"><a href="#sanro-design-preview">Lihat kaos</a><a href="#sanro-design-controls">Atur desain</a></div>
 <div class="sanro-layout"><section class="sanro-preview" id="sanro-design-preview"><nav><button>Depan</button><button>Belakang</button></nav>
  <div class="sanro-shirt"><div class="sanro-safe"><img alt="Gambar desain" hidden><div class="sanro-text" style="left:20%;top:20%">SANRO</div></div></div>
  <div class="sanro-object-toolbar"><button>Ukuran</button><button>Tengah</button><button>Hapus</button></div>
  <div class="sanro-position-pad"><button>↑</button><button>←</button><button>◎</button><button>→</button><button>↓</button></div></section>
  <section id="sanro-design-controls" class="sanro-controls"><input id="sanro-image" type="file"><section class="sanro-tool-panel"><h2>Produk</h2><label>Warna<select id="sanro-shirt-color"><option value="#fff">Putih</option></select></label><label>Ukuran teks<input id="sanro-font-size" type="range" min="10" max="48" value="22"></label><input id="sanro-image-size" type="range" min="10" max="100" value="40"><button id="sanro-center-text">Tengah</button><button id="sanro-remove-text">Hapus</button></section></section></div>
 <section class="sanro-purchase-bar"><button>Simpan</button><button>Ke Keranjang</button></section>
 <nav class="sanro-bottom-nav"><button>Gambar</button><button>Teks</button><button>Produk</button><button>Desain Saya</button></nav>
 <section class="sanro-collection-view"><h2>Desain Saya</h2><p>Buka kembali atau pesan ulang desainmu</p><fieldset id="sanro-collection">
  <div class="sanro-collection-save"><label>Nama desain<input></label><button class="sanro-collection-primary">Simpan desain ini ke koleksi</button></div>
  <details class="sanro-collection-access"><summary>Akses koleksi dengan email</summary><label>Email<input></label></details>
  <div class="sanro-collection-session-actions"><button>Buka koleksi sesi ini</button></div><div class="sanro-collection-card"><h4>Desain komunitas</h4><img><button>Buka Desain</button><button>Pesan Lagi</button></div>
 </fieldset></section>
</main><footer class="site-footer">Powered by WordPress</footer></body>`;

(async()=>{
 const browser=await chromium.launch({executablePath:process.env.SANRO_TEST_CHROMIUM||undefined,headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
  await page.setContent(html);await page.addStyleTag({path:path.join(assets,'buyer.css')});
  await page.addScriptTag({path:path.join(assets,'studio-ui.js')});
  const buyerSource=fs.readFileSync(path.join(assets,'buyer.js'),'utf8');
  const dragStart=buyerSource.indexOf(' function drag(el,prefix){');
  const dragEnd=buyerSource.indexOf(' // Visual D-pad uses the same bounded geometry',dragStart);
  assert.ok(dragStart>=0&&dragEnd>dragStart,'native buyer drag handler is available');
  await page.evaluate(source=>{
   const text=document.querySelector('.sanro-text'),safe=document.querySelector('.sanro-safe');
   text.hidden=false;
   const sides={front:{textX:20,textY:20},back:{textX:20,textY:20}};
   const install=new Function('el','safe','sides',`let side='front',editRevision=0;const render=()=>{el.style.left=sides[side].textX+'%';el.style.top=sides[side].textY+'%';safe.closest('#sanro-studio-buyer').dispatchEvent(new Event('sanro:render'))};${source};drag(el,'text');return ()=>({x:sides.front.textX,y:sides.front.textY,left:el.style.left,revision:editRevision})`);
   window.__readDragState=install(text,safe,sides);
   document.querySelector('#sanro-font-size').addEventListener('input',event=>{text.style.fontSize=event.target.value+'px'});
  },buyerSource.slice(dragStart,dragEnd));
  assert.equal(await page.locator('.site-header').evaluate(e=>getComputedStyle(e).display),'none','theme header hidden only within Studio body scope');
  for(const width of [390,320]){
   await page.setViewportSize({width,height:844});
   const values=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,app:document.querySelector('#sanro-studio-buyer').getBoundingClientRect().width,shirt:document.querySelector('.sanro-shirt').getBoundingClientRect().width,resize:document.querySelector('.sanro-resize-handle').getBoundingClientRect().width,dpad:document.querySelector('.sanro-position-pad button:nth-child(4)').getBoundingClientRect().width}));
   assert.equal(values.overflow,false,`${width}px portrait buyer must not overflow`);
   assert.ok(values.shirt>=Math.min(width-32,280),`${width}px portrait canvas should remain prominent`);
   assert.ok(values.resize>=44&&values.dpad>=44,'touch controls retain 44px hit targets');
   assert.ok(values.app<=width,'Studio shell fits the viewport');
  }
  await page.setViewportSize({width:390,height:844});
  const textBox=await page.locator('.sanro-text').boundingBox(),beforeDrag=await page.evaluate(()=>window.__readDragState());
  await page.mouse.move(textBox.x+textBox.width/2,textBox.y+textBox.height/2);
  await page.mouse.down();
  await page.mouse.move(textBox.x+textBox.width/2+24,textBox.y+textBox.height/2+8);
  await page.mouse.up();
  const afterDrag=await page.evaluate(()=>window.__readDragState());
  assert.ok(afterDrag.x>beforeDrag.x,'pointer drag moves the print object through native buyer handler');
  const resize=await page.locator('.sanro-resize-handle').boundingBox(),sizeBefore=Number(await page.locator('#sanro-font-size').inputValue());
  await page.mouse.move(resize.x+resize.width/2,resize.y+resize.height/2);
  await page.mouse.down();
  await page.mouse.move(resize.x+resize.width/2+18,resize.y+resize.height/2);
  await page.mouse.up();
  assert.ok(Number(await page.locator('#sanro-font-size').inputValue())>sizeBefore,'pointer resize handle adjusts text size');
  await page.addStyleTag({path:path.join(assets,'studio-admin.css')});
  await page.locator('body').evaluate(e=>e.classList.add('sanro-studio-admin'));
  const admin=await page.evaluate(()=>({wpMenu:getComputedStyle(document.querySelector('#adminmenumain')).display,studio:getComputedStyle(document.querySelector('#sanro-studio-buyer')).display}));
  assert.equal(admin.wpMenu,'none','Studio admin scope removes duplicate WordPress navigation');
  assert.notEqual(admin.studio,'none','Studio admin UI remains visible');
  console.log('PASS: buyer/admin CSS parsed; portrait widths, canvas proportion, theme chrome, touch target sizing, pointer drag and resize verified');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
