'use strict';
/**
 * Sprint 140: staging-only session-capture policy.
 * Pure validation and private local file write; NEVER place cookies in git.
 */
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');

const HOST='saddlebrown-peafowl-616656.hostingersite.com';
const ORIGIN='https://'+HOST;
const STATE_DIR='/home/sanro/.local/state/sanro-studio-browser-qa';
const STATE_FILE=STATE_DIR+'/staging-admin.json';

function allowedLoginRequest(method,requestUrl) {
  let url;
  try { url=new URL(requestUrl); } catch (_) { return false; }
  if (url.origin!==ORIGIN || !['GET','HEAD','POST'].includes(method)) return false;
  if(method==='POST') return url.pathname==='/wp-login.php' &&
    (!url.search || url.search==='?action=login');
  if(url.pathname.endsWith('/admin-post.php') ||
     url.pathname.endsWith('/admin-ajax.php')) return false;
  for(const key of ['action','action2','doaction','doaction2']) {
    const action=url.searchParams.get(key);
    if(action!==null && !['login','edit','view'].includes(action)) return false;
  }
  return true;
}
function validStagingAdminState(state) {
  if(!state || !Array.isArray(state.cookies) ||
     state.cookies.length===0 ||
     !state.cookies.some(c => typeof c?.name==='string' &&
       c.name.startsWith('wordpress_logged_in_'))) return false;
  for(const cookie of state.cookies){
    const domain=String(cookie?.domain||'').replace(/^\./,'').toLowerCase();
    if(domain!==HOST || typeof cookie?.value!=='string' || !cookie.value) return false;
  }
  if(state.origins!==undefined && (!Array.isArray(state.origins) ||
     state.origins.some(o=>!o || o.origin!==ORIGIN))) return false;
  return true;
}
function checkPrivateDirectory(directory=STATE_DIR){
  if(!path.isAbsolute(directory)) throw Error('INVALID_STATE_DIRECTORY');
  fs.mkdirSync(directory,{recursive:true,mode:0o700});
  const st=fs.lstatSync(directory);
  if(!st.isDirectory() || st.isSymbolicLink() ||
     (st.mode&0o077)!==0 ||
     (process.getuid && st.uid!==process.getuid())) {
    throw Error('UNSAFE_STATE_DIRECTORY');
  }
  return directory;
}
function saveStagingAdminState(state,filename=STATE_FILE){
  if(!validStagingAdminState(state)) throw Error('INVALID_STAGING_SESSION');
  const parent=path.dirname(filename);
  checkPrivateDirectory(parent);
  if(fs.existsSync(filename)||fs.existsSync(filename+'.tmp')) {
    throw Error('STATE_ALREADY_EXISTS');
  }
  const tmp=path.join(parent,'.staging-session-'+crypto.randomBytes(12).toString('hex')+'.tmp');
  try {
    fs.writeFileSync(tmp,JSON.stringify(state),{mode:0o600,flag:'wx'});
    const st=fs.lstatSync(tmp);
    if(!st.isFile() || st.isSymbolicLink() ||
      (st.mode&0o077)!==0 ||
      (process.getuid && st.uid!==process.getuid())) {
      throw Error('UNSAFE_STATE_TEMPFILE');
    }
    if(fs.existsSync(filename)) throw Error('STATE_ALREADY_EXISTS');
    fs.renameSync(tmp,filename);
  }finally{
    if(fs.existsSync(tmp))fs.unlinkSync(tmp);
  }
  return filename;
}
module.exports={HOST,ORIGIN,STATE_DIR,STATE_FILE,allowedLoginRequest,
  validStagingAdminState,checkPrivateDirectory,saveStagingAdminState};
