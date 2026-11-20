'use strict';
/**
 * Read-only staging release gate. Never logs page HTML, REST bodies, nonce
 * values, cookies, or design credentials. No order/cart/payment mutations.
 * Requires Node.js 18+ and an already installed, authorized staging plugin.
 */
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const STAGING_HOST = 'saddlebrown-peafowl-616656.hostingersite.com';
const PAGE = 'https://' + STAGING_HOST + '/custom-2/?sanro_studio_editor=1&product_id=24';
const EDITOR_CONFIG = 'https://' + STAGING_HOST +
  '/wp-json/sanro-studio/v1/products/24/editor-config';
const BUYER_ASSET_PATH = '/wp-content/plugins/sanro-studio/assets/buyer.js';

function assertStagingUrl(input) {
  const url = new URL(input);
  assert(url.protocol === 'https:' && url.hostname === STAGING_HOST &&
    !url.username && !url.password && !url.port, 'Non-staging request blocked');
  return url;
}
function attr(tag, name) {
  const re = new RegExp('(?:^|\\s)' + name + '\\s*=\\s*(?:"([^"]*)"|\\x27([^\\x27]*)\\x27)', 'i');
  const match = tag.match(re);
  return match ? (match[1] ?? match[2]) : null;
}
function decodeAttribute(value) {
  return value.replace(/&amp;|&#0*38;|&#x0*26;/gi, '&');
}
function findBuyerAsset(html) {
  for (const match of html.matchAll(/<script\b[^>]*>/gi)) {
    if (attr(match[0], 'id') === 'sanro-studio-buyer-js') {
      const src = attr(match[0], 'src');
      assert(src, 'Buyer script has no src attribute');
      return decodeAttribute(src);
    }
  }
  throw Error('Expected buyer script was not found in staging page');
}
/**
 * Read-only public UI gate check; reports button presence, never page HTML or
 * plugin secrets. Storage safety is independently validated server-side.
 */
function visibleDtfGates(html) {
  const hasButton = id => [...html.matchAll(/<button\b[^>]*>/gi)]
    .some(m => attr(m[0], 'id') === id);
  return {
    draft:hasButton('sanro-save-draft'),
    cart:hasButton('sanro-add-to-cart'),
    master:hasButton('sanro-upload-master'),
    previewOptional:hasButton('sanro-upload-previews')
  };
}
function expectedVersion(source, pluginPhp) {
  const version = pluginPhp.match(/define\('SANRO_STUDIO_VERSION',\s*'([^']+)'\)/);
  assert(version, 'Plugin version constant unavailable');
  return version[1] + '-' + crypto.createHash('sha256')
    .update(source).digest('hex').slice(0, 12);
}
async function probe({read, source, pluginPhp}) {
  const page = assertStagingUrl(PAGE);
  const pageResult = await read(page, 3 * 1024 * 1024);
  assert.equal(pageResult.status, 200, 'Staging editor page HTTP not 200');
  const html = pageResult.bytes.toString('utf8');
  const asset = assertStagingUrl(new URL(findBuyerAsset(html), page).href);
  assert.equal(asset.pathname, BUYER_ASSET_PATH, 'Unexpected buyer asset path');
  const version = expectedVersion(source, pluginPhp);
  assert.equal(asset.searchParams.get('ver'), version,
    'Staging buyer.js cache version is stale or not fingerprinted');
  const assetResult = await read(asset, 2 * 1024 * 1024);
  assert.equal(assetResult.status, 200, 'Staging buyer asset HTTP not 200');
  const localHash = crypto.createHash('sha256').update(source).digest('hex');
  const remoteHash = crypto.createHash('sha256').update(assetResult.bytes).digest('hex');
  assert.equal(remoteHash, localHash, 'Staging browser-loaded asset differs from branch');
  const configResult = await read(assertStagingUrl(EDITOR_CONFIG), 1024 * 1024);
  assert.equal(configResult.status, 200, 'Staging editor-config REST HTTP not 200');
  return {page:200, asset:200, editorConfig:200, hash:localHash, version, gates:visibleDtfGates(html)};
}
async function readOnlyGet(url, maxBytes) {
  assertStagingUrl(url.href);
  const response = await fetch(url.href, {
    method:'GET', redirect:'manual', cache:'no-store',
    credentials:'omit', signal:AbortSignal.timeout(15000)
  });
  const size = Number(response.headers.get('content-length') || 0);
  assert(size <= maxBytes, 'Staging response exceeds size limit');
  const reader = response.body?.getReader();
  assert(reader, 'Missing staging response body');
  const chunks = [];
  let total = 0;
  while (true) {
    const {value,done} = await reader.read();
    if (done) break;
    total += value.length;
    assert(total <= maxBytes, 'Staging response exceeds size limit');
    chunks.push(Buffer.from(value));
  }
  return {status:response.status, bytes:Buffer.concat(chunks)};
}
if (require.main === module) {
  (async () => {
    const root = path.resolve(__dirname, '..');
    const source = fs.readFileSync(path.join(root, 'plugins/sanro-studio/assets/buyer.js'));
    const pluginPhp = fs.readFileSync(path.join(root, 'plugins/sanro-studio/sanro-studio.php'), 'utf8');
    const result = await probe({read:readOnlyGet, source, pluginPhp});
    console.log('PASS: staging read-only asset + REST release gate');
    console.log('HTTP page=' + result.page + ' buyer=' + result.asset
      + ' editor-config=' + result.editorConfig);
    console.log('BUYER_JS_SHA256=' + result.hash);
    console.log('BUYER_JS_VERSION=' + result.version);
    console.log('STAGING DTF UI GATES: '+Object.entries(result.gates)
      .map(([gate,enabled])=>gate+'='+(enabled?'ON':'OFF')).join(' '));
  })().catch(error => {
    console.error('FAIL: staging preflight: ' + String(error.message).slice(0, 200));
    process.exitCode = 1;
  });
}
module.exports = {assertStagingUrl,findBuyerAsset,visibleDtfGates,expectedVersion,probe};
