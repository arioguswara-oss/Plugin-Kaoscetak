'use strict';
/**
 * SANRO Studio staging buyer E2E using free, self-hosted Playwright.
 *
 * Default: read-only preflight. Explicit SANRO_STAGING_CART_E2E=1 permits
 * exactly one synthetic text-only draft and one staging cart addition.
 * NEVER submits checkout, creates an order, or invokes a payment gateway.
 *
 * Run from the repo root:
 *   NODE_PATH=/path/to/isolated/node_modules node tests/staging-cart-checkout-e2e.cjs
 *   SANRO_STAGING_CART_E2E=1 NODE_PATH=/path/to/isolated/node_modules node tests/staging-cart-checkout-e2e.cjs
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const crypto = require('node:crypto');
const path = require('node:path');
const {assertSafeStorageState} = require('./lib/staging-admin-guards.cjs');
const expectedHost = 'saddlebrown-peafowl-616656.hostingersite.com';
const editor = new URL(process.env.SANRO_STAGING_EDITOR_URL ||
  'https://' + expectedHost + '/custom-2/?sanro_studio_editor=1&product_id=24');

if (editor.protocol !== 'https:' || editor.hostname !== expectedHost ||
    editor.searchParams.get('sanro_studio_editor') !== '1' ||
    editor.searchParams.get('product_id') !== '24') {
  console.error('BLOCKED: staging editor URL and product 24 are mandatory.');
  process.exit(2);
}
if (!['undefined', '1', '0'].includes(String(process.env.SANRO_STAGING_CART_E2E))) {
  console.error('BLOCKED: SANRO_STAGING_CART_E2E must be 1 or unset.');
  process.exit(2);
}
const write = process.env.SANRO_STAGING_CART_E2E === '1';
const reuseRequested = process.env.SANRO_STAGING_CART_REUSE === '1';
// Opt-in for Hostinger's Coming Soon gate. Reuse only an existing Hermes
// owner-private authenticated WP staging session; never capture or log it.
const authStatePath = process.env.SANRO_STAGING_BUYER_AUTH_STATE;
let validatedAuthState;
if (authStatePath !== undefined) {
  if (process.env.SANRO_STUDIO_QA_ENV !== 'staging') {
    console.error('BLOCKED: authenticated buyer QA requires exact staging mode.');
    process.exit(2);
  }
  try {
    validatedAuthState = assertSafeStorageState(authStatePath);
  } catch (_) {
    console.error('BLOCKED: missing or unsafe private staging browser session.');
    process.exit(2);
  }
}
const stateDir = path.join(process.env.HOME || '/home/sanro', 'sanro-browser-qa');
const stateFile = path.join(stateDir, 'staging-cart-e2e-private.json');
// Only local, owner-readable QA credentials; never store in GitHub or logs.
function privateDirectory() {
  fs.mkdirSync(stateDir, {recursive:true,mode:0o700});
  const stat = fs.lstatSync(stateDir);
  assert(stat.isDirectory() && !stat.isSymbolicLink()
    && (!process.getuid || stat.uid === process.getuid()),
    'Unsafe private browser QA state directory');
  if ((stat.mode & 0o077) !== 0) fs.chmodSync(stateDir,0o700);
}
function readPrivateDraft() {
  if (!reuseRequested || !write) return null;
  privateDirectory();
  if (!fs.existsSync(stateFile)) {
    throw new Error('No saved QA draft; run once without SANRO_STAGING_CART_REUSE.');
  }
  const stat = fs.lstatSync(stateFile);
  assert(stat.isFile() && !stat.isSymbolicLink()
    && (!process.getuid || stat.uid === process.getuid())
    && (stat.mode & 0o077) === 0, 'Unsafe private QA state file');
  const data = JSON.parse(fs.readFileSync(stateFile,'utf8'));
  assert(data.host === expectedHost && data.product === 24
    && /^[a-f0-9-]{36}$/i.test(data.id)
    && /^[a-f0-9]{64}$/.test(data.token)
    && Number.isFinite(data.createdAt)
    && data.createdAt <= Date.now()
    && Date.now() - data.createdAt < 6*86400000, 'Expired or invalid QA draft reference');
  return data;
}
function savePrivateDraft(id, token) {
  privateDirectory();
  assert(/^[a-f0-9-]{36}$/i.test(id) && /^[a-f0-9]{64}$/.test(token),
    'Invalid QA draft credentials');
  const temp = stateFile+'.'+process.pid+'.tmp';
  try {
    fs.writeFileSync(temp,JSON.stringify({
      host:expectedHost,product:24,id,token,createdAt:Date.now()
    }),{flag:'wx',mode:0o600});
    fs.renameSync(temp,stateFile);
  } finally {
    try {fs.unlinkSync(temp);} catch (_) {}
  }
}
let chromium;
try { ({chromium} = require('playwright')); }
catch (_) {
  console.error('BLOCKED: install Playwright in an isolated QA node_modules first.');
  process.exit(2);
}

function verifyHost(page) {
  assert.equal(new URL(page.url()).hostname, expectedHost,
    'Navigation left the authorised staging host');
}
function redact(message) {
  return String(message || '')
    .replace(/\b[a-f0-9]{64}\b/ig, '[redacted]')
    .replace(/\b[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\b/ig, '[draft-id]')
    .slice(0, 260);
}

(async () => {
  const launch = {headless:true};
  if (process.env.SANRO_CHROMIUM_EXECUTABLE) {
    launch.executablePath = process.env.SANRO_CHROMIUM_EXECUTABLE;
  }
  const browser = await chromium.launch(launch);
  const context = await browser.newContext({
    ...(validatedAuthState ? {storageState:validatedAuthState} : {}),
    viewport:{width:390,height:844}, locale:'id-ID',
    acceptDownloads:false
  });
  const priorDraft = readPrivateDraft();
  if (priorDraft) {
    await context.addInitScript(state => {
      if (location.hostname === 'saddlebrown-peafowl-616656.hostingersite.com') {
        sessionStorage.setItem('sanro-studio-draft:24',
          JSON.stringify({id:state.id,token:state.token}));
      }
    }, priorDraft);
  }
  const page = await context.newPage();
  // Verify the exact script executed by Chromium, not only a curl request
  // to an unversioned URL. Cache/CDN query variants can serve stale code.
  const expectedBuyerJs = crypto.createHash('sha256').update(
    fs.readFileSync(path.join(__dirname, '../plugins/sanro-studio/assets/buyer.js'))
  ).digest('hex');
  // Observe the browser's real script request first. Browser caching or a
  // different script loader can otherwise yield an absent Playwright response,
  // which is NOT evidence of differing bytes.
  const buyerAssetPath = '/wp-content/plugins/sanro-studio/assets/buyer.js';
  let buyerAssetResponse = null;
  page.on('response', response => {
    try {
      const url = new URL(response.url());
      if (url.origin === editor.origin && url.pathname === buyerAssetPath &&
          response.request().method() === 'GET') {
        buyerAssetResponse = response;
      }
    } catch (_) { /* Ignore unrelated responses. */ }
  });
  const jsErrors = [];
  page.on('pageerror', error => {
    if (jsErrors.length < 5) jsErrors.push(redact(error.name));
  });
  try {
    const opened = await page.goto(editor.href, {
      waitUntil:'domcontentloaded', timeout:30000
    });
    assert(opened && opened.ok(), 'Editor HTTP response is not 2xx');
    verifyHost(page);
    console.log('BUYER_STAGING_SESSION_MODE: ' +
      (validatedAuthState ? 'EXISTING_AUTHENTICATED' : 'GUEST'));
    // PHP normally renders the text control before DOMContentLoaded. If it is
    // missing, report only allowlisted booleans; never dump HTML, URL queries,
    // cookies or WordPress nonces. No staging writes occur in this diagnostic.
    const editorControl=page.locator('#sanro-studio-buyer #sanro-text-input');
    if (await editorControl.count() !== 1 ||
        !await editorControl.isVisible().catch(()=>false)) {
      const route=await page.evaluate(()=>{
        const target=new URL(location.href);
        const buyer=document.getElementById('sanro-studio-buyer');
        return {
          expectedPath:target.pathname==='/custom-2/',
          editorQuery:target.searchParams.get('sanro_studio_editor')==='1',
          productQuery:target.searchParams.get('product_id')==='24',
          buyerRoot:!!buyer,
          productPicker:!!buyer?.classList.contains('sanro-product-picker'),
          textControl:!!document.getElementById('sanro-text-input'),
          loginForm:!!document.querySelector('#loginform, input[name="log"]'),
          wordpress404:document.body?.classList.contains('error404')===true,
          buyerJsTag:[...document.scripts].some(script=>{
            try {return new URL(script.src).pathname===
              '/wp-content/plugins/sanro-studio/assets/buyer.js';}
            catch (_) {return false;}
          })
        };
      });
      console.log('BUYER_EDITOR_ROUTE_DIAG: '+JSON.stringify({
        http:opened.status(),...route,jsErrors:jsErrors.length
      }));
      throw Error('Staging page is not a visible SANRO buyer editor; check staging editor page, enabled buyer flag and product 24 readiness.');
    }
    // Check editor markup before blaming the served asset. A missing script
    // tag must report a bootstrap issue, not a source SHA mismatch.
    const loadedScriptUrls = await page.locator('script[src]').evaluateAll(
      (elements, expected) => elements.map(node => node.src).filter(value => {
        try {
          const u = new URL(value);
          return u.origin === expected.origin && u.pathname === expected.pathname;
        } catch (_) { return false; }
      }), {origin:editor.origin, pathname:buyerAssetPath});
    assert.equal(loadedScriptUrls.length, 1,
      'Staging editor buyer.js script not enqueued exactly once');

    let buyerBytes = buyerAssetResponse
      ? await buyerAssetResponse.body().catch(() => null) : null;
    let buyerHttp = buyerAssetResponse?.status() ?? 0;
    let buyerAssetMode = buyerBytes ? 'browser_response' : 'same_origin_get';

    if (!buyerBytes) {
      // Same-origin read-only fallback uses the exact enqueued script URL,
      // including its WordPress cache-busting version. Never POST/write.
      const result = await page.evaluate(async url => {
        try {
          const r = await fetch(url, {method:'GET',credentials:'same-origin',
            cache:'no-store',redirect:'error'});
          return {http:r.status, bytes:r.ok ?
            [...new Uint8Array(await r.arrayBuffer())] : null};
        } catch (_) { return {http:0,bytes:null}; }
      }, loadedScriptUrls[0]);
      buyerHttp = result.http;
      buyerBytes = result.bytes ? Buffer.from(result.bytes) : null;
    }
    const browserBuyerJs = buyerBytes
      ? crypto.createHash('sha256').update(buyerBytes).digest('hex') : null;
    console.log('BUYER JS probe=' + buyerAssetMode
      + ' browser_sha256=' + (browserBuyerJs || 'missing')
      + ' expected_sha256=' + expectedBuyerJs
      + ' http=' + buyerHttp);
    assert(buyerHttp === 200 && browserBuyerJs !== null,
      'Staging buyer.js missing or unavailable over same-origin GET');
    assert.equal(browserBuyerJs, expectedBuyerJs,
      'Staging buyer.js bytes differ from pinned Git source; staging asset update required.');
    const capabilities = await page.evaluate(() => {
      const cfg = window.SANRO_STUDIO_EDITOR_CONFIG || {};
      return {
        product:Number(cfg.product_id),
        draft:Boolean(cfg.draft_api && cfg.draft_api.url && cfg.draft_api.nonce),
        save:Boolean(document.querySelector('#sanro-save-draft')),
        cart:Boolean(document.querySelector('#sanro-add-to-cart')),
        quote:Boolean(document.querySelector('#sanro-server-quote'))
      };
    });
    assert.equal(capabilities.product, 24, 'Unexpected product');
    assert(capabilities.draft && capabilities.save && capabilities.cart && capabilities.quote,
      'Missing draft, quote, or cart staging controls');
    console.log('PASS staging editor, draft, quote and cart controls available');

    if (!write) {
      console.log('READ-ONLY PASS: no draft, cart, order or payment was created.');
      console.log('To run the single synthetic buyer test set SANRO_STAGING_CART_E2E=1.');
      return;
    }

    if (priorDraft) {
      await page.waitForFunction(() =>
        /Draft(?: teks)? berhasil dibuka kembali\./i.test(
          document.querySelector('#sanro-save-status')?.textContent || ''),
        null,{timeout:25000});
      assert.equal(await page.locator('input[data-size="M"]').inputValue(),'1',
        'Reused draft quantity M differs from synthetic fixture');
      console.log('PASS existing private staging QA draft restored (no new draft)');
    } else {
      const front = page.locator('[data-side="front"]');
      assert.equal(await front.count(), 1, 'Front print side unavailable');
      await front.click();
      await page.locator('#sanro-category').selectOption('A3+');
      await page.locator('#sanro-font-size').evaluate(input => {
        input.value = '10';
        input.dispatchEvent(new Event('input', {bubbles:true}));
      });
      const marker = 'QA' + Date.now().toString(36).slice(-4);
      await page.locator('#sanro-text-input').fill(marker);
      await page.locator('#sanro-center-text').click();
      await page.locator('input[data-size="M"]').fill('1');
      await page.waitForFunction(() =>
        /Pratinjau sesuai batas/.test(
          document.querySelector('#sanro-validation')?.textContent || ''
        ), null, {timeout:15000});
      console.log('PASS DTF text geometry and size M quantity 1');
  
      await page.locator('#sanro-save-draft').click();
      await page.waitForFunction(() => {
        const s = document.querySelector('#sanro-save-status')?.textContent || '';
        return /Draft teks tersimpan|Penyimpanan gagal|Draft gagal|di luar area/i.test(s);
      }, null, {timeout:30000});
      const saveStatus = await page.locator('#sanro-save-status').innerText();
      assert.match(saveStatus, /Draft teks tersimpan/,
        'Draft save did not complete: ' + redact(saveStatus));
      console.log('PASS one synthetic staging draft saved');
      const savedState = await page.evaluate(() =>
        JSON.parse(sessionStorage.getItem('sanro-studio-draft:24') || 'null'));
      assert(savedState && typeof savedState.id==='string'
        && typeof savedState.token==='string',
        'Draft owner reference missing after save');
      savePrivateDraft(savedState.id,savedState.token);
      console.log('PASS private QA draft reference saved in owner-only VPS file');
    }

    // Read the quote inside the same browser session; return ONLY nonsensitive
    // numbers, never owner tokens, IDs or responses to the terminal.
    const quote = await page.evaluate(async () => {
      const cfg = window.SANRO_STUDIO_EDITOR_CONFIG;
      const state = JSON.parse(sessionStorage.getItem('sanro-studio-draft:24') || 'null');
      if (!state || !state.id || !state.token) return {valid:false, status:0};
      const response = await fetch(cfg.draft_api.url + '/' +
        encodeURIComponent(state.id) + '/quote', {
          method:'GET',
          credentials:'same-origin',
          headers:{
            'X-Sanro-Draft-Nonce':cfg.draft_api.nonce,
            'X-Sanro-Owner-Token':state.token
          }
        });
      if (!response.ok) return {valid:false, status:response.status};
      const data = await response.json();
      return {
        valid:data.valid === true && data.quote?.valid === true,
        total:data.quote?.total_price,
        currency:data.quote?.currency,
        quantity:data.quote?.total_quantity,
        shortId:state.id.slice(0,8)
      };
    });
    assert(quote.valid && Number.isSafeInteger(quote.total) && quote.total > 0
      && quote.quantity === 1 && quote.currency === 'IDR',
      'Server quote invalid or unavailable; status ' + (quote.status ?? 'n/a'));
    console.log('PASS authoritative draft quote: IDR ' + quote.total);

    // Save status may render before the handler's finally{} unlocks Save.
    // Wait for that completion instead of racing Cart against the save handler.
    await page.locator('#sanro-save-draft').waitFor({state:'visible'});
    await page.waitForFunction(() =>
      !document.querySelector('#sanro-save-draft')?.disabled,
      null, {timeout:5000});

    // Observe both the actual API request and any early UI validation
    // rejection. Never log the draft ID, owner token, nonce or request URL.
    const cartNetwork = {started:0, statuses:[], failed:[]};
    const isStagingCartPost = request => {
      try {
        const target = new URL(request.url());
        return request.method() === 'POST'
          && target.hostname === expectedHost
          && target.pathname.endsWith('/cart');
      } catch (_error) { return false; }
    };
    page.on('request', request => {
      if (isStagingCartPost(request)) cartNetwork.started += 1;
    });
    page.on('response', response => {
      if (isStagingCartPost(response.request()))
        cartNetwork.statuses.push(response.status());
    });
    page.on('requestfailed', request => {
      if (isStagingCartPost(request))
        cartNetwork.failed.push('network-request-failed');
    });
    const cartButton = page.locator('#sanro-add-to-cart');
    assert(await cartButton.isEnabled(), 'Add to Cart button is disabled');
    // Inspect direct handlers in Chromium without exposing credentials,
    // requests, or inline function bodies. CDP probe is read-only.
    async function countDirectCartClickListeners() {
      const cdp = await context.newCDPSession(page);
      try {
        const {root:doc} = await cdp.send('DOM.getDocument');
        const {nodeId} = await cdp.send('DOM.querySelector', {
          nodeId:doc.nodeId, selector:'#sanro-add-to-cart'
        });
        if (!nodeId) return -1;
        const {object} = await cdp.send('DOM.resolveNode',{nodeId});
        if (!object.objectId) return -1;
        const {listeners} = await cdp.send('DOMDebugger.getEventListeners',{
          objectId:object.objectId,depth:0
        });
        return listeners.filter(listener => listener.type === 'click').length;
      } catch (_) {
        return -1;
      } finally {
        await cdp.detach().catch(() => {});
      }
    }
    // Trace only nonsensitive DOM/interaction signals. The initial Cart status
    // in buyer.php is a placeholder, NOT proof that a click was rejected.
    const initialCart = await page.evaluate(() => {
      const button = document.querySelector('#sanro-add-to-cart');
      const status = document.querySelector('#sanro-cart-status');
      if (!button || !status) return {instrumented:false};
      const root = document.getElementById('sanro-studio-buyer');
      window.__sanroQaCartSignals = {
        clicked:0, targetBubbled:0, rootBubbled:0, statusChanges:0
      };
      button.addEventListener('click', () => {
        window.__sanroQaCartSignals.clicked += 1;
      }, {capture:true});
      button.addEventListener('click', () => {
        window.__sanroQaCartSignals.targetBubbled += 1;
      });
      root?.addEventListener('click', event => {
        if (event.target?.closest?.('#sanro-add-to-cart')) {
          window.__sanroQaCartSignals.rootBubbled += 1;
        }
      });
      new MutationObserver(() => {
        window.__sanroQaCartSignals.statusChanges += 1;
      }).observe(status, {subtree:true, childList:true, characterData:true});
      return {
        instrumented:true,
        rootContainsButton:!!root?.contains(button),
        duplicateButtonIds:document.querySelectorAll('#sanro-add-to-cart').length,
        buyerScriptPresent:[...document.scripts].some(script =>
          script.src.includes('/sanro-studio/assets/buyer.js')),
        buyerResourceLoaded:performance.getEntriesByType('resource').some(entry =>
          entry.name.includes('/sanro-studio/assets/buyer.js')),
        staticPlaceholder:(status.textContent || '').includes(
          'Simpan desain terlebih dahulu. Checkout memerlukan otorisasi ulang.')
      };
    });
    assert(initialCart.instrumented, 'Cart interaction instrumentation unavailable');
    const directCartClickHandlers = await countDirectCartClickListeners();
    const responseWait = page.waitForResponse(response =>
      isStagingCartPost(response.request()), {timeout:20000})
      .then(response => ({kind:'response', response}),
        () => ({kind:'timeout'}));
    await cartButton.click();
    const uiErrorWait = page.waitForFunction(() => {
      const text = document.querySelector('#sanro-cart-status')?.textContent || '';
      return /Simpan seluruh perubahan|Tunggu sampai|Draft belum tersedia|Desain berubah setelah draft|Keranjang belum siap|Gagal menambah|Draft.*kedaluwarsa/i.test(text);
    }, null, {timeout:20000})
      .then(() => ({kind:'ui-error'}), () => ({kind:'timeout'}));
    const cartOutcome = await Promise.race([responseWait, uiErrorWait]);
    if (cartOutcome.kind !== 'response') {
      const status = await page.locator('#sanro-cart-status').innerText({timeout:1500})
        .catch(() => 'Editor already navigated or Cart status unavailable');
      const signals = await page.evaluate(() => {
        const state = window.__sanroQaCartSignals || {};
        return {
          clicked:state.clicked ?? -1,
          targetBubbled:state.targetBubbled ?? -1,
          rootBubbled:state.rootBubbled ?? -1,
          statusChanges:state.statusChanges ?? -1
        };
      }).catch(() => ({clicked:-1,statusChanges:-1}));
      // This numeric-only line remains readable even if the redacted error
      // message below is truncated to 260 characters.
      console.log('CART PROBE direct=' + directCartClickHandlers
        + ' bubbled=' + signals.targetBubbled + '/' + signals.rootBubbled
        + ' root=' + Number(initialCart.rootContainsButton)
        + ' ids=' + initialCart.duplicateButtonIds
        + ' script=' + Number(initialCart.buyerScriptPresent)
        + ' resource=' + Number(initialCart.buyerResourceLoaded));
      throw new Error('Cart POST not observed; request_count=' + cartNetwork.started
        + '; http=' + cartNetwork.statuses.join(',')
        + '; network_fail=' + cartNetwork.failed.length
        + '; dom_click=' + signals.clicked
        + '; direct_click_handlers=' + directCartClickHandlers
        + '; target_bubbled=' + signals.targetBubbled
        + '; root_bubbled=' + signals.rootBubbled
        + '; status_changes=' + signals.statusChanges
        + '; root_contains=' + Number(initialCart.rootContainsButton)
        + '; button_ids=' + initialCart.duplicateButtonIds
        + '; buyer_script_loaded=' + Number(initialCart.buyerResourceLoaded)
        + '; buyer_script_tag=' + Number(initialCart.buyerScriptPresent)
        + '; initial_placeholder=' + Number(initialCart.staticPlaceholder)
        + '; js_errors=' + (jsErrors.join(',') || 'none')
        + '; cart_ui=' + redact(status));
    }
    const cartResponse = cartOutcome.response;
    if (cartResponse.status() !== 200) {
      const status = await page.locator('#sanro-cart-status').innerText({timeout:1500})
        .catch(() => 'UI changed after Cart POST');
      throw new Error('Cart REST returned HTTP ' + cartResponse.status()
        + '; cart_ui=' + redact(status));
    }
    await page.waitForURL(u =>
      u.hostname === expectedHost && /^\/cart\/?$/.test(u.pathname),
      {waitUntil:'domcontentloaded', timeout:30000});
    verifyHost(page);
    const cartRows = page.locator('.woocommerce-cart-form .cart_item');
    assert(await cartRows.count() >= 1, 'No WooCommerce Cart item');
    const cartSummary = await page.locator('.woocommerce-cart-form').innerText();
    assert(cartSummary.includes('Ukuran kaos') && cartSummary.includes('M')
      && cartSummary.includes('Kode desain') && cartSummary.includes(quote.shortId),
      'Cart is missing verified size/design details');
    const shownPrice = quote.total.toLocaleString('id-ID');
    const cartTotals = await page.locator('.cart_totals').innerText();
    assert(cartTotals.includes(shownPrice),
      'Cart totals do not display authoritative IDR quote');
    console.log('PASS WooCommerce Cart includes size M, design and IDR quote');

    // Navigate only; explicitly NO submit, checkout form submission, order or
    // gateway request is permitted in this smoke test.
    const next = page.locator('.checkout-button');
    assert(await next.count() >= 1, 'WooCommerce checkout link missing');
    await next.first().click();
    await page.waitForURL(u =>
      u.hostname === expectedHost && /^\/checkout\/?$/.test(u.pathname),
      {waitUntil:'domcontentloaded',timeout:30000});
    verifyHost(page);
    await page.locator('.woocommerce-checkout-review-order')
      .waitFor({timeout:15000});
    const checkout = await page.locator('.woocommerce-checkout-review-order').innerText();
    assert(checkout.includes('Ukuran kaos') && checkout.includes('Kode desain')
      && checkout.includes(quote.shortId) && checkout.includes(shownPrice),
      'Checkout lacks signed design details or server price');
    console.log('PASS checkout summary contains size, design and same IDR quote');
    console.log('PASS E2E staging editor -> Draft -> Cart -> Checkout (NO ORDER)');
    if (jsErrors.length) console.log('WARN browser JS exceptions (types): '+jsErrors.join(','));
  } finally {
    await context.close();
    await browser.close();
  }
})().catch(error => {
  console.error('FAIL staging E2E: ' + redact(error.message));
  process.exitCode = 1;
});
