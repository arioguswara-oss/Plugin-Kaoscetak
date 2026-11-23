'use strict';
/**
 * SANRO Studio staging READ-ONLY diagnostic.
 * Classifies strict buyer.js metadata drift after a saved QA draft is reopened.
 * Uses the existing owner-private synthetic QA draft, never prints token, ID,
 * actual artwork, customer text, raw server metadata or REST response bodies.
 * Zero POST, zero draft mutations, zero master upload, zero orders.
 */
const assert=require('node:assert/strict');
const {chromium}=require('playwright');
const {readTextQaDraft}=require('./staging-image-private-draft.cjs');

if(process.env.SANRO_STUDIO_QA_ENV!=='staging'){
  console.error('BLOCKED: exact staging QA environment is required.');
  process.exit(2);
}
const host='saddlebrown-peafowl-616656.hostingersite.com';
const url='https://'+host+'/custom-2/?sanro_studio_editor=1&product_id=24';
const credentials=readTextQaDraft();

function flattenedDiff(a,b,at='$',out=[]){
  if(out.length>=30)return out;
  if(typeof a!==typeof b || a===null || b===null){
    if(a!==b)out.push({field:at,kind:'type_or_null_mismatch'});
    return out;
  }
  if(typeof a==='number'){
    if(!Number.isFinite(a)||!Number.isFinite(b))out.push({field:at,kind:'non_finite_number'});
    else if(a!==b)out.push({field:at,
      kind:Math.abs(a-b)<=1e-6?'floating_point_drift':'numeric_mismatch',
      // Categorize numerical drift without displaying saved geometry values.
      magnitude:Math.abs(a-b)<=1e-6?'at_most_1e-6':'above_1e-6'});
    return out;
  }
  if(typeof a!=='object'){
    if(a!==b)out.push({field:at,kind:'value_mismatch'});
    return out;
  }
  if(Array.isArray(a)!==Array.isArray(b)){
    out.push({field:at,kind:'array_shape_mismatch'});return out;
  }
  if(Array.isArray(a)){
    if(a.length!==b.length)out.push({field:at,kind:'array_length_mismatch'});
    for(let i=0;i<Math.min(a.length,b.length);i++)
      flattenedDiff(a[i],b[i],at+'['+i+']',out);
    return out;
  }
  const keysA=Object.keys(a),keysB=Object.keys(b);
  for(const key of [...new Set([...keysA,...keysB])].sort()){
    if(!Object.hasOwn(a,key)||!Object.hasOwn(b,key)){
      out.push({field:at+'.'+key,kind:'missing_field'});
    }else{
      flattenedDiff(a[key],b[key],at+'.'+key,out);
    }
  }
  return out;
}

