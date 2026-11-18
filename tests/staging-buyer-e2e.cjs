'use strict';
// Run: SANRO_STAGING_EDITOR_URL=https://<staging-editor-url> node tests/staging-buyer-e2e.cjs
// Requires Playwright installed in the test environment. Never point at production.
const assert = require('node:assert/strict');
const url = process.env.SANRO_STAGING_EDITOR_URL;
if (!url) { console.error('BLOCKED: set SANRO_STAGING_EDITOR_URL to the staging buyer editor page'); process.exit(2); }
const target = new URL(url);
if (target.protocol !== 'https:' || target.hostname === 'kaoscetak.id' || target.hostname.endsWith('.kaoscetak.id')) {
 console.error('BLOCKED: HTTPS non-production staging URL required'); process.exit(2);
}
let chromium;
try { ({chromium} = require('playwright')); }
catch (_) { console.error('BLOCKED: Playwright missing. Install it in isolated QA environment.'); process.exit(2); }
(async () => {
 const browser = await chromium.launch({headless:true});
 const context = await browser.newContext({viewport:{width:390,height:844}});
 const page = await context.newPage();
 const errors=[];
 page.on('pageerror', e => errors.push(e.message));
 try {
  await page.goto(url,{waitUntil:'domcontentloaded',timeout:30000});
  const root=page.locator('#sanro-studio-buyer');
  assert.equal(await root.count(),1,'Buyer editor must render');
  console.log('PASS buyer editor renders on mobile');
  const safe=page.locator('.sanro-safe');
  const bounds=await safe.boundingBox();
  assert(bounds && bounds.width>0 && bounds.x>=0 && bounds.x+bounds.width<=390+2,'Preview must fit mobile viewport');
  console.log('PASS preview fits 390px viewport');
  const sides=page.locator('[data-side]:not([disabled])');
  const count=await sides.count();
  assert(count>=1,'At least one configured design side required');
  const first=await sides.first().getAttribute('data-side');
  await sides.first().click();
  await page.locator('#sanro-text-input').fill('SANRO E2E');
  assert.match(await page.locator('#sanro-side-summary').innerText(),/ada desain/);
  console.log('PASS text edit on '+first);
  if(count>1){
   const second=await sides.nth(1).getAttribute('data-side');
   await sides.nth(1).click();
   assert.equal(await page.locator('#sanro-text-input').inputValue(),'','Opposite side starts empty');
   await page.locator('#sanro-text-input').fill('BELAKANG E2E');
   await page.locator('[data-side="'+first+'"]').click();
   assert.equal(await page.locator('#sanro-text-input').inputValue(),'SANRO E2E');
   await page.locator('[data-side="'+second+'"]').click();
   assert.equal(await page.locator('#sanro-text-input').inputValue(),'BELAKANG E2E');
   console.log('PASS front/back state isolation');
  } else console.log('SKIP front/back switching: only one side configured');
  assert.deepEqual(errors,[],'No uncaught JavaScript exceptions');
  console.log('PASS browser JS errors: 0');
  console.log('NOT TESTED draft persistence: requires a dedicated test product and authenticated staging API');
 } finally {await browser.close();}
})().catch(error=>{console.error('FAIL '+error.stack);process.exitCode=1;});
