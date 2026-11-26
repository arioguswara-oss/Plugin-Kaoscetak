const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.SANRO_TEST_CHROMIUM });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await page.setContent('<main id="sanro-studio-buyer"><header class="sanro-appbar"><div class="sanro-mini-cart-wrap"><a class="sanro-cart-link" href="/cart/" aria-haspopup="true" aria-expanded="false" aria-controls="sanro-mini-cart-panel" data-sanro-mini-cart-toggle>Keranjang</a><div id="sanro-mini-cart-panel" class="sanro-mini-cart-panel" data-sanro-mini-cart role="region" aria-label="Mini Cart" hidden><ul class="woocommerce-mini-cart"><li class="woocommerce-mini-cart-item"><img src="/preview.jpg" alt="Preview desain"><a href="/edit-design/">Edit Desain</a></li></ul><a class="sanro-mini-cart-view" href="/cart/">Lihat keranjang</a></div></div></header></main>');
    await page.addStyleTag({ path: path.join(__dirname, '../assets/buyer.css') });
    await page.addScriptTag({ path: path.join(__dirname, '../assets/studio-ui.js') });
    const toggle = page.locator('[data-sanro-mini-cart-toggle]');
    const panel = page.locator('[data-sanro-mini-cart]');
    assert.equal(await panel.isHidden(), true);
    await toggle.click();
    assert.equal(await panel.isVisible(), true);
    assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(await page.evaluate(() => document.activeElement?.className), 'sanro-mini-cart-view');
    assert.equal(await page.locator('.woocommerce-mini-cart-item img').getAttribute('src'), '/preview.jpg');
    await page.keyboard.press('Escape');
    assert.equal(await panel.isHidden(), true);
    assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
    await toggle.click();
    await page.mouse.click(380, 800);
    assert.equal(await panel.isHidden(), true);
    console.log('PASS: Mini Cart opens, exposes native preview and Cart link, and closes via Escape/outside click at 390px.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
