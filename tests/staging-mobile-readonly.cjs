'use strict';
/**
 * SANRO Studio staging-only, read-only mobile/touch integration QA.
 * Tests the plugin's editor controls; never saves a draft, mutates Cart,
 * submits Checkout, creates an order, or invokes payment.
 * Run through scripts/sanro-studio-staging-browser-qa.sh with
 * SANRO_STAGING_MOBILE_QA=1. Chromium touch emulation is not physical-device QA.
 */
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const host = 'saddlebrown-peafowl-616656.hostingersite.com';
const pageUrl = new URL('https://' + host + '/custom-2/?sanro_studio_editor=1&product_id=24');
const jsPath = '/wp-content/plugins/sanro-studio/assets/buyer.js';
const expectedHash = crypto.createHash('sha256').update(fs.readFileSync(
  path.join(__dirname, '../plugins/sanro-studio/assets/buyer.js')
)).digest('hex');
const sizes = [
  {width:320,height:700,label:'small-phone'},
  {width:390,height:844,label:'phone'},
  {width:768,height:1024,label:'tablet'}
];

let chromium;
try { ({chromium} = require('playwright')); }
catch (_) {
  console.error('BLOCKED: Playwright missing from existing Hermes QA installation.');
  process.exit(2);
}

(async () => {
  const launch = {headless:true};
  if (process.env.SANRO_CHROMIUM_EXECUTABLE) {
    launch.executablePath = process.env.SANRO_CHROMIUM_EXECUTABLE;
  }
  const browser = await chromium.launch(launch);
  let passed = 0;
  try {
    for (const size of sizes) {
      const context = await browser.newContext({
        viewport:{width:size.width,height:size.height},
        isMobile:true,hasTouch:true,deviceScaleFactor:2,
        locale:'id-ID',acceptDownloads:false
      });
      try {
        const page = await context.newPage();
        const assetResponse = page.waitForResponse(response => {
          try {
            const u = new URL(response.url());
            return u.protocol === 'https:' && u.hostname === host &&
              u.pathname === jsPath;
          } catch (_) { return false; }
        }, {timeout:30000});
        const response = await page.goto(pageUrl.href,
          {waitUntil:'domcontentloaded',timeout:30000});
        assert(response && response.status() === 200, 'Editor HTTP not 200');
        assert.equal(new URL(page.url()).hostname,host,'Unexpected navigation host');
        const asset = await assetResponse;
        assert.equal(asset.status(),200,'Buyer JS HTTP not 200');
        const actualHash = crypto.createHash('sha256').update(await asset.body()).digest('hex');
        assert.equal(actualHash,expectedHash,'Browser loaded stale buyer.js');
        await page.locator('#sanro-studio-buyer #sanro-text-input')
          .waitFor({state:'visible',timeout:15000});

        const geometry = await page.evaluate(() => {
          const root = document.querySelector('#sanro-studio-buyer');
          const layout = root?.querySelector('.sanro-layout');
          const controls = [
            '#sanro-studio-buyer button[data-side="front"]',
            '#sanro-studio-buyer button[data-side="back"]',
            '#sanro-save-draft',
            '#sanro-add-to-cart'
          ];
          const r = root?.getBoundingClientRect();
          const l = layout?.getBoundingClientRect();
          return {
            width:window.innerWidth,rootLeft:r?.left,rootRight:r?.right,
            layoutRight:l?.right,rootScrollWidth:root?.scrollWidth,
            rootClientWidth:root?.clientWidth,
            buttons:controls.map(selector => {
              const el = document.querySelector(selector);
              const b = el?.getBoundingClientRect();
              const style = el && getComputedStyle(el);
              return {selector,width:b?.width,height:b?.height,left:b?.left,
                right:b?.right,visible:!!(b && b.width && b.height &&
                  style.visibility !== 'hidden' && style.display !== 'none')};
            })
          };
        });
        assert.equal(geometry.width,size.width,'Unexpected mobile viewport width');
        assert(Number.isFinite(geometry.rootLeft) && geometry.rootLeft >= -2,
          'SANRO editor starts outside mobile viewport');
        assert(geometry.rootRight <= size.width + 2 &&
          geometry.layoutRight <= size.width + 2,
          'SANRO editor extends past mobile viewport');
        assert(geometry.rootScrollWidth <= geometry.rootClientWidth + 2,
          'SANRO editor has horizontal overflow');
        for (const b of geometry.buttons) {
          assert(b.visible && b.height >= 44 &&
            b.left >= -2 && b.right <= size.width + 2,
            'Mobile touch target invalid: ' + b.selector);
        }
        const back = page.locator('#sanro-studio-buyer button[data-side="back"]');
        const front = page.locator('#sanro-studio-buyer button[data-side="front"]');
        await back.tap();
        await page.waitForFunction(() => document.querySelector(
          '#sanro-studio-buyer button[data-side="back"]')?.getAttribute('aria-pressed') === 'true');
        await front.tap();
        await page.waitForFunction(() => document.querySelector(
          '#sanro-studio-buyer button[data-side="front"]')?.getAttribute('aria-pressed') === 'true');

        console.log('PASS MOBILE '+size.label+' '+size.width+
          'px: layout, 44px touch controls, front/back taps, buyer asset fingerprint');
        passed++;
      } finally {
        await context.close();
      }
    }
    console.log('PASS: '+passed+' staging mobile touch read-only profiles; NO DRAFT/CART/ORDER/PAYMENT');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error('FAIL mobile staging QA: ' + String(error.message || error).slice(0,200));
  process.exitCode = 1;
});
