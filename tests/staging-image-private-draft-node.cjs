'use strict';
/** Isolated reusable-DTF-credential safety tests; synthetic tokens only. */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {statePaths,validState,readPrivateDraft,savePrivateDraft}=
  require('./staging-image-private-draft.cjs');
const home=fs.mkdtempSync(path.join(os.tmpdir(),'sanro-private-image-state-'));
let checks=0;
function check(fn,name){fn();checks++;}
const state={host:'saddlebrown-peafowl-616656.hostingersite.com',product:24,
  id:'11111111-1111-4111-8111-111111111111',token:'a'.repeat(64),
  createdAt:Date.now()};
try {
  check(()=>assert.equal(validState(state),true),'valid owner draft');
  check(()=>assert.equal(validState({...state,host:'kaoscetak.id'}),false),'different host blocked');
  check(()=>assert.equal(validState({...state,product:25}),false),'wrong product blocked');
  check(()=>assert.equal(validState({...state,token:'not-a-secret'}),false),'bad token blocked');
  check(()=>assert.equal(validState({...state,id:'not-a-uuid'}),false),'bad UUID blocked');
  check(()=>assert.equal(validState({...state,createdAt:Date.now()-7*86400000}),false),
    'expired draft blocked');
  check(()=>assert.equal(validState({...state,createdAt:Date.now()+60000}),false),
    'future timestamp blocked');
  check(()=>assert.throws(()=>readPrivateDraft(home),/No saved private DTF QA draft/),
    'missing state fails closed without creating draft');
  savePrivateDraft(state,home);
  const {dir,file}=statePaths(home);
  check(()=>assert.equal(fs.statSync(dir).mode&0o077,0),
    'directory mode 0700');
  check(()=>assert.equal(fs.statSync(file).mode&0o077,0),
    'file mode 0600');
  check(()=>assert.equal(readPrivateDraft(home).token,state.token),
    'readback matches owner token');
  fs.chmodSync(file,0o644);
  check(()=>assert.throws(()=>readPrivateDraft(home),/Unsafe private DTF QA credential/),
    'public-readable state refused');
  fs.chmodSync(file,0o600);
  fs.unlinkSync(file);
  fs.symlinkSync(path.join(home,'missing'),file);
  check(()=>assert.throws(()=>readPrivateDraft(home)),
    'file symlink refused');
  fs.unlinkSync(file);
  savePrivateDraft(state,home);
  check(()=>assert.equal(readPrivateDraft(home).id,state.id),
    'valid private credentials reusable');
  check(()=>assert.equal(fs.readdirSync(dir).length,1),
    'atomic credential file has no leaked temporary files');
  console.log('PASS: '+checks+' owner-private DTF QA reuse and permission checks');
} finally {
  fs.rmSync(home,{recursive:true,force:true});
}
