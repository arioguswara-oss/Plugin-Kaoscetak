'use strict';
/**
 * SANRO Studio — authenticated staging WordPress product admin QA.
 * READ-ONLY: route blocks all non-GET/HEAD requests. Never clicks Save,
 * Update, or creates a WP session. Use an owner-controlled 0600 Playwright
 * storageState JSON outside GitHub (SANRO_STAGING_ADMIN_STATE).
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');
const host='saddlebrown-peafowl-616656.hostingersite.com';
const url='https://'+host+'/wp-admin/post.php?post=24&action=edit';
const assetPath='/wp-content/plugins/sanro-studio/assets/admin-print-zones.js';
const assetName='admin-print-zones.js';
function authStateFile(file) {
  if(!file || !path.isAbsolute(file))throw Error(
    'BLOCKED: SANRO_STAGING_ADMIN_STATE must be an absolute path to private staging browser state.');
  const stat=fs.lstatSync(file);
  assert(stat.isFile()&&!stat.isSymbolicLink(),
    'Admin state must be a regular file, never a symlink.');
  assert((stat.mode&0o077)===0,'Admin browser state must be owner-only (chmod 600).');
  if(process.getuid)assert.equal(stat.uid,process.getuid(),'Admin state owner mismatch.');
  const parsed=JSON.parse(fs.readFileSync(file,'utf8'));
  assert(parsed&&Array.isArray(parsed.cookies),'Invalid browser state JSON.');
  assert(parsed.cookies.every(c=>{
    const domain=String(c.domain||'').replace(/^\./,'');
    return domain===host;
  }),'Browser state must contain staging-host cookies only.');
  return file;
}
let chromium;
try{({chromium}=require('playwright'))}
catch(_){console.error('BLOCKED: existing Playwright installation unavailable.');process.exit(2)}
(async()=>{
  const state=authStateFile(process.env.SANRO_STAGING_ADMIN_STATE);
  const browser=await chromium.launch({
    headless:true,
    ...(process.env.SANRO_CHROMIUM_EXECUTABLE?
      {executablePath:process.env.SANRO_CHROMIUM_EXECUTABLE}:{})
  });
  try{
    const context=await browser.newContext({
      storageState:state,acceptDownloads:false,viewport:{width:1280,height:900}
    });
    // Fail closed against any admin save/autosave/heartbeat POST during this QA.
    await context.route('**/*',async route=>{
      const req=route.request();
      const dest=new URL(req.url());
      if(!['GET','HEAD'].includes(req.method())||
         (dest.hostname!==host && dest.protocol!=='data:' && dest.protocol!=='blob:')){
        return route.abort('blockedbyclient');
      }
      return route.continue();
    });
    const page=await context.newPage();
    const response=await page.goto(url,{waitUntil:'domcontentloaded',timeout:30000});
    assert(response&&response.status()===200,'Staging admin product page HTTP != 200.');
    assert.equal(new URL(page.url()).hostname,host,'Admin navigation escaped staging.');
    assert.equal(new URL(page.url()).pathname,'/wp-admin/post.php',
      'Authentication missing: redirected away from product editor.');
    const script=await page.locator('script[src]').evaluateAll(nodes=>
      nodes.map(n=>n.src).find(src=>{
        try{return new URL(src).pathname===
          '/wp-content/plugins/sanro-studio/assets/admin-print-zones.js'}
        catch(_){return false}
      })||null);
    assert(script,'Admin print-zone JS not enqueued for product editor.');
    const assetRes=await page.request.get(script,{timeout:15000});
    assert.equal(assetRes.status(),200,'Admin preview asset HTTP not 200.');
    const actualHash=crypto.createHash('sha256').update(await assetRes.body()).digest('hex');
    const expectedHash=crypto.createHash('sha256').update(fs.readFileSync(
      path.join(__dirname,'../plugins/sanro-studio/assets/',assetName))).digest('hex');
    assert.equal(actualHash,expectedHash,'Stale admin preview JS delivered by staging.');
    const inputIds=['x','y','width','height'];
    async function inspect(side){
      const group=page.locator('[data-sanro-printzone-side="'+side+'"]');
      const guide=group.locator('.sanro-printzone-guide');
      const status=page.locator('[data-sanro-zone-status="'+side+'"]');
      assert.equal(await group.count(),1,'Missing '+side+' guide container.');
      const values={};
      for(const key of inputIds){
        const el=page.locator('#_sanro_studio_zone_'+side+'_'+key);
        assert.equal(await el.count(),1,'Missing '+side+'/'+key+' numeric field.');
        values[key]=Number(await el.inputValue());
      }
      assert(Object.values(values).every(Number.isFinite),
        'Malformed saved staging '+side+' print-zone.');
      assert(values.x>=0&&values.y>=0&&values.width>0&&values.height>0
        &&values.x+values.width<=100&&values.y+values.height<=100,
        'Saved staging '+side+' print-zone invalid.');
      assert.equal(await guide.isVisible(),true,side+' guide not visible.');
      const box=await guide.evaluate(el=>({
        x:parseFloat(el.style.left),y:parseFloat(el.style.top),
        width:parseFloat(el.style.width),height:parseFloat(el.style.height)
      }));
      for(const key of inputIds)assert(Math.abs(box[key]-values[key])<0.01,
        'Guide visual '+side+'/'+key+' differs from saved form.');
      assert.match(await status.innerText(),/Batas cetak cm tidak berubah/,
        'Admin status did not identify visual-only placement.');
      return values;
    }
    const front=await inspect('front');
    const back=await inspect('back');
    // Temporary DOM edits only; all non-read requests are blocked.
    const x=page.locator('#_sanro_studio_zone_front_x');
    const before=await x.inputValue();
    const altered=Math.max(0,Math.min(front.x+1,100-front.width));
    await x.fill(String(altered));
    const newX=await page.locator(
      '[data-sanro-printzone-side="front"] .sanro-printzone-guide')
      .evaluate(el=>parseFloat(el.style.left));
    assert(Math.abs(newX-altered)<0.01,'Front admin live preview did not update.');
    const backX=await page.locator(
      '[data-sanro-printzone-side="back"] .sanro-printzone-guide')
      .evaluate(el=>parseFloat(el.style.left));
    assert(Math.abs(backX-back.x)<0.01,'Editing front unexpectedly changed back.');
    await x.fill(before);
    // Exercise validation without submitting: overflow must hide the guide.
    const width=page.locator('#_sanro_studio_zone_front_width');
    const prevWidth=await width.inputValue();
    await width.fill('100');
    assert.equal(await page.locator(
      '[data-sanro-printzone-side="front"] .sanro-printzone-guide')
      .isVisible(),false,'Overflow guide was not hidden.');
    assert.match(await page.locator('[data-sanro-zone-status="front"]').innerText(),
      /tidak valid/,'Invalid print zone must have visible feedback.');
    await width.fill(prevWidth);
    await inspect('front');
    console.log('PASS: staging authenticated admin print-zone (front/back, hash, live change, invalid bounds; GET-only)');
    await context.close();
  }finally{await browser.close()}
})().catch(err=>{
  const msg=String(err?.message||err).slice(0,220);
  console.error((/BLOCKED|Authentication missing/.test(msg)?'BLOCKED':'FAIL')+
    ': staging admin print-zone QA: '+msg);
  process.exitCode=/BLOCKED|Authentication missing/.test(msg)?2:1;
});