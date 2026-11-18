'use strict';
// SANRO Studio Milestone 1: synthetic, text-only staging draft round trip.
// This is intentionally scoped to the existing staging test product (ID 24).
// Default operation is read-only preflight; writing requires explicit opt-in.
// No checkout, media upload, WP admin edits, production, or secrets in logs.
const assert = require('node:assert/strict');
const url = new URL(process.env.SANRO_STAGING_EDITOR_URL ||
 'https://saddlebrown-peafowl-616656.hostingersite.com/product/test/?sanro_studio_editor=1&product_id=24');
if (url.protocol !== 'https:' ||
 url.hostname !== 'saddlebrown-peafowl-616656.hostingersite.com' ||
 url.searchParams.get('sanro_studio_editor') !== '1' ||
 url.searchParams.get('product_id') !== '24') {
 console.error('BLOCKED: this draft test accepts only SANRO staging product test (ID 24).');
 process.exit(2);
}
let chromium;
try { ({chromium} = require('playwright')); }
catch (_) { console.error('BLOCKED: Playwright unavailable; set NODE_PATH to isolated QA node_modules.'); process.exit(2); }
(async () => {
 const browser = await chromium.launch({headless:true});
 const context = await browser.newContext({viewport:{width:390,height:844}});
 const page = await context.newPage();
 try {
  const response = await page.goto(url.href,{waitUntil:'domcontentloaded',timeout:30000});
  assert(response && response.ok(), 'Staging editor returned non-2xx status');
  await page.locator('#sanro-studio-buyer #sanro-text-input').waitFor({timeout:15000});
  console.log('PASS staging test product editor available');
  const capabilities = await page.evaluate(() => {
   const config = window.SANRO_STUDIO_EDITOR_CONFIG || {};
   return {
    draftApi: Boolean(config.draft_api && config.draft_api.url && config.draft_api.nonce),
    saveControl: Boolean(document.querySelector('#sanro-save-draft')),
    productId: Number(config.product_id)
   };
  });
  assert.equal(capabilities.productId,24,'Product identity mismatch');
  if (!capabilities.draftApi || !capabilities.saveControl) {
   console.error('BLOCKED: staging draft API/save control is disabled. Check the staging-only draft REST enable flags and plugin version.');
   process.exitCode=2;
   return;
  }
  console.log('PASS staging draft API and Save control exposed');
  if (process.env.SANRO_STAGING_DRAFT_WRITE !== '1') {
   console.log('READ-ONLY: set SANRO_STAGING_DRAFT_WRITE=1 to test one synthetic draft save and reload.');
   return;
  }
  const first = page.locator('[data-side]:not([disabled])').first();
  assert(await first.count(),'No configured print side');
  const chosen = await first.getAttribute('data-side');
  // Keep the fixture safely inside the configured print geometry.
  // The previous long label at default 22px exceeded the A5 category on mobile.
  const marker = 'QA' + Date.now().toString(36).slice(-4);
  await first.click();
  await page.locator('#sanro-category').selectOption('A3+');
  await page.locator('#sanro-font-size').evaluate(input => { input.value='10'; input.dispatchEvent(new Event('input',{bubbles:true})); });
  await page.locator('#sanro-text-input').fill(marker);
  await page.locator('#sanro-center-text').click();
  await page.waitForFunction(() => {
   const message = document.querySelector('#sanro-validation')?.textContent || '';
   return /Pratinjau sesuai batas/.test(message);
  },null,{timeout:10000});
  const geometry = await page.locator('#sanro-validation').innerText();
  assert.match(geometry,/Pratinjau sesuai batas/, 'Synthetic text geometry invalid: '+geometry);
  console.log('PASS text geometry within admin print bounds');
  const qty = page.locator('input[data-size="M"]');
  await qty.fill('1');
  const status = page.locator('#sanro-save-status');
  await page.locator('#sanro-save-draft').click();
  await page.waitForFunction(() => {
   const value = document.querySelector('#sanro-save-status')?.textContent || '';
   return /Draft teks tersimpan|Penyimpanan gagal|Teks di luar area|Jumlah per ukuran tidak valid/.test(value);
  },null,{timeout:30000});
  const saved = await status.innerText();
  assert.match(saved,/Draft teks tersimpan/, 'Draft save did not succeed: '+saved);
  const stored = await page.evaluate(() => sessionStorage.getItem('sanro-studio-draft:24'));
  assert(stored,'Owner-bound draft reference absent from sessionStorage');
  const credentials = JSON.parse(stored);
  assert.match(credentials.id,/^[a-f0-9-]{36}$/i);
  assert.match(credentials.token,/^[a-f0-9]{64}$/);
  console.log('PASS synthetic text draft saved; private reference retained without logging token');
  // Track only the REST status, never the private draft URL, ID, owner token, or response body.
  const draftApiBase = await page.evaluate(() => String(window.SANRO_STUDIO_EDITOR_CONFIG.draft_api.url).replace(/\\/$/, ''));
  const restoreReads = [];
  const browserErrorTypes = [];
  page.on('response', response => {
   if (response.request().method()==='GET' && response.url().startsWith(draftApiBase+'/')) {
    restoreReads.push(response.status());
   }
  });
  page.on('pageerror', error => { if(browserErrorTypes.length<3)browserErrorTypes.push(error.name||'Error'); });
  await page.reload({waitUntil:'domcontentloaded',timeout:30000});
  const referencePresent = await page.evaluate(() => Boolean(sessionStorage.getItem('sanro-studio-draft:24')));
  assert(referencePresent, 'Draft reference disappeared from sessionStorage after same-tab reload');
  // The editor can report more error strings than the old whitelist; do not hide
  // those actionable failures behind a generic 30-second timeout.
  let restoreStatusChanged = false;
  try {
   await page.waitForFunction(() => {
    const value = document.querySelector('#sanro-save-status')?.textContent?.trim() || '';
    return Boolean(value && value !== 'Penyimpanan gambar dan checkout belum tersedia.');
   },null,{timeout:20000});
   restoreStatusChanged = true;
  } catch (error) {
   if (!String(error.message).includes('Timeout')) throw error;
  }
  const restoredStatus = (await page.locator('#sanro-save-status').innerText()).trim();
  const safeStatus = restoredStatus.replace(/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/ig,'[draft-id]')
   .replace(/\\b[a-f0-9]{64}\\b/ig,'[redacted]');
  const diagnostics = 'restore status='+JSON.stringify(safeStatus)
   +'; draft GET status='+(restoreReads.length?restoreReads.join(','):'not observed')
   +'; browser errors='+(browserErrorTypes.length?browserErrorTypes.join(','):'none');
  assert(restoreStatusChanged, 'No restore result after reload; '+diagnostics);
  assert.match(restoredStatus,/Draft berhasil dibuka kembali/, 'Draft restore failed; '+diagnostics);
  await page.locator('[data-side="'+chosen+'"]').click();
  assert.equal(await page.locator('#sanro-text-input').inputValue(),marker);
  assert.equal(await page.locator('input[data-size="M"]').inputValue(),'1');
  console.log('PASS text and quantity restored after same-tab reload');
  console.log('PASS staging draft round-trip (one synthetic draft; expires according to server TTL)');
 } finally {await browser.close();}
})().catch(error=>{console.error('FAIL '+(error.stack||error.message));process.exitCode=1;});
