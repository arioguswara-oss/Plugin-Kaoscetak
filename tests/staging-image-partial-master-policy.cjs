'use strict';
/** Pure synthetic-only tests: no WordPress, API calls, owner tokens or writes. */
const assert=require('node:assert/strict');
const crypto=require('node:crypto');
const fs=require('node:fs');
const path=require('node:path');
const {verifiedExistingFrontUpload}=require('./staging-image-partial-master-helper.cjs');
const script=fs.readFileSync(path.join(__dirname,'staging-image-checkout-e2e.cjs'),'utf8');
let n=0;
function check(condition,label){assert(condition,label);n++;}
function rejected(fn,label){assert.throws(fn,undefined,label);n++;}
const id='123e4567-e89b-42d3-a456-426614174000';
// Synthetic PNG-shaped buffer (unit tests verify readback matching, not image decoding).
const png=Buffer.concat([Buffer.from('89504e470d0a1a0a','hex'),Buffer.alloc(192,0x42)]);
const base64=png.toString('base64');
const sha256=crypto.createHash('sha256').update(png).digest('hex');
const stored={valid:true,side:'front',mime:'image/png',sha256,
  image_base64:base64,file_id:id+'-front-'+sha256+'.png'};
const accepted=verifiedExistingFrontUpload(stored,id,base64);
check(accepted.valid&&accepted.side==='front','verified persisted front reused');
check(accepted.sha256===sha256&&accepted.bytes===png.length,
  'byte-count and digest preserved in response');
check(accepted.file_id===stored.file_id,'bound file ID retained exactly');
rejected(()=>verifiedExistingFrontUpload({...stored,mime:'image/jpeg'},id,base64),
  'wrong MIME rejected');
rejected(()=>verifiedExistingFrontUpload({...stored,sha256:'0'.repeat(64)},id,base64),
  'changed digest rejected');
rejected(()=>verifiedExistingFrontUpload({...stored,image_base64:'AA=='},id,base64),
  'changed bytes rejected');
rejected(()=>verifiedExistingFrontUpload({...stored,file_id:'other.png'},id,base64),
  'foreign master filename rejected');
rejected(()=>verifiedExistingFrontUpload({...stored,side:'back'},id,base64),
  'wrong side rejected');
rejected(()=>verifiedExistingFrontUpload(stored,'invalid-draft-id',base64),
  'invalid owner draft ID rejected');
rejected(()=>verifiedExistingFrontUpload(stored,id,'YWJj'),
  'non-PNG expected content rejected');
check(script.includes("const resumePartial=process.env.SANRO_STAGING_IMAGE_RESUME_PARTIAL==='1'"),
  'partial recovery requires explicit opt-in');
check(script.includes("if(resumePartial && (!textQaReuseRequested ||"),
  'partial recovery requires existing text QA draft and staging');
check(script.includes("if(resumePartial &&")&&
  script.includes("return route.abort('blockedbyclient')"),
  'previously uploaded front cannot be sent again');
check(script.includes('const frontRef=await page.evaluate(async()=>')&&
  script.includes("'/master-read/front'"),
  'real owner-bound GET verifies stored front');
check(script.includes('verifiedExistingFrontUpload(frontRef,expectedId,fixture.base64)'),
  'expected synthetic bytes compared against server readback');
check(script.indexOf('verifiedExistingFrontUpload(frontRef,expectedId,fixture.base64)')
  <script.indexOf("await page.route('**/wp-json/sanro-studio/v1/drafts/*/masters/front'"),
  'response reuse only after byte-for-byte readback verification');
check(script.includes('if(!resumePartial){')&&
  script.includes('partial front master preserved without draft rewrite'),
  'partial master mode does not rewrite text metadata');
check(script.includes("typeof d.master_refs.front==='string'")&&
  script.includes('Object.keys(d.master_refs||{}).length===1'),
  'only front-bound partial state is accepted');
check(script.includes('assert.equal(frontReuseCount,1,')&&
  script.includes("item.side==='back'")&&
  script.includes("item.method==='POST'&&item.http===200"),
  'exactly one reused front and real back POST required');
check(script.includes('if(!usingSavedDraft) {')&&
  script.includes("return route.abort('blockedbyclient')"),
  'guest draft create remains disabled in reuse mode');
console.log('PASS: '+n+' SANRO persisted front master resume safeguards (offline synthetic)');
