'use strict';
/**
 * Read-only staging discovery followed by the existing buyer browser smoke test.
 * Usage:
 *   NODE_PATH="$HOME/sanro-browser-qa/node_modules" node tests/staging-discover-and-test.cjs
 * Optional:
 *   SANRO_STAGING_SITE_URL=https://your-staging-host node tests/staging-discover-and-test.cjs
 * Runs no draft persistence, checkout, or production operation.
 */
const {spawnSync} = require('node:child_process');
const path = require('node:path');
const site = process.env.SANRO_STAGING_SITE_URL || 'https://saddlebrown-peafowl-616656.hostingersite.com/';
let base;
try { base = new URL(site); }
catch (_) { console.error('BLOCKED: invalid staging site URL'); process.exit(2); }
if (base.protocol !== 'https:' || base.username || base.password
 || base.hostname === 'kaoscetak.id' || base.hostname.endsWith('.kaoscetak.id')) {
 console.error('BLOCKED: HTTPS non-production staging domain required'); process.exit(2);
}
let chromium;
try { ({chromium} = require('playwright')); }
catch (_) { console.error('BLOCKED: Playwright not found. Set NODE_PATH to isolated QA node_modules.'); process.exit(2); }
function approved(url) {
 try {
  const u = new URL(url,base);
  return u.protocol === 'https:' && u.origin === base.origin ? u.href : null;
 } catch (_) { return null; }
}
(async () => {
 const browser = await chromium.launch({headless:true});
 const page = await browser.newPage({viewport:{width:390,height:844}});
 let found = null;
 const visited = new Set();
 async function scan(url) {
  url = approved(url);
  if (!url || visited.has(url)) return null;
  visited.add(url);
  try {
   const response = await page.goto(url,{waitUntil:'domcontentloaded',timeout:20000});
   console.log('SCAN '+(response ? response.status() : 'unknown')+' '+url);
   const direct = await page.locator('a[href*="sanro_studio_editor=1"][href*="product_id="]').first().getAttribute('href').catch(()=>null);
   if (direct) return approved(direct);
   if (await page.locator('#sanro-studio-buyer #sanro-text-input').count()) return page.url();
   const card = await page.locator('a.sanro-product-card').first().getAttribute('href').catch(()=>null);
   return card ? approved(card) : null;
  } catch (error) {
   console.log('SKIP '+url+' ('+error.message.split('\n')[0]+')');
   return null;
  }
 }
 try {
  found = await scan(base.href);
  if (!found) {
   const endpoint = approved('/wp-json/wc/store/v1/products?per_page=24');
   try {
    const response = await page.request.get(endpoint,{timeout:15000});
    if (response.ok()) {
     const products = await response.json();
     if (Array.isArray(products)) {
      for (const product of products.slice(0,24)) {
       if (typeof product.permalink === 'string') {
        found = await scan(product.permalink);
        if (found) break;
       }
      }
     }
    } else console.log('INFO WooCommerce Store API HTTP '+response.status());
   } catch (error) { console.log('INFO WooCommerce Store API unavailable: '+error.message.split('\n')[0]); }
  }
  if (!found) {
   try {
    const response = await page.request.get(approved('/wp-json/wp/v2/pages?per_page=100'),{timeout:15000});
    if (response.ok()) {
     const pages = await response.json();
     if (Array.isArray(pages)) {
      const likely = pages.filter(p=>/studio|designer|desain|editor/i.test((p.slug||'')+' '+(p.title?.rendered||'')));
      for (const item of likely.slice(0,12)) {
       found = await scan(item.link);
       if (found) break;
      }
     }
    }
   } catch (error) { console.log('INFO WordPress pages API unavailable: '+error.message.split('\n')[0]); }
  }
 } finally { await browser.close(); }
 if (!found) {
  console.error('BLOCKED: buyer editor URL not discovered. Check staging page publication, configured product, or staging access.');
  process.exitCode=2;
  return;
 }
 console.log('DISCOVERED buyer editor: '+found);
 const result = spawnSync(process.execPath,[path.join(__dirname,'staging-buyer-e2e.cjs')],{
  stdio:'inherit',env:{...process.env,SANRO_STAGING_EDITOR_URL:found}
 });
 process.exitCode = typeof result.status === 'number' ? result.status : 1;
})().catch(error=>{console.error('FAIL '+error.stack);process.exitCode=1;});
