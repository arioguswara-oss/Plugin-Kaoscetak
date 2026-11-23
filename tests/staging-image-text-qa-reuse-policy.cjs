'use strict';
/**
 * SANRO Studio Sprint 156: offline, synthetic-only test of image E2E reuse.
 * No network, WP login, cookies, genuine draft credentials or staging writes.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {readTextQaDraft}=require('./staging-image-private-draft.cjs');
const source=fs.readFileSync(path.join(__dirname,'staging-image-checkout-e2e.cjs'),'utf8');
let checks=0;
function check(value,label){assert(value,label);checks++}
check(source.includes("const textQaReuseRequested=process.env.SANRO_STAGING_IMAGE_REUSE_TEXT_QA==='1'"),
  'text QA reuse is explicitly opt-in');
check(source.includes('if(reuseRequested && textQaReuseRequested)'),
  'image QA and text QA sources mutually exclusive');
check(source.includes("process.env.SANRO_STUDIO_QA_ENV!=='staging'"),
  'reuse limited to staging environment');
check(source.includes('const saved=textQaReuseRequested?readTextQaDraft():readPrivateDraft()'),
  'owner-only text QA state sourced locally');
check(source.includes("if(!usingSavedDraft) {\n      const draftCreates=[];"),
  'reusing text QA cannot issue new draft POST');
check(source.includes("const hasSavedText=(await page.locator('#sanro-text-input').inputValue())")&&
  source.includes("if(!usingSavedDraft || !hasSavedText)")&&
  source.includes("await page.locator('#sanro-category').selectOption('A3+')"),
  'existing text category preserved; empty side selects A3+ for image QA');
check(source.includes(".trim().length>0")&&
  source.indexOf("const hasSavedText=")>source.indexOf("button[data-side"),
  'empty-side category selection uses trimmed text after switching side');
const guard=source.indexOf('assert(safeSource,');
const master=source.indexOf("await page.locator('#sanro-upload-master').click()");
const draftCreate=source.indexOf("await page.locator('#sanro-save-draft').click()");
check(guard>=0&&master>guard&&draftCreate>guard,
  'owner-bound GET guard executes before all writes');
check(source.includes("!Object.keys(d.master_refs||{}).length")&&
  source.includes("Number(d.product_id)===24")&&
  source.includes("d.status==='draft'")&&
  source.includes("Number(d.design?.quantities?.M)===1"),
  'source must be an unbound, editable QA draft for product 24 and quantity M=1');
check(source.includes('if(draftCreates.some(item=>item.http===429')&&
  source.includes("sanro_rate_limited"),
  'guest rate-limit remains fail-closed');
check(source.includes("await context.route('**/*',route=>")&&
  source.includes("return route.abort('blockedbyclient')")&&
  source.includes("'/wp-json/sanro-studio/v1/drafts'"),
  'guest draft creation route blocked in reuse mode');
check(source.includes("const updateResponse=page.waitForResponse(")&&
  source.includes("const reusedId=await page.evaluate("),
  'synchronization waits for exact owner-bound update response');
check(source.includes("assert.deepEqual(updateRequests,['update'],")&&
  source.includes("result.status()===200"),
  'exactly one successful existing-draft update, zero create POSTs');
check(source.includes("const updateRequests=[];")&&
  source.includes("page.on('request',onSyncRequest)")&&
  source.includes("page.off('request',onSyncRequest)"),
  'update-only request accounting is scoped and cleaned up');
const sync=source.indexOf('const reusedId=await page.evaluate(');
const image=source.indexOf("await page.locator('#sanro-image').setInputFiles(");
const upload=source.indexOf("await page.locator('#sanro-upload-master').click()");
check(sync>source.indexOf('assert(safeSource,')&&image>sync&&upload>image,
  'draft sync happens after owner check and before image selection / upload');
check(source.includes("Never submits an order, places a payment or touches production"),
  'checkout side-effect restriction still documented');

const home=fs.mkdtempSync(path.join(os.tmpdir(),'sanro-qa-reuse-'));
const dir=path.join(home,'sanro-browser-qa');
const file=path.join(dir,'staging-cart-e2e-private.json');
const sample={
  host:'saddlebrown-peafowl-616656.hostingersite.com',
  product:24,
  id:'123e4567-e89b-42d3-a456-426614174000',
  token:'a'.repeat(64),
  createdAt:Date.now()
};
function rejects(label){assert.throws(()=>readTextQaDraft(home),undefined,label);checks++}
try{
  fs.mkdirSync(dir,{mode:0o700});
  fs.writeFileSync(file,JSON.stringify(sample),{mode:0o600});
  check(readTextQaDraft(home).id===sample.id,'valid synthetic private state accepted');
  fs.chmodSync(file,0o644); rejects('world-readable source rejected');
  fs.chmodSync(file,0o600);
  fs.writeFileSync(file,JSON.stringify({...sample,host:'kaoscetak.id'}));
  rejects('production host rejected');
  fs.writeFileSync(file,JSON.stringify({...sample,product:25}));
  rejects('wrong product rejected');
  fs.writeFileSync(file,JSON.stringify({...sample,createdAt:Date.now()-7*86400000}));
  rejects('expired reference rejected');
  fs.writeFileSync(file,JSON.stringify(sample));
  const target=path.join(dir,'target.txt');
  fs.renameSync(file,target);fs.symlinkSync(target,file);
  rejects('symlink QA state rejected');
  fs.unlinkSync(file);fs.renameSync(target,file);
  fs.chmodSync(dir,0o755); rejects('world-readable parent rejected');
}finally{
  fs.rmSync(home,{recursive:true,force:true});
}
console.log('PASS: '+checks+' SANRO image reuse of text QA draft safeguards (offline synthetic)');
