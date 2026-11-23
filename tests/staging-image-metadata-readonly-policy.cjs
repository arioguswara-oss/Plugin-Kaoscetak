'use strict';
/** Offline safeguards for READ-ONLY QA metadata classification. No network. */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const script=fs.readFileSync(
  path.join(__dirname,'staging-image-metadata-readonly-diagnostic.cjs'),'utf8'
);
let checks=0;
function check(x,label){assert(x,label);checks++;}
check(script.includes("process.env.SANRO_STUDIO_QA_ENV!=='staging'"),
  'exact staging environment required');
check(script.includes("readTextQaDraft()"),
  'local owner-private synthetic QA state required');
check(script.includes("await context.route('**/*',route=>"),
  'all browser requests are routed');
check(script.includes("if(!['GET','HEAD'].includes(route.request().method()))"),
  'every write method is blocked');
check(script.includes("return route.abort('blockedbyclient')"),
  'write request fails closed');
check(script.includes("await page.waitForFunction(")&&
  script.includes("Draft berhasil dibuka kembali."),
  'reopened draft is validated before browser measurements');
check(script.includes("method:'GET'")&&
  script.includes("X-Sanro-Owner-Token"),
  'server metadata obtained only by authenticated GET');
check(script.includes("original.frontBound&&!original.backBound"),
  'partially stored master must be front-only');
check(script.includes("server[name].objects.filter(item=>item.type!=='image')"),
  'server text metadata is compared without image geometry');
check(script.includes("Number.parseFloat(text.style.left)")&&
  script.includes("safe.getBoundingClientRect()"),
  'text coordinates reconstructed from browser layout');
check(script.includes("key_order_only:equivalent&&!exact"),
  'ordering mismatch distinguished from semantic differences');
check(script.includes("floating_point_only:driftOnly"),
  'numeric roundoff distinguished from actual metadata changes');
check(script.includes("mismatches:differences.slice(0,20)"),
  'mismatch output is limited and field-name-only');
check(!script.includes("console.log(credentials)")&&
  !script.includes("console.log(original.design)")&&
  !script.includes("console.log(local)")&&
  !script.includes("console.log(server)"),
  'no token, artwork, or raw metadata logged');
check(script.includes("if(blockedWrites)process.exitCode=2"),
  'unexpected write attempts fail diagnostic');
check(!/page\.locator\(['"]#sanro-(?:upload-master|save-draft|add-to-cart)['"]\)\.click\(/.test(script),
  'no browser actions to upload, save or add cart');
console.log('PASS: '+checks+' SANRO staging image metadata read-only diagnostic safeguards');
