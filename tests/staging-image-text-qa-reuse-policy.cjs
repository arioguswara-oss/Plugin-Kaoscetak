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
check(source.includes('if(!usingSavedDraft)await page.locator'),
  'existing text QA print category preserved for metadata CAS');
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
