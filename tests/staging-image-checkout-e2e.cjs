'use strict';
/**
 * Staging-only buyer DTF image integration E2E. Explicit opt-in REQUIRED.
 * Synthetic front/back PNG → private masters → bound draft → reopen →
 * optional private previews → authoritative server quote → Cart → Checkout.
 * Never submits an order, places a payment or touches production.
 * The test may create ONE private staging QA draft and add a staging cart item.
 */
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const {readPrivateDraft,savePrivateDraft} = require('./staging-image-private-draft.cjs');

if (process.env.SANRO_STAGING_IMAGE_E2E !== '1') {
  console.error('BLOCKED: set SANRO_STAGING_IMAGE_E2E=1 to authorize one synthetic staging image flow.');
  process.exit(2);
}
if (process.env.SANRO_STAGING_CART_E2E === '1' ||
    process.env.SANRO_STAGING_MOBILE_QA === '1') {
  console.error('BLOCKED: image E2E must not be combined with other write/read-only QA modes.');
  process.exit(2);
}
const reuseRequested=process.env.SANRO_STAGING_IMAGE_REUSE==='1';
if (process.env.SANRO_STAGING_IMAGE_REUSE &&
    !['0','1'].includes(process.env.SANRO_STAGING_IMAGE_REUSE)) {
  console.error('BLOCKED: SANRO_STAGING_IMAGE_REUSE must be 1 or unset.');
  process.exit(2);
}
const host = 'saddlebrown-peafowl-616656.hostingersite.com';
const editorUrl = 'https://'+host+'/custom-2/?sanro_studio_editor=1&product_id=24';
const sourceHash = crypto.createHash('sha256').update(fs.readFileSync(
  path.join(__dirname,'../plugins/sanro-studio/assets/buyer.js'))).digest('hex');
let chromium;
try { ({chromium} = require('playwright')); }
catch (_) {
  console.error('BLOCKED: Playwright missing from existing Hermes QA environment.');
  process.exit(2);
}

