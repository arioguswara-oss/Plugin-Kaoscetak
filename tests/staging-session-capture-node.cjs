'use strict';
/** Offline no-network safety checks for one-time staging WordPress login. */
const fs=require('node:fs');
const path=require('node:path');
const os=require('node:os');
const p=require('./lib/staging-session-capture-policy.cjs');
let count=0;
function check(condition,label){if(!condition)throw Error('FAIL: '+label);count++;}
const url=p.ORIGIN;
check(p.allowedLoginRequest('GET',url+'/wp-login.php'),'staging login GET');
check(p.allowedLoginRequest('HEAD',url+'/wp-login.php'),'staging login HEAD');
check(p.allowedLoginRequest('POST',url+'/wp-login.php'),'one staging login POST');
check(p.allowedLoginRequest('POST',url+'/wp-login.php?action=login'),'staging explicit login POST');
check(p.allowedLoginRequest('GET',url+'/wp-admin/'),'admin read GET');
check(p.allowedLoginRequest('GET',url+'/wp-content/plugins/sanro-studio/assets/buyer.js'),'asset GET');
check(!p.allowedLoginRequest('POST',url+'/wp-admin/admin-ajax.php'),'ajax write denied');
check(!p.allowedLoginRequest('POST',url+'/wp-json/wc/v3/orders'),'order write denied');
check(!p.allowedLoginRequest('PUT',url+'/wp-login.php'),'PUT denied');
check(!p.allowedLoginRequest('DELETE',url+'/wp-login.php'),'DELETE denied');
check(!p.allowedLoginRequest('GET',url+'/wp-admin/admin-post.php'),'admin post GET denied');
check(!p.allowedLoginRequest('GET',url+'/wp-admin/admin.php?action=delete'),'GET delete denied');
check(!p.allowedLoginRequest('GET',url+'/wp-admin/plugins.php?action=activate'),'GET activation denied');
check(!p.allowedLoginRequest('GET','https://kaoscetak.id/wp-admin/'),'production origin denied');
check(!p.allowedLoginRequest('POST','https://kaoscetak.id/wp-login.php'),'production login denied');
check(!p.allowedLoginRequest('GET','http://'+p.HOST+'/wp-login.php'),'non-HTTPS denied');
check(!p.allowedLoginRequest('GET','https://'+p.HOST+'.evil.example/wp-login.php'),'host suffix denied');

const good=()=>({
  cookies:[{name:'wordpress_logged_in_dummy',value:'fixture-only',domain:p.HOST}],
  origins:[{origin:p.ORIGIN,localStorage:[]}]
});
check(p.validStagingAdminState(good()),'staging login cookie accepted');
check(!p.validStagingAdminState({cookies:[],origins:[]}), 'empty cookie refused');
const badName=good();badName.cookies[0].name='wordpress_test_cookie';
check(!p.validStagingAdminState(badName),'test cookie alone not authorization');
const wrongSite=good();wrongSite.cookies[0].domain='kaoscetak.id';
check(!p.validStagingAdminState(wrongSite),'production cookie refused');
const wrongOrigin=good();wrongOrigin.origins[0].origin='https://kaoscetak.id';
check(!p.validStagingAdminState(wrongOrigin),'production localStorage refused');
const extraCookie=good();extraCookie.cookies.push({name:'other',value:'secret',domain:'external.example'});
check(!p.validStagingAdminState(extraCookie),'foreign cookie refused');
const emptyToken=good();emptyToken.cookies[0].value='';
check(!p.validStagingAdminState(emptyToken),'empty cookie refused');

const root=fs.mkdtempSync(path.join(os.tmpdir(),'sanro-stage-session-test-'));
fs.chmodSync(root,0o700);
try{
  const folder=path.join(root,'browser-state');
  check(p.checkPrivateDirectory(folder)===folder,'create private directory');
  check((fs.statSync(folder).mode&0o077)===0,'directory permissions 0700');
  const target=path.join(folder,'session.json');
  check(p.saveStagingAdminState(good(),target)===target,'private state persisted');
  check((fs.statSync(target).mode&0o077)===0,'cookie file permissions 0600');
  check(p.validStagingAdminState(JSON.parse(fs.readFileSync(target,'utf8'))),'cookie state re-read');
  let threw=false;
  try{p.saveStagingAdminState(good(),target)}catch(_){threw=true}
  check(threw,'existing state never silently overwritten');
  fs.chmodSync(folder,0o755);
  threw=false;try{p.checkPrivateDirectory(folder)}catch(_){threw=true}
  check(threw,'world-readable directory rejected');
  fs.chmodSync(folder,0o700);
  const link=path.join(root,'link');
  fs.symlinkSync(folder,link);
  threw=false;try{p.checkPrivateDirectory(link)}catch(_){threw=true}
  check(threw,'symlink directory rejected');
}finally{fs.rmSync(root,{recursive:true,force:true})}
console.log('PASS: '+count+' SANRO secure staging login session policy checks');
