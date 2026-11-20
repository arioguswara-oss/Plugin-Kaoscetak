'use strict';
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {assertStagingUrl, findBuyerAsset, expectedVersion, probe} =
  require('../scripts/sanro-studio-staging-preflight.cjs');

const origin = 'https://saddlebrown-peafowl-616656.hostingersite.com';
const source = Buffer.from("'use strict';\n// synthetic buyer QA fixture\n");
const pluginPhp = "define('SANRO_STUDIO_VERSION', '0.1.1-dev');";
const version = expectedVersion(source, pluginPhp);
const sha = crypto.createHash('sha256').update(source).digest('hex');
const assetPath = '/wp-content/plugins/sanro-studio/assets/buyer.js';
const pageHtml = '<script id="sanro-studio-buyer-js" src="' +
  origin + assetPath + '?ver=' + version + '"></script>';
function stub({html=pageHtml, bytes=source, configStatus=200, assetStatus=200}={}) {
  const visited = [];
  const read = async url => {
    visited.push(url.href);
    if (url.pathname === '/custom-2/') {
      return {status:200,bytes:Buffer.from(html)};
    }
    if (url.pathname === assetPath) {
      return {status:assetStatus,bytes};
    }
    if (url.pathname.endsWith('/editor-config')) {
      return {status:configStatus,bytes:Buffer.from('{}')};
    }
    throw Error('Unexpected URL');
  };
  return {read, visited};
}
(async()=>{
  let checks=0;
  const good=stub();
  const result=await probe({read:good.read,source,pluginPhp});
  assert.equal(good.visited.length,3,'Only 3 GET targets expected');
  assert.equal(result.hash,sha);
  assert.equal(result.editorConfig,200);
  checks++;

  assert.equal(findBuyerAsset("<script src='/x.js' id='sanro-studio-buyer-js'></script>"),'/x.js');
  assert.equal(findBuyerAsset("<script id='sanro-studio-buyer-js' src='/buyer.js?ver=a&amp;id=b'></script>"),
    '/buyer.js?ver=a&id=b');
  checks++;

  assert.throws(()=>assertStagingUrl('https://evil.example/cart/'));
  assert.throws(()=>assertStagingUrl('http://saddlebrown-peafowl-616656.hostingersite.com/cart/'));
  assert.throws(()=>assertStagingUrl('https://user:pass@saddlebrown-peafowl-616656.hostingersite.com/'));
  checks++;

  const stale=stub({html:pageHtml.replace(version,'0.1.1-dev')});
  await assert.rejects(()=>probe({read:stale.read,source,pluginPhp}),/cache version/);
  assert.equal(stale.visited.length,1,'Stale version must fail before fetching asset');
  checks++;

  const wrongHost=stub({html:pageHtml.replace(origin,'https://evil.example')});
  await assert.rejects(()=>probe({read:wrongHost.read,source,pluginPhp}),/Non-staging/);
  assert.equal(wrongHost.visited.length,1);
  checks++;

  const badHash=stub({bytes:Buffer.from('stale js')});
  await assert.rejects(()=>probe({read:badHash.read,source,pluginPhp}),/differs from branch/);
  assert.equal(badHash.visited.length,2,'Bad asset must fail before REST probe');
  checks++;

  const badRest=stub({configStatus:500});
  await assert.rejects(()=>probe({read:badRest.read,source,pluginPhp}),/REST HTTP/);
  assert.equal(badRest.visited.length,3);
  checks++;

  const missing=stub({html:'<html><body>no buyer script</body></html>'});
  await assert.rejects(()=>probe({read:missing.read,source,pluginPhp}),/not found/);
  checks++;

  console.log('PASS: '+checks+' staging preflight regression groups');
})().catch(error=>{
  console.error('FAIL staging preflight regression: '+error.message);
  process.exitCode=1;
});
