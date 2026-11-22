'use strict';
// SANRO Studio: offline auth-state security and browser integration contract.
// Uses only synthetic fixture cookies; never reads real owner credentials.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {HOST, ORIGIN, assertSafeStorageState} =
  require('./lib/staging-admin-guards.cjs');

const source = fs.readFileSync(
  path.join(__dirname,'staging-cart-checkout-e2e.cjs'),'utf8');
let checks = 0;
function verify(ok, label) {
  assert(ok, label);
  checks++;
}
verify(source.includes("const {assertSafeStorageState} = require('./lib/staging-admin-guards.cjs');"),
  'uses existing strict staging admin storageState validator');
verify(source.includes('SANRO_STAGING_BUYER_AUTH_STATE') &&
  source.includes("SANRO_STUDIO_QA_ENV !== 'staging'"),
  'requires opt-in plus exact staging mode');
verify(source.includes('validatedAuthState = assertSafeStorageState(authStatePath)') &&
  source.includes('storageState:validatedAuthState'),
  'validated private state wired to Playwright, not a raw path');
verify(source.includes('const write = process.env.SANRO_STAGING_CART_E2E === \'1\'') &&
  source.includes('if (!write)'),
  'default remains read-only');
verify(source.includes('BUYER_STAGING_SESSION_MODE: ') &&
  source.includes('EXISTING_AUTHENTICATED'),
  'safe session mode reporting excludes private values');
verify(!source.includes("console.log('AUTH_STATE_PATH'") &&
  !source.includes('console.log(validatedAuthState)'),
  'no credentials or state path printed');

const dir = fs.mkdtempSync(path.join(os.tmpdir(),'sanro-buyer-auth-policy-'));
const file = path.join(dir,'state.json');
const fixture = {
  cookies:[{name:'sanro_qa_fixture',value:'synthetic-only',domain:HOST,path:'/'}],
  origins:[{origin:ORIGIN,localStorage:[]}]
};
try {
  fs.chmodSync(dir,0o700);
  fs.writeFileSync(file,JSON.stringify(fixture),{mode:0o600});
  verify(assertSafeStorageState(file) === file,
    'valid staging-only synthetic state is accepted');
  verify(assert.throws(()=>assertSafeStorageState('relative/state.json')) === undefined,
    'relative paths are rejected');
  fs.chmodSync(file,0o644);
  verify(assert.throws(()=>assertSafeStorageState(file)) === undefined,
    'world-readable state rejected');
  fs.chmodSync(file,0o600);
  fs.writeFileSync(file,JSON.stringify({...fixture,cookies:[
    {...fixture.cookies[0],domain:'kaoscetak.id'}]}));
  verify(assert.throws(()=>assertSafeStorageState(file)) === undefined,
    'production-domain cookie rejected');
  fs.writeFileSync(file,JSON.stringify({...fixture,origins:[
    {origin:'https://kaoscetak.id',localStorage:[]}]}));
  verify(assert.throws(()=>assertSafeStorageState(file)) === undefined,
    'production origin rejected');
  fs.writeFileSync(file,JSON.stringify(fixture));
  const symlink = path.join(dir,'symlink.json');
  fs.symlinkSync(file,symlink);
  verify(assert.throws(()=>assertSafeStorageState(symlink)) === undefined,
    'symlink state rejected');
  fs.chmodSync(dir,0o755);
  verify(assert.throws(()=>assertSafeStorageState(file)) === undefined,
    'world-readable parent directory rejected');
} finally {
  fs.chmodSync(dir,0o700);
  fs.rmSync(dir,{recursive:true,force:true});
}
console.log('PASS: '+checks+' SANRO authenticated staging buyer E2E safeguards');
