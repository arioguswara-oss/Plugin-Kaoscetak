'use strict';
/** Offline, deterministic security checks: no Playwright or external network. */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const g=require('./lib/staging-admin-guards.cjs');

let passed=0;
function pass(test,label){assert(test,label);passed++;}
const stage='https://'+g.HOST;
pass(g.requestAllowed('GET',stage+'/wp-admin/'),'admin GET');
pass(g.requestAllowed('HEAD',stage+'/wp-admin/admin.php?page=wc-orders'),'admin HEAD');
pass(g.requestAllowed('GET',stage+'/wp-admin/admin.php?page=wc-orders&action=edit&id=17'),'HPOS edit GET');
pass(g.requestAllowed('GET',stage+'/wp-admin/post.php?post=17&action=edit'),'classic Woo order GET');
pass(g.requestAllowed('GET',stage+'/wp-json/wp/v2/'),'WP REST GET');
pass(g.requestAllowed('GET',stage+'/wp-content/plugins/sanro-studio/assets/admin.js'),'plugin asset GET');
pass(!g.requestAllowed('POST',stage+'/wp-admin/admin-ajax.php'),'POST blocked');
pass(!g.requestAllowed('PUT',stage+'/wp-json/wc/v3/orders/17'),'PUT blocked');
pass(!g.requestAllowed('DELETE',stage+'/wp-json/wc/v3/orders/17'),'DELETE blocked');
pass(!g.requestAllowed('GET',stage+'/wp-admin/admin.php?action=delete'),'GET delete blocked');
pass(!g.requestAllowed('GET',stage+'/wp-admin/admin.php?doaction=trash'),'bulk trash blocked');
pass(!g.requestAllowed('GET',stage+'/wp-admin/admin-post.php'),'admin-post GET blocked');
pass(!g.requestAllowed('GET',stage+'/wp-admin/admin-ajax.php?action=foo'),'admin-ajax GET blocked');
pass(!g.requestAllowed('GET','https://kaoscetak.id/wp-admin/'),'production blocked');
pass(!g.requestAllowed('GET','http://'+g.HOST+'/wp-admin/'),'non-TLS staging blocked');
pass(!g.requestAllowed('GET','https://evil.example/wp-admin/'),'foreign blocked');
pass(!g.requestAllowed('GET','https://'+g.HOST+'.evil.example/'),'host suffix blocked');
pass(!g.requestAllowed('GET','data:text/plain,secret'),'data URLs not web requests');
pass(g.safeOrderId('124')===124,'valid staging ID');
pass(g.safeOrderId('0')===null && g.safeOrderId('-1')===null,'zero/negative denied');
pass(g.safeOrderId('1e3')===null && g.safeOrderId('01')===null,'ambiguous IDs denied');
pass(g.safeOrderId('9999999999')===null,'overlong ID denied');

const dir=fs.mkdtempSync(path.join(os.tmpdir(),'sanro-s139-auth-'));
fs.chmodSync(dir,0o700);
const file=path.join(dir,'state.json');
function createState(cookieDomain=g.HOST,origin=g.ORIGIN) {
  fs.writeFileSync(file,JSON.stringify({
    cookies:[{name:'wordpress_logged_in_dummy',value:'not-a-real-cookie',domain:cookieDomain}],
    origins:[{origin,localStorage:[]}]
  }),{mode:0o600});
}
try {
  createState();
  pass(g.assertSafeStorageState(file)===file,'private owner staging session accepted');
  fs.chmodSync(file,0o644);
  let forbidden=false;
  try{g.assertSafeStorageState(file)}catch(_){forbidden=true}
  pass(forbidden,'world-readable cookies refused');
  fs.chmodSync(file,0o600);
  createState('kaoscetak.id');
  forbidden=false;try{g.assertSafeStorageState(file)}catch(_){forbidden=true}
  pass(forbidden,'production cookie refused');
  createState(g.HOST,'https://kaoscetak.id');
  forbidden=false;try{g.assertSafeStorageState(file)}catch(_){forbidden=true}
  pass(forbidden,'production localStorage refused');
  createState();
  const link=path.join(dir,'alias.json');
  fs.symlinkSync(file,link);
  forbidden=false;try{g.assertSafeStorageState(link)}catch(_){forbidden=true}
  pass(forbidden,'symlink cookie file refused');
  forbidden=false;try{g.assertSafeStorageState('state.json')}catch(_){forbidden=true}
  pass(forbidden,'relative state path refused');
  fs.chmodSync(dir,0o755);
  forbidden=false;try{g.assertSafeStorageState(file)}catch(_){forbidden=true}
  pass(forbidden,'world-readable state directory refused');
} finally {
  fs.chmodSync(dir,0o700);
  fs.rmSync(dir,{recursive:true,force:true});
}
pass(!g.redactedIssue(new Error('Secret: TOKEN=123')).includes('TOKEN'),
  'sensitive exception is not emitted to logs');
console.log('PASS: '+passed+' SANRO staging admin Playwright offline security guards');
