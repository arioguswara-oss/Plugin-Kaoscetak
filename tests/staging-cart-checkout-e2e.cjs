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
    viewport:{width:390,height:844}, locale:'id-ID',
    acceptDownloads:false
  });
  const page = await context.newPage();
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
    await page.locator('#sanro-studio-buyer #sanro-text-input')
      .waitFor({timeout:15000});
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

    // Keep the POST inside the editor's real code path; never forge cart lines
    // or supply a client-provided price to the API.
    const responseWait = page.waitForResponse(response =>
      response.request().method() === 'POST'
        && new URL(response.url()).pathname.endsWith('/cart')
        && new URL(response.url()).hostname === expectedHost,
      {timeout:30000});
    await page.locator('#sanro-add-to-cart').click();
    const cartResponse = await responseWait;
    assert.equal(cartResponse.status(), 200,
      'Cart REST returned HTTP ' + cartResponse.status());
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