(async()=>{
  const browser=await chromium.launch({
    headless:true,
    ...(process.env.SANRO_CHROMIUM_EXECUTABLE
      ?{executablePath:process.env.SANRO_CHROMIUM_EXECUTABLE}:{})
  });
  try{
    const context=await browser.newContext({
      viewport:{width:390,height:844},locale:'id-ID',acceptDownloads:false
    });
    let blockedWrites=0;
    await context.route('**/*',route=>{
      if(!['GET','HEAD'].includes(route.request().method())){
        blockedWrites++;
        return route.abort('blockedbyclient');
      }
      return route.continue();
    });
    await context.addInitScript(state=>{
      if(location.hostname==='saddlebrown-peafowl-616656.hostingersite.com')
        sessionStorage.setItem('sanro-studio-draft:24',
          JSON.stringify({id:state.id,token:state.token}));
    },credentials);
    const page=await context.newPage();
    const response=await page.goto(url,{waitUntil:'domcontentloaded',timeout:30000});
    assert(response&&response.status()===200,'Staging editor unavailable');
    await page.waitForFunction(()=>
      (document.querySelector('#sanro-save-status')?.textContent||'')
        .includes('Draft berhasil dibuka kembali.'),
      null,{timeout:45000});
    const original=await page.evaluate(async()=>{
      const cfg=window.SANRO_STUDIO_EDITOR_CONFIG;
      let state;
      try{state=JSON.parse(sessionStorage.getItem('sanro-studio-draft:24')||'null')}
      catch(_){return null}
      if(!cfg||!state)return null;
      const r=await fetch(cfg.draft_api.url+'/'+encodeURIComponent(state.id),{
        method:'GET',credentials:'same-origin',cache:'no-store',
        headers:{'X-Sanro-Draft-Nonce':cfg.draft_api.nonce,
          'X-Sanro-Owner-Token':state.token}
      });
      if(!r.ok)return null;
      const data=await r.json();
      return data.valid===true && data.status==='draft'&&
        Number(data.product_id)===24
        ?{design:data.design,frontBound:typeof data.master_refs?.front==='string',
          backBound:typeof data.master_refs?.back==='string'}
        :null;
    });
    assert(original&&original.frontBound&&!original.backBound,
      'Expected owner-authorized partially-bound QA draft; no changes made');
    const local={};
    for(const side of ['front','back']){
      await page.locator('#sanro-studio-buyer button[data-side="'+side+'"]').click();
      const entry=await page.evaluate(name=>{
        const root=document.querySelector('#sanro-studio-buyer');
        const cfg=window.SANRO_STUDIO_EDITOR_CONFIG;
        const area=cfg?.safe_areas?.[name],safe=root?.querySelector('.sanro-safe');
        const text=root?.querySelector('.sanro-text');
        const input=root?.querySelector('#sanro-text-input');
        if(!area||!safe||!text||!input)return {error:true};
        if(!input.value.trim())return null;
        const rect=safe.getBoundingClientRect(),box=text.getBoundingClientRect();
        if(!rect.width||!rect.height||!box.width||!box.height)return {error:true};
        return {
          category:root.querySelector('#sanro-category').value,
          objects:[{
            type:'text',
            x:Number.parseFloat(text.style.left)*area.width/100,
            y:Number.parseFloat(text.style.top)*area.height/100,
            width:box.width*area.width/rect.width,
            height:box.height*area.height/rect.height
          }],
          text:input.value,
          font_size:Number(root.querySelector('#sanro-font-size').value),
          text_color:root.querySelector('#sanro-text-color').value
        };
      },side);
      assert(!entry?.error,'Browser print-zone geometry not ready');
      if(entry)local[side]=entry;
    }
    const metadata=await page.evaluate(()=>{
      const root=document.querySelector('#sanro-studio-buyer');
      return {
        shirt_color:root.querySelector('#sanro-shirt-color').value,
        quantities:Object.fromEntries([...root.querySelectorAll('input[data-size]')]
          .map(input=>[input.dataset.size,Number(input.value)])),
        long_sleeve:root.querySelector('#sanro-sleeve').value==='long'
      };
    });
    Object.assign(local,metadata);
    const server=structuredClone(original.design);
    for(const name of ['front','back']){
      if(!server[name])continue;
      server[name].objects=server[name].objects.filter(item=>item.type!=='image');
      if(!server[name].objects.length)delete server[name];
    }
    const differences=flattenedDiff(local,server);
    const exact=JSON.stringify(local)===JSON.stringify(server);
    const equivalent=differences.length===0;
    const driftOnly=differences.length>0&&
      differences.every(item=>item.kind==='floating_point_drift');
    console.log('STAGING_METADATA_DIAG:',JSON.stringify({
      source:'existing_owner_bound_synthetic_draft',
      full_json_equal:exact,
      semantic_fields_equal:equivalent,
      key_order_only:equivalent&&!exact,
      floating_point_only:driftOnly,
      mismatch_count:differences.length,
      mismatches:differences.slice(0,20),
      front_bound:original.frontBound,
      back_bound:original.backBound,
      blocked_write_requests:blockedWrites,
      mode:'READ_ONLY'
    }));
    console.log('STATUS: METADATA_DIAGNOSTIC_COMPLETE');
    if(blockedWrites)process.exitCode=2;
  }finally{await browser.close()}
})().catch(e=>{
  console.error('STATUS: BLOCKED_METADATA_DIAGNOSTIC_ERROR');
  console.error('ERROR_TYPE:',e&&e.name||'UnknownError');
  process.exitCode=2;
});
