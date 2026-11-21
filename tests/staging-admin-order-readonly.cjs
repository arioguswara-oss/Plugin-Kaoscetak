'use strict';
/**
 * Sprint 139 Playwright staging WordPress/WooCommerce admin E2E (GET/HEAD ONLY).
 * Existing Playwright + existing owner-private staging storageState are reused.
 * No login entry, new session, new order, save, upload, payment or deployment.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {HOST, ORIGIN, assertSafeStorageState, requestAllowed, safeOrderId,
  redactedIssue} = require('./lib/staging-admin-guards.cjs');

function codeError(code) { const error=new Error(code); error.code=code; return error; }
function stageOutput(label,value) {
  // Only controlled labels and status words; never log page HTML, request URLs
  // (which contain wp nonces), order details or exception stack traces.
  console.log(label + ': ' + value);
}
const rawId = process.env.SANRO_STAGING_ADMIN_ORDER_ID;
if (process.env.SANRO_STUDIO_QA_ENV !== 'staging' ||
    process.env.SANRO_STAGING_CART_E2E === '1' ||
    process.env.SANRO_STAGING_IMAGE_E2E === '1' ||
    process.env.SANRO_STAGING_ADMIN_WRITE === '1') {
  console.error('BLOCKED: staging read-only browser mode required');
  process.exit(2);
}
const id = rawId ? safeOrderId(rawId) : null;
if (rawId && id === null) {
  console.error('BLOCKED: staging synthetic order id must be a positive integer');
  process.exit(2);
}
let state;
try {
  state = assertSafeStorageState(process.env.SANRO_STAGING_ADMIN_STATE);
} catch (_) {
  console.error('BLOCKED: private staging admin login state missing or unsafe');
  process.exit(2);
}
let chromium;
try { ({chromium}=require('playwright')); }
catch (_) {
  console.error('BLOCKED: reuse existing Hermes Playwright; module unavailable');
  process.exit(2);
}

(async () => {
  const opts={headless:true};
  if (process.env.SANRO_CHROMIUM_EXECUTABLE) {
    opts.executablePath=process.env.SANRO_CHROMIUM_EXECUTABLE;
  }
  const browser=await chromium.launch(opts);
  let browserErrors=0, blockedWrites=0;
  try {
    const context=await browser.newContext({
      storageState:state,acceptDownloads:false,
      viewport:{width:1440,height:1000},locale:'id-ID'
    });
    await context.route('**/*', async route => {
      const req=route.request();
      if (!requestAllowed(req.method(),req.url())) {
        if (!['GET','HEAD'].includes(req.method())) blockedWrites++;
        return route.abort('blockedbyclient');
      }
      return route.continue();
    });
    const page=await context.newPage();
    page.on('pageerror',()=>{ browserErrors++; });
    const main=await page.goto(ORIGIN+'/wp-admin/',{
      waitUntil:'domcontentloaded',timeout:30000
    });
    assert(main && main.status() === 200,'staging admin not HTTP 200');
    const destination = new URL(page.url());
    if (destination.hostname !== HOST || destination.pathname.includes('wp-login.php') ||
      await page.locator('#loginform, input[name="log"]').count()) {
      throw codeError('AUTH_REQUIRED');
    }
    if (!await page.locator('#adminmenu, #wpadminbar').count()) {
      throw codeError('AUTH_REQUIRED');
    }
    stageOutput('STAGING_ADMIN_LOGIN','PASS');

    const wooMenu = await page.locator('#toplevel_page_woocommerce,'+
      '#adminmenu a[href*="page=wc-admin"],#adminmenu a[href*="page=wc-orders"]').count();
    stageOutput('WOOCOMMERCE_ADMIN_MENU',wooMenu>0?'VISIBLE':'MISSING');
    if (wooMenu<1) throw Error('WooCommerce menu not available to saved staging admin role');

    if (id===null) {
      stageOutput('STAGING_ORDER_DETAIL','NOT_TESTED_NO_SYNTHETIC_ID');
      stageOutput('DTF_ADMIN_PANEL','NOT_TESTED');
      stageOutput('JS_PAGE_ERRORS',browserErrors);
      stageOutput('BLOCKED_WRITE_REQUESTS',blockedWrites);
      stageOutput('BROWSER_LOGIN_MENU_QA','PASS');
      stageOutput('WOOCOMMERCE_ORDER_UI_E2E','NOT_TESTED');
      return;
    }

    const editUrls=[
      ORIGIN+'/wp-admin/admin.php?page=wc-orders&action=edit&id='+id,
      ORIGIN+'/wp-admin/post.php?post='+id+'&action=edit'
    ];
    let opened=false;
    for (const editUrl of editUrls) {
      const response=await page.goto(editUrl,{
        waitUntil:'domcontentloaded',timeout:30000
      });
      const actual=new URL(page.url());
      if (actual.hostname !== HOST || actual.pathname.includes('wp-login.php') ||
        await page.locator('#loginform, input[name="log"]').count()) {
        throw codeError('AUTH_REQUIRED');
      }
      if (!response || response.status()!==200) continue;
      const hasOrderItems=await page.locator(
        '#woocommerce-order-items, #order_line_items,'+
        '.woocommerce_order_items_wrapper, .woocommerce-order-items'
      ).count();
      const pluginItems=await page.locator(
        '.sanro-studio-item-preview,.sanro-studio-admin-dtf-status'
      ).count();
      if ((hasOrderItems+pluginItems)>0) { opened=true; break; }
    }
    if (!opened) throw codeError('ORDER_NOT_FOUND');
    stageOutput('STAGING_ORDER_DETAIL','PASS_READ_ONLY');

    const previews=page.locator('.sanro-studio-item-preview');
    const status=page.locator('.sanro-studio-admin-dtf-status');
    const previewCount=await previews.count();
    const statusCount=await status.count();
    stageOutput('SANRO_ITEM_PREVIEW_PANELS',previewCount>0?'PRESENT':'ABSENT');
    stageOutput('DTF_ADMIN_PANEL',statusCount>0?'PRESENT':'NOT_DEPLOYED_OR_DISABLED');

    // A candidate status panel must not surface private download actions.
    // This does not prove pixel correctness or that the candidate is press-ready.
    if(statusCount){
      for(let i=0;i<statusCount;i++){
        const panel=status.nth(i);
        assert.equal(await panel.locator('a[href],form,button,input,img').count(),0,
          'Candidate status panel contains unexpected action or image');
        assert.match(await panel.innerText(),/Belum disetujui untuk cetak/,
          'Candidate status does not warn against production use');
      }
      if(process.env.SANRO_STAGING_CAPTURE_ADMIN_PANEL==='1'){
        const dir=path.join(process.env.HOME||'/home/sanro',
          '.local/state/sanro-studio-browser-qa');
        fs.mkdirSync(dir,{recursive:true,mode:0o700});
        const stat=fs.lstatSync(dir);
        assert(stat.isDirectory()&&!stat.isSymbolicLink()&&
          (stat.mode&0o077)===0 &&
          (!process.getuid||stat.uid===process.getuid()),
          'Unsafe QA screenshot directory');
        const capture=path.join(dir,'sprint139-dtf-status-synthetic.png');
        await status.first().screenshot({path:capture,animations:'disabled',timeout:15000});
        fs.chmodSync(capture,0o600);
        stageOutput('PRIVATE_DTF_PANEL_SCREENSHOT','CAPTURED');
      }
      stageOutput('DTF_PANEL_SECURITY','PASS');
    }else{
      stageOutput('DTF_PANEL_SECURITY','NOT_TESTED_PANEL_ABSENT');
    }
    stageOutput('JS_PAGE_ERRORS',browserErrors);
    stageOutput('BLOCKED_WRITE_REQUESTS',blockedWrites);
    stageOutput('BROWSER_LOGIN_MENU_QA','PASS');
    stageOutput('WOOCOMMERCE_ORDER_UI_E2E',statusCount>0?
      'PANEL_PRESENT_READ_ONLY':'PARTIAL_CANDIDATE_NOT_PRESENT');
    await context.close();
  } finally { await browser.close(); }
})().catch(error=>{
  console.error(redactedIssue(error));
  process.exitCode=error && ['AUTH_REQUIRED','ORDER_NOT_FOUND','INVALID_STATE'].includes(error.code)?2:1;
});
