'use strict';
// Isolated regression of the *actual* delegated Cart handler from buyer.js.
// No WordPress connection, HTTP request, order, payment or browser required.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../assets/buyer.js'), 'utf8');
const startMarker = "  const cartButton=q('#sanro-add-to-cart'),cartStatus=q('#sanro-cart-status');";
const stopMarker = "  const saveDraft=async()=>{";
const start = source.indexOf(startMarker);
const stop = source.indexOf(stopMarker, start);
assert(start !== -1 && stop > start, 'Cart handler source boundaries changed');
const block = source.slice(start, stop);

function createHarness(options = {}) {
  const oldButton = {disabled: false};
  const visibleButton = {disabled: false};
  const status = {textContent: 'initial placeholder'};
  const requests = [];
  let listener;
  let redirectedTo = null;
  const root = {
    addEventListener(type, handler) {
      assert.equal(type, 'click', 'Unexpected listener');
      assert(!listener, 'Cart handler installed twice');
      listener = handler;
    },
    contains(node) { return node === visibleButton; }
  };
  const config = {draft_api: {url:'https://staging.invalid/drafts', nonce:'qa-nonce', rest_nonce:options.restNonce}};
  const context = {
    root, config,
    q(selector) {
      return selector === '#sanro-add-to-cart' ? oldButton :
        selector === '#sanro-cart-status' ? status : null;
    },
    saving: options.saving === true,
    uploadingMaster: options.uploadingMaster === true,
    savedId: options.savedId === undefined ? 'synthetic-draft' : options.savedId,
    ownerToken: options.ownerToken === undefined ? 'local-test-token' : options.ownerToken,
    editRevision: options.editRevision ?? 1,
    savedRevision: options.savedRevision ?? 1,
    cartPending: false,
    designReady() {
      return !!context.savedId && !!context.ownerToken
        && context.editRevision === context.savedRevision && !options.pendingImage;
    },
    saveDraft:async()=>{context.savedRevision=context.editRevision;return !options.pendingImage;},
    fetch(url, request) {
      requests.push({url, request});
      if (options.onFetch) return options.onFetch();
      return Promise.resolve({
        ok: true, status:200, json:async()=>({valid:true, cart_url:'/cart/'})
      });
    },
    window: {SanroStudioSafeCartUrl: () => 'https://staging.invalid/cart/'},
    location: {
      href:'https://staging.invalid/custom/',
      assign(url) { redirectedTo = url; }
    },
    encodeURIComponent
  };
  vm.runInNewContext(block, context, {timeout:1000});
  assert.equal(typeof listener, 'function', 'Cart handler missing');
  return {
    oldButton, visibleButton, status, requests,
    redirect() { return redirectedTo; },
    click(node = visibleButton) {
      return listener({target: {closest: () => node}});
    }
  };
}

(async () => {
  let checks = 0;
  const pass = () => { checks++; };

  const replaced = createHarness();
  await replaced.click();
  assert.equal(replaced.requests.length, 1, 'Replaced button did not trigger POST');
  assert.equal(replaced.requests[0].request.method, 'POST');
  assert(replaced.requests[0].url.endsWith('/synthetic-draft/cart'));
  assert.equal(replaced.redirect(), 'https://staging.invalid/cart/');
  assert.equal(replaced.visibleButton.disabled, false);
  assert.equal(replaced.oldButton.disabled, false);
  pass();

  assert.equal(replaced.requests[0].request.headers['X-WP-Nonce'],undefined,'Legacy guest configuration remains supported');
  const signedIn=createHarness({restNonce:'signed-in-rest-nonce'});
  await signedIn.click();
  assert.equal(signedIn.requests[0].request.headers['X-WP-Nonce'],'signed-in-rest-nonce');
  assert.equal(signedIn.requests[0].request.headers['X-Sanro-Draft-Nonce'],'qa-nonce');
  assert.equal(signedIn.requests[0].request.headers['X-Sanro-Owner-Token'],'local-test-token');
  pass();

  const unrelated = createHarness();
  await unrelated.click(null);
  assert.equal(unrelated.requests.length, 0, 'Unrelated click posted to Cart');
  pass();

  const unsaved = createHarness({savedId:null});
  await unsaved.click();
  assert.equal(unsaved.requests.length, 0, 'Unsaved design posted to Cart');
  assert.match(unsaved.status.textContent, /Tambahkan desain/);
  pass();

  const dirty = createHarness({editRevision:2, savedRevision:1});
  await dirty.click();
  assert.equal(dirty.requests.length, 1, 'Dirty design is saved before Cart');
  assert.match(dirty.status.textContent, /Desain masuk keranjang/);
  pass();

  const saving = createHarness({saving:true});
  await saving.click();
  assert.equal(saving.requests.length, 0, 'Cart submitted while draft save pending');
  pass();

  const uploading = createHarness({uploadingMaster:true});
  await uploading.click();
  assert.equal(uploading.requests.length, 0, 'Cart submitted while master upload pending');
  pass();

  const unbound = createHarness({pendingImage:true});
  await unbound.click();
  assert.equal(unbound.requests.length, 0, 'Unbound image unexpectedly reached Cart');
  assert.equal(unbound.status.textContent, '', 'Old Cart guidance remained visible');
  pass();

  const rejected = createHarness({onFetch:async()=>({
    ok:false, status:409, json:async()=>({valid:false})
  })});
  await rejected.click();
  assert.equal(rejected.requests.length, 1);
  assert.equal(rejected.status.textContent,'Keranjang belum bisa dibuka. Coba lagi.');
  assert.equal(rejected.visibleButton.disabled, false, 'Button left disabled after failure');
  assert.equal(rejected.redirect(), null, 'Rejected Cart unexpectedly redirected');
  pass();

  let release;
  const pending = createHarness({onFetch:()=>new Promise(resolve => { release = resolve; })});
  const first = pending.click();
  await pending.click();
  assert.equal(pending.requests.length, 1, 'Overlapping Cart POST was not blocked');
  assert.equal(pending.visibleButton.disabled, true);
  release({ok:true,status:200,json:async()=>({valid:true,cart_url:'/cart/'})});
  await first;
  assert.equal(pending.visibleButton.disabled, false, 'Button was not re-enabled');
  pass();

  console.log('PASS: ' + checks + ' delegated Cart handler regression checks');
})().catch(error => {
  console.error('FAIL delegated Cart handler:', error.message);
  process.exitCode = 1;
});
