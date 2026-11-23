'use strict';
/**
 * Owner-only Hermes QA state for one staging DTF image draft.
 * Local file only; never commit owner tokens, cache them in logs or upload them.
 * Reusing an already authorized draft does not make a draft-creation request
 * and does not disable any server-side rate limits.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const HOST = 'saddlebrown-peafowl-616656.hostingersite.com';
const PRODUCT = 24;
const MAX_AGE_MS = 6 * 86400000;
function statePaths(home = process.env.HOME || '/home/sanro') {
  const dir = path.join(home, 'sanro-browser-qa');
  return {dir,file:path.join(dir,'staging-dtf-image-e2e-private.json')};
}
function ensurePrivateDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir,{recursive:true,mode:0o700});
  const st = fs.lstatSync(dir);
  assert(st.isDirectory() && !st.isSymbolicLink()
    && (!process.getuid || st.uid === process.getuid())
    && (st.mode & 0o077) === 0,
    'Unsafe staging DTF QA credential directory (requires owner and 0700)');
}
function validState(data,now=Date.now()) {
  return data && typeof data === 'object'
    && data.host === HOST && data.product === PRODUCT
    && typeof data.id === 'string'
    && /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(data.id)
    && typeof data.token === 'string' && /^[a-f0-9]{64}$/.test(data.token)
    && Number.isSafeInteger(data.createdAt)
    && data.createdAt <= now && now-data.createdAt < MAX_AGE_MS;
}
function readPrivateDraft(home) {
  const {dir,file}=statePaths(home);
  ensurePrivateDir(dir);
  assert(fs.existsSync(file), 'No saved private DTF QA draft; do not retry image E2E under HTTP 429');
  const st=fs.lstatSync(file);
  assert(st.isFile() && !st.isSymbolicLink()
    && (!process.getuid || st.uid === process.getuid())
    && (st.mode & 0o077) === 0,
    'Unsafe private DTF QA credential file (requires owner and 0600)');
  const data=JSON.parse(fs.readFileSync(file,'utf8'));
  assert(validState(data), 'Expired or invalid private DTF QA draft; new authorized creation required');
  return data;
}
/**
 * A prior successful text-only guest E2E has an owner-bound QA draft already.
 * Reusing its local credentials lets image QA avoid creating another draft
 * during a legitimate server-side guest rate-limit window. Explicit opt-in
 * and a server-side owner-bound GET preflight are required by the runner.
 * Never search other files or obtain customer data.
 */
function readTextQaDraft(home) {
  const {dir}=statePaths(home);
  ensurePrivateDir(dir);
  const file=path.join(dir,'staging-cart-e2e-private.json');
  assert(fs.existsSync(file),'No existing text-only staging QA draft to reuse');
  const st=fs.lstatSync(file);
  assert(st.isFile() && !st.isSymbolicLink()
    && (!process.getuid || st.uid===process.getuid())
    && (st.mode & 0o077)===0,
    'Unsafe existing text-only staging QA credentials');
  const data=JSON.parse(fs.readFileSync(file,'utf8'));
  assert(validState(data),'Invalid or expired text-only staging QA draft');
  return {id:data.id,token:data.token};
}
function savePrivateDraft(credentials,home) {
  const {dir,file}=statePaths(home);
  ensurePrivateDir(dir);
  const data={host:HOST,product:PRODUCT,id:credentials.id,
    token:credentials.token,createdAt:Date.now()};
  assert(validState(data), 'Invalid owner credentials; cannot persist QA draft');
  if (fs.existsSync(file)) {
    const old=fs.lstatSync(file);
    assert(old.isFile() && !old.isSymbolicLink()
      && (!process.getuid || old.uid === process.getuid())
      && (old.mode & 0o077) === 0,'Unsafe existing QA credential file');
  }
  const temp=file+'.'+process.pid+'.tmp';
  const fd=fs.openSync(temp,'wx',0o600);
  try {
    fs.writeFileSync(fd,JSON.stringify(data));
    fs.fsyncSync(fd);
  } finally { fs.closeSync(fd); }
  try { fs.renameSync(temp,file); }
  finally { try { fs.unlinkSync(temp); } catch (_) {} }
}
module.exports={statePaths,validState,readPrivateDraft,readTextQaDraft,savePrivateDraft};