(async () => {
  const launch={headless:true};
  if(process.env.SANRO_CHROMIUM_EXECUTABLE)
    launch.executablePath=process.env.SANRO_CHROMIUM_EXECUTABLE;
  const browser=await chromium.launch(launch);
  try {
    const context=await browser.newContext({
      viewport:{width:390,height:844},acceptDownloads:false,locale:'id-ID'
    });
    if(reuseRequested){
      // Credentials remain in owner-only Hermes file and browser session.
      // This mode does not issue POST /drafts, preserving the guest rate limit.
      const saved=readPrivateDraft();
      await context.addInitScript(credentials=>{
        if(location.hostname==='saddlebrown-peafowl-616656.hostingersite.com'){
          sessionStorage.setItem('sanro-studio-draft:24',
            JSON.stringify({id:credentials.id,token:credentials.token}));
        }
      },saved);
    }
    try {
      const page=await context.newPage();
      // Prevent accidental external navigation; the expected flow only visits
      // the owner's already approved Hostinger staging site.
      const response=await page.goto(editorUrl,{waitUntil:'domcontentloaded',timeout:30000});
      assert(response && response.status()===200,'Staging editor did not load');
      await page.locator('#sanro-save-draft').waitFor({state:'visible',timeout:15000});
      const flags=await page.evaluate(()=>{
        const cfg=window.SANRO_STUDIO_EDITOR_CONFIG||{};
        return {product:cfg.product_id,master:cfg.master_upload_enabled===true,
          preview:cfg.preview_upload_enabled===true,
          cart:!!document.querySelector('#sanro-add-to-cart'),
          draft:!!cfg.draft_api?.url};
      });
      assert.equal(flags.product,24,'Wrong staging test product');
      // Expose only public boolean feature gates, never owner tokens,
      // nonces, draft identifiers, URLs, storage paths or server config.
      const gateStatus = {
        master:flags.master===true,
        draft:flags.draft===true,
        cart:flags.cart===true,
        previewOptional:flags.preview===true
      };
      console.log('STAGING DTF GATES: '+Object.entries(gateStatus)
        .map(([key,enabled])=>key+'='+(enabled?'ON':'OFF')).join(' '));
      const required=Object.entries(gateStatus)
        .filter(([key,enabled])=>key!=='previewOptional'&&!enabled)
        .map(([key])=>key);
      if(required.length){
        console.error('BLOCKED: required DTF gates OFF: '+required.join(', ')+
          '. No draft or cart mutation. Private master gate requires '+
          'staging-only server constants and a protected storage directory outside web root.');
        process.exitCode=2;return;
      }
      const servedJs=await page.evaluate(async () => {
        const url=[...document.querySelectorAll('script[src]')]
          .map(x=>x.src).find(x=>{
            try {return new URL(x).pathname==='/wp-content/plugins/sanro-studio/assets/buyer.js'}
            catch (_){return false}
          });
        if(!url)return null;
        const res=await fetch(url,{credentials:'same-origin',cache:'no-store'});
        if(!res.ok)return null;
        const bytes=await res.arrayBuffer();
        return [...new Uint8Array(bytes)];
      });
      assert(Array.isArray(servedJs),'Buyer JS staging asset unavailable');
      assert.equal(crypto.createHash('sha256').update(Buffer.from(servedJs)).digest('hex'),
        sourceHash,'Staging buyer asset differs from image QA source');
      console.log('PASS: staging image E2E source asset verified');
      if(reuseRequested){
        await page.waitForFunction(()=>
          (document.querySelector('#sanro-save-status')?.textContent||'')
            .includes('Draft berhasil dibuka kembali.'),
          null,{timeout:45000});
        console.log('PASS: owner-bound private DTF QA draft reopened (no new draft POST)');
      }

      // Generate synthetic, non-customer artwork directly inside the isolated
      // browser. No personal artwork, local uploads or order data are used.
      const images=await page.evaluate(()=>{
        return ['front','back'].map((side,i)=>{
          const canvas=document.createElement('canvas');
          canvas.width=240;canvas.height=240;
          const ctx=canvas.getContext('2d');
          ctx.fillStyle=i?'#123e66':'#c83146';ctx.fillRect(0,0,240,240);
          ctx.fillStyle='#ffffff';
          for(let n=0;n<6;n++)ctx.fillRect(15+n*15,25+n*11,70,12);
          ctx.fillStyle='#f8dc47';ctx.fillRect(35,150,160,38);
          return {side,base64:canvas.toDataURL('image/png').split(',')[1]};
        });
      });
      await page.locator('input[data-size="M"]').fill('1');
      for (const {side,base64} of images) {
        await page.locator('#sanro-studio-buyer button[data-side="'+side+'"]').click();
        await page.locator('#sanro-category').selectOption('A3+');
        await page.locator('#sanro-image').setInputFiles({
          name:'sanro-qa-'+side+'.png',mimeType:'image/png',
          buffer:Buffer.from(base64,'base64')
        });
        await page.waitForFunction(()=>
          /Pratinjau sesuai batas|Di luar batas cetak|Gambar tidak valid/i.test(
            document.querySelector('#sanro-validation')?.textContent||''),
          null,{timeout:12000});
        const geometry=(await page.locator('#sanro-validation').innerText()).trim();
        assert.match(geometry,/Pratinjau sesuai batas/,
          'Synthetic '+side+' image outside authorized print area');
        console.log('PASS: '+side+' synthetic DTF image fits configured print area');
      }

      // Collect only HTTP status and the allowlisted public WP error code.
      // Never print response bodies, draft identifiers, owner tokens or nonces.
      if(!reuseRequested) {
      const draftCreates=[];
      const draftReads=[];
      const onDraftCreateResponse=response=>{
        let url;
        try { url=new URL(response.url()); } catch (_) { return; }
        if(url.hostname!==host||response.request().method()!=='POST')return;
        const route=url.searchParams.get('rest_route');
        if(!url.pathname.replace(/\/$/,'').endsWith('/wp-json/sanro-studio/v1/drafts')
          && route!=='/sanro-studio/v1/drafts')return;
        const item={http:response.status(),code:'ok'};
        draftCreates.push(item);
        draftReads.push((async()=>{
          if(response.ok())return;
          try {
            const payload=await response.json();
            item.code=typeof payload?.code==='string'
              && /^sanro_[a-z0-9_]{1,70}$/.test(payload.code)
              ?payload.code:'http_error';
          }catch(_){item.code='non_json_error';}
        })());
      };
      page.on('response',onDraftCreateResponse);
      await page.locator('#sanro-save-draft').click();
      await page.waitForFunction(()=>{
        const b=document.querySelector('#sanro-save-draft');
        const s=document.querySelector('#sanro-save-status')?.textContent||'';
        return b && !b.disabled && !s.includes('Menyimpan metadata');
      },null,{timeout:30000});
      const draftStatus=await page.locator('#sanro-save-status').innerText();
      await Promise.all(draftReads);
      page.off('response',onDraftCreateResponse);
      const draftOutcome=/^Draft teks tersimpan/.test(draftStatus)?'saved'
        :/^Penyimpanan gagal \(\d{3}\)\.$/.test(draftStatus)?'http_error'
        :'other';
      console.log('DTF DRAFT PHASE: '+JSON.stringify({ui:draftOutcome,
        responses:draftCreates.map(item=>({http:item.http,code:item.code}))}));
      if(draftCreates.some(item=>item.http===429
         && item.code==='sanro_rate_limited')){
        console.error('BLOCKED: hourly guest draft-creation limit reached on staging. '+
          'Do not retry until normal expiration; server rate limit remains enabled.');
        process.exitCode=2;
        return;
      }
      assert.match(draftStatus,/Draft teks tersimpan/,
        'Image bootstrap draft creation failed; see DTF DRAFT PHASE');
      // Save only the owner-bound QA draft credentials to a local, owner-only
      // file. It enables a subsequent explicit reuse without more draft POSTs.
      // Never print credentials, JSON, draft URLs or identifiers.
      const privateCredentials=await page.evaluate(()=>{
        try {return JSON.parse(sessionStorage.getItem('sanro-studio-draft:24')||'null')}
        catch (_) {return null}
      });
      savePrivateDraft(privateCredentials||{});
      console.log('PASS: owner-bound private image-only bootstrap draft');
      console.log('PASS: private staging DTF QA draft saved in 0600 Hermes file');
      } // End optional new-draft path; reuse skips creation entirely.

      // Capture only HTTP status, side, method and allowlisted WP error codes.
      // Do NOT record request headers, draft IDs, tokens, image bytes or REST bodies.
      const masterHttp = [];
      const masterReads = [];
      const onMasterResponse = response => {
        const request=response.request();
        let url;
        try { url=new URL(response.url()); } catch (_) { return; }
        if (url.hostname!==host) return;
        const match=url.pathname.match(
          /\/wp-json\/sanro-studio\/v1\/drafts\/[a-f0-9-]{36}\/(?:masters\/(front|back)(?:\/[^/]+)?|master-read\/(front|back))$/i);
        if(!match) return;
        const contentType=response.headers()['content-type']||'';
        const kind=/\bjson\b/i.test(contentType)?'json'
          :/\bhtml\b/i.test(contentType)?'html'
          :/\btext\b/i.test(contentType)?'text':'other';
        const row={side:match[1]||match[2],method:request.method(),
          http:response.status(),contentType:kind,code:'ok'};
        masterHttp.push(row);
        masterReads.push((async()=>{
          if(response.ok())return;
          try {
            const payload=await response.json();
            row.code=(typeof payload?.code==='string'&&/^sanro_[a-z0-9_]{1,70}$/.test(payload.code))
              ?payload.code:'http_error';
          } catch (_) {row.code='non_json_error';}
        })());
      };
      page.on('response',onMasterResponse);
      await page.locator('#sanro-upload-master').click();
      await page.waitForFunction(()=>{
        const b=document.querySelector('#sanro-upload-master');
        const s=document.querySelector('#sanro-master-upload-status')?.textContent||'';
        return b && !b.disabled && s && !s.includes('Mengunggah master privat');
      },null,{timeout:75000});
      const masterStatus=await page.locator('#sanro-master-upload-status').innerText();
      await Promise.all(masterReads);
      page.off('response',onMasterResponse);
      const safeUiStatus=/^(?:Upload master (?:front|back) gagal \(\d{3}\)|Verifikasi master (?:front|back) gagal \(\d{3}\)|Master terunggah, tetapi metadata gambar belum tersimpan \(\d{3}\)|Metadata teks, jumlah, atau warna berubah\. Simpan draft teks sebelum upload master\.|Master dan posisi gambar terverifikasi untuk front, back.*)$/
        .test(masterStatus)?masterStatus.slice(0,160):'other';
      console.log('DTF MASTER PHASE: '+JSON.stringify({ui:safeUiStatus,
        responses:masterHttp.map(row=>({side:row.side,method:row.method,
          http:row.http,contentType:row.contentType,code:row.code}))}));
      assert.match(masterStatus,/Master dan posisi gambar terverifikasi untuk front, back/,
        'Private front/back master upload or binding failed; see DTF MASTER PHASE');
      console.log('PASS: both private DTF masters uploaded and bound to same draft');

      const metadata=await page.evaluate(async ()=>{
        const cfg=window.SANRO_STUDIO_EDITOR_CONFIG;
        const state=JSON.parse(sessionStorage.getItem('sanro-studio-draft:24')||'null');
        if(!state)return {valid:false};
        const r=await fetch(cfg.draft_api.url+'/'+encodeURIComponent(state.id),{
          method:'GET',credentials:'same-origin',cache:'no-store',
          headers:{'X-Sanro-Draft-Nonce':cfg.draft_api.nonce,
            'X-Sanro-Owner-Token':state.token}
        });
        if(!r.ok)return {valid:false,http:r.status};
        const d=await r.json();
        return {valid:d.valid,hasFront:!!d.master_refs?.front,
          hasBack:!!d.master_refs?.back,
          frontImage:d.design?.front?.objects?.some(o=>o.type==='image'),
          backImage:d.design?.back?.objects?.some(o=>o.type==='image'),
          quantity:d.design?.quantities?.M};
      });
      assert(metadata.valid && metadata.hasFront && metadata.hasBack &&
        metadata.frontImage && metadata.backImage && metadata.quantity===1,
        'Owner-bound front/back draft metadata missing');
      console.log('PASS: persisted front/back image geometry and master references');

      if(flags.preview){
        await page.locator('#sanro-upload-previews').click();
        await page.waitForFunction(()=>{
          const b=document.querySelector('#sanro-upload-previews');
          const s=document.querySelector('#sanro-preview-upload-status')?.textContent||'';
          return b && !b.disabled && s && !s.includes('Menyiapkan preview');
        },null,{timeout:60000});
        assert.match(await page.locator('#sanro-preview-upload-status').innerText(),
          /Preview privat tersimpan: front, back/,
          'Image-derived private front/back preview not saved');
        console.log('PASS: image-derived private front/back previews saved and read back');
      } else {
        console.log('NOT TESTED: optional staging private preview upload flag disabled');
      }

      await page.reload({waitUntil:'domcontentloaded',timeout:30000});
      await page.waitForFunction(()=>
        /Draft berhasil dibuka kembali/.test(
          document.querySelector('#sanro-save-status')?.textContent||''),
        null,{timeout:45000});
      for(const side of ['front','back']){
        await page.locator('#sanro-studio-buyer button[data-side="'+side+'"]').click();
        await page.waitForFunction(()=>{
          const img=document.querySelector('#sanro-studio-buyer .sanro-safe img');
          return img && !img.hidden && img.complete && img.naturalWidth>0;
        },null,{timeout:15000});
        // Confirm that restoration did not substitute the opposite side's
        // private master. Read one synthetic PNG background pixel, not artwork.
        const rgb=await page.evaluate(()=>{
          const img=document.querySelector('#sanro-studio-buyer .sanro-safe img');
          const c=document.createElement('canvas');
          c.width=img.naturalWidth;c.height=img.naturalHeight;
          const ctx=c.getContext('2d');
          ctx.drawImage(img,0,0);
          return Array.from(ctx.getImageData(2,2,1,1).data).slice(0,3);
        });
        assert.deepEqual(rgb,side==='front'?[200,49,70]:[18,62,102],
          'Restored private master image does not match '+side+' side');
      }
      console.log('PASS: both owned DTF masters restored after browser reload');

      // Read authoritative price without returning draft ID/nonce/owner token.
      const quote=await page.evaluate(async()=>{
        const cfg=window.SANRO_STUDIO_EDITOR_CONFIG;
        const state=JSON.parse(sessionStorage.getItem('sanro-studio-draft:24')||'null');
        if(!state)return {valid:false};
        const res=await fetch(cfg.draft_api.url+'/'+encodeURIComponent(state.id)+'/quote',{
          method:'GET',credentials:'same-origin',cache:'no-store',
          headers:{'X-Sanro-Draft-Nonce':cfg.draft_api.nonce,'X-Sanro-Owner-Token':state.token}
        });
        if(!res.ok)return {valid:false,http:res.status};
        const d=await res.json();
        return {valid:d.valid===true&&d.quote?.valid===true,
          currency:d.quote?.currency,total:d.quote?.total_price,
          quantity:d.quote?.total_quantity,shortId:state.id.slice(0,8)};
      });
      assert(quote.valid&&quote.currency==='IDR'&&
        Number.isSafeInteger(quote.total)&&quote.total>0&&quote.quantity===1,
        'DTF front/back server quote invalid');
      console.log('PASS: authoritative DTF front/back quote IDR '+quote.total);

      const cartResponse=page.waitForResponse(r=>{
        try {
          const url=new URL(r.url());
          return url.hostname===host && r.request().method()==='POST' &&
            url.pathname.endsWith('/cart') &&
            url.pathname.includes('/sanro-studio/v1/drafts/');
        } catch(_){return false}
      },{timeout:30000});
      await page.locator('#sanro-add-to-cart').click();
      const cart=await cartResponse;
      assert.equal(cart.status(),200,'Studio image draft Cart POST rejected');
      await page.waitForURL(u=>u.hostname===host&&/^\/cart\/?$/.test(u.pathname),
        {waitUntil:'domcontentloaded',timeout:30000});
      const cartSummary=await page.locator('.woocommerce-cart-form').innerText();
      assert(cartSummary.includes('Ukuran kaos')&&cartSummary.includes('M')
        &&cartSummary.includes('Kode desain')&&cartSummary.includes(quote.shortId),
        'WooCommerce Cart missing image-bound design reference or size');
      const shownPrice=quote.total.toLocaleString('id-ID');
      assert((await page.locator('.cart_totals').innerText()).includes(shownPrice),
        'WooCommerce Cart price differs from authoritative image draft');
      console.log('PASS: image-bearing DTF design and server price reach WooCommerce Cart');

      await page.locator('.checkout-button').first().click();
      await page.waitForURL(u=>u.hostname===host&&/^\/checkout\/?$/.test(u.pathname),
        {waitUntil:'domcontentloaded',timeout:30000});
      const checkout=await page.locator('.woocommerce-checkout-review-order').innerText();
      assert(checkout.includes('Ukuran kaos')&&checkout.includes('Kode desain')
        &&checkout.includes(quote.shortId)&&checkout.includes(shownPrice),
        'Checkout summary lost image design identity, size or price');
      console.log('PASS: DTF front/back image draft -> Cart -> Checkout (NO ORDER, NO PAYMENT)');
    } finally {await context.close();}
  } finally {await browser.close();}
})().catch(e=>{
  // Never log URLs, IDs, owner tokens, arbitrary server bodies or private art.
  const msg=String(e.message||e).replace(/\b[a-f0-9]{64}\b/gi,'[redacted]')
    .replace(/\b[a-f0-9]{8}-[a-f0-9-]{27}\b/gi,'[redacted]')
    .replace(/https?:\/\/\S+/gi,'[staging-url]').slice(0,260);
  console.error('FAIL DTF image E2E: '+msg);
  process.exitCode=1;
});
