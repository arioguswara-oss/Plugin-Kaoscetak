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
const cssPath = '/wp-content/plugins/sanro-studio/assets/buyer.css';
const expectedCssHash = crypto.createHash('sha256').update(fs.readFileSync(
  path.join(__dirname, '../plugins/sanro-studio/assets/buyer.css')
)).digest('hex');
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
  const failures = [];
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
        const cssResponse = page.waitForResponse(response => {
          try {
            const u = new URL(response.url());
            return u.protocol === 'https:' && u.hostname === host &&
              u.pathname === cssPath;
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
        const cssAsset = await cssResponse;
        assert.equal(cssAsset.status(),200,'SANRO buyer CSS HTTP not 200');
        const actualCssHash = crypto.createHash('sha256').update(await cssAsset.body()).digest('hex');
        assert.equal(actualCssHash,expectedCssHash,'Browser loaded stale buyer.css');
        const servedCssUrl = new URL(cssAsset.url());
        assert((servedCssUrl.searchParams.get('ver') || '').endsWith('-'+expectedCssHash.slice(0,12)),
          'SANRO buyer.css URL must use its content fingerprint');
        // The V1.1 mobile workspace hides inactive tools behind its bottom tabs.
        // Select the text panel before checking controls so this is a user-visible flow.
        await page.locator('#sanro-studio-buyer [data-tool="text"]').tap();
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
          const metrics = el => {
            if (!el) return null;
            const rect = el.getBoundingClientRect();
            const cs = getComputedStyle(el);
            return {tag:el.tagName.toLowerCase(),id:el.id.slice(0,55),
              x:Math.round(rect.left),right:Math.round(rect.right),
              width:Math.round(rect.width),client:el.clientWidth,
              scroll:el.scrollWidth,minWidth:cs.minWidth,maxWidth:cs.maxWidth,
              paddingLeft:cs.paddingLeft,paddingRight:cs.paddingRight,
              overflowX:cs.overflowX};
          };
          const ancestors = [];
          for (let el=root;el&&ancestors.length<12;el=el.parentElement) {
            ancestors.push(metrics(el));
          }
          const visualWidth = window.visualViewport?.width ?? null;
          const deviceWidth = window.screen.width;
          const wideElements = [...document.querySelectorAll('body *')]
            .filter(el => {
              const cs = getComputedStyle(el);
              if (cs.display === 'none' || cs.visibility === 'hidden') return false;
              const box=el.getBoundingClientRect();
              return box.width>0 && (box.right>deviceWidth+2 || box.left< -2);
            })
            .slice(0,7).map(metrics);
          return {
            width:window.innerWidth,deviceWidth,visualWidth,
            htmlClientWidth:document.documentElement.clientWidth,
            htmlScrollWidth:document.documentElement.scrollWidth,
            bodyClientWidth:document.body.clientWidth,
            bodyScrollWidth:document.body.scrollWidth,
            ancestors,wideElements,
            viewportMeta:document.querySelector('meta[name="viewport"]')?.getAttribute('content') || '(missing)',
            rootLeft:r?.left,rootRight:r?.right,
            layoutRight:l?.right,rootScrollWidth:root?.scrollWidth,
            rootClientWidth:root?.clientWidth,
            cssLinked:[...document.querySelectorAll('link[rel="stylesheet"]')]
              .some(link => { try { return new URL(link.href).pathname ===
                '/wp-content/plugins/sanro-studio/assets/buyer.css'; } catch (_) { return false; } }),
            buttons:controls.map(selector => {
              const el = document.querySelector(selector);
              const b = el?.getBoundingClientRect();
              const style = el && getComputedStyle(el);
              return {selector,width:b?.width,height:b?.height,left:b?.left,
                right:b?.right,minHeight:style?.minHeight,display:style?.display,
                visible:!!(b && b.width && b.height &&
                  style.visibility !== 'hidden' && style.display !== 'none')};
            })
          };
        });
        // Only public CSS/layout metrics: no cookies, HTML, nonce or draft credentials.
        console.log('MOBILE WIDTH '+size.label+
          ' screen='+geometry.deviceWidth+'px inner='+geometry.width+
          'px visual='+geometry.visualWidth+'px html='+
          geometry.htmlClientWidth+'/'+geometry.htmlScrollWidth+
          'px body='+geometry.bodyClientWidth+'/'+geometry.bodyScrollWidth+
          'px cssHashOk='+Boolean(actualCssHash===expectedCssHash));
        console.log('MOBILE PARENTS '+size.label+' '+JSON.stringify(
          geometry.ancestors.filter(e => e.scroll>size.width+2 ||
            e.right>size.width+2 || e.paddingLeft!=='0px' ||
            e.paddingRight!=='0px').slice(0,8)));
        console.log('MOBILE WIDE '+size.label+' '+JSON.stringify(geometry.wideElements));
        console.log('MOBILE BUTTONS '+size.label+' '+JSON.stringify(
          geometry.buttons.map(b=>({control:b.selector.replace('#sanro-studio-buyer ',''),
            height:b.height,minHeight:b.minHeight,right:b.right}))));
        assert.match(geometry.viewportMeta,/width\s*=\s*device-width/i,
          'Missing device-width viewport meta for SANRO buyer');
        assert.equal(geometry.deviceWidth,size.width,
          'Configured emulated device screen width does not match target');
        for (const b of geometry.buttons) {
          assert(b.visible && b.height >= 44 &&
            b.left >= -2 && b.right <= size.width + 2,
            'Mobile touch target invalid: ' + b.selector);
        }
        // Compare read-only product REST metadata with real browser geometry.
        // A button tap alone is not enough to verify side-specific overlays.
        const configUrl='https://'+host+
          '/wp-json/sanro-studio/v1/products/24/editor-config';
        const configResponse=await page.request.get(configUrl,{timeout:15000});
        assert.equal(configResponse.status(),200,'Editor-config REST unavailable');
        const publicConfig=await configResponse.json();
        assert.equal(publicConfig.valid,true,'Staging product not configured');
        async function verifySide(name) {
          await page.locator('#sanro-studio-buyer button[data-side="'+name+'"]').tap();
          await page.waitForFunction(side => document.querySelector(
            '#sanro-studio-buyer button[data-side="'+side+'"]')
            ?.getAttribute('aria-pressed')==='true',name);
          const actual=await page.evaluate(side => {
            const safe=document.querySelector('#sanro-studio-buyer .sanro-safe');
            const shirt=document.querySelector('#sanro-studio-buyer .sanro-shirt');
            const safeBox=safe?.getBoundingClientRect();
            const shirtBox=shirt?.getBoundingClientRect();
            const cfg=window.SANRO_STUDIO_EDITOR_CONFIG||{};
            return {
              selected:document.querySelector(
                '#sanro-studio-buyer button[data-side="'+side+'"]')
                ?.getAttribute('aria-pressed'),
              position:safe?{
                x:parseFloat(safe.style.left),y:parseFloat(safe.style.top),
                width:parseFloat(safe.style.width),height:parseFloat(safe.style.height)
              }:null,
              positive:!!(safeBox&&shirtBox&&safeBox.width>0&&safeBox.height>0),
              inside:!!(safeBox&&shirtBox&&safeBox.left>=shirtBox.left-2
                &&safeBox.top>=shirtBox.top-2&&safeBox.right<=shirtBox.right+2
                &&safeBox.bottom<=shirtBox.bottom+2),
              hasMockup:!!(shirt&&shirt.style.backgroundImage
                &&shirt.style.backgroundImage!=='none'),
              configuredZone:cfg.print_zones?.[side]||null,
              configuredMockup:cfg.mockups?.[side]||null
            };
          },name);
          const expected=publicConfig.print_zones?.[name]||
            {x:27,y:22,width:46,height:54};
          assert.equal(actual.selected,'true','Wrong selected side '+name);
          assert(actual.positive&&actual.inside,
            'Print-zone missing/outside mockup for '+name);
          for(const key of ['x','y','width','height']) {
            assert(Number.isFinite(actual.position?.[key])
              &&Math.abs(actual.position[key]-expected[key])<0.01,
              'Visual '+key+' differs from REST '+name);
            assert.equal(actual.configuredZone?.[key],expected[key],
              'Inline buyer configuration stale for '+name+'/'+key);
          }
          const expectedMockup=publicConfig.mockups?.[name]||null;
          assert.equal(actual.configuredMockup,expectedMockup,
            'Inline mockup differs from REST for '+name);
          assert.equal(actual.hasMockup,!!expectedMockup,
            'Wrong active mockup for '+name);
          console.log('PASS PRINT-ZONE '+size.label+' '+name+
            ': REST metadata, placement, bounds, mockup (read-only)');
        }
        await verifySide('back');
        await verifySide('front');
        console.log('PASS TOUCH '+size.label+' '+size.width+
          'px: front/back taps and print-zone live geometry');
        assert(Number.isFinite(geometry.rootLeft) && geometry.rootLeft >= -2,
          'SANRO editor starts outside mobile viewport');
        assert(geometry.rootRight <= size.width + 2 &&
          geometry.layoutRight <= size.width + 2,
          'SANRO editor extends past mobile viewport');
        assert(geometry.rootScrollWidth <= geometry.rootClientWidth + 2,
          'SANRO editor has horizontal overflow');
        assert.equal(geometry.width,size.width,
          'Mobile layout width expanded to '+geometry.width+
          'px for '+size.width+'px screen; see ancestors/wideElements');

        console.log('PASS MOBILE '+size.label+' '+size.width+
          'px: layout, 44px touch controls, front/back taps, buyer asset fingerprint');
        passed++;
      } catch (error) {
        const message = String(error.message || error).slice(0,250);
        console.error('FAIL MOBILE '+size.label+' '+size.width+'px: '+message);
        failures.push(size.label);
      } finally {
        await context.close();
      }
    }
    assert.equal(failures.length,0,
      'Mobile QA failed in '+failures.join(', '));
    console.log('PASS: '+passed+' staging mobile touch read-only profiles; NO DRAFT/CART/ORDER/PAYMENT');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error('FAIL mobile staging QA: ' + String(error.message || error).slice(0,200));
  process.exitCode = 1;
});
