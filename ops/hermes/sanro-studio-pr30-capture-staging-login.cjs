'use strict';
/**
 * One-time SANRO Studio staging wp-admin login capture on Hermes (interactive).
 * Username/password are typed directly in the owner's SSH terminal.
 * NO credentials in command arguments, GitHub, environment, process logs or
 * WordPress production. Stores only staging-bound Playwright storageState.
 *
 * Usage:
 * NODE_PATH=/home/sanro/sanro-browser-qa/node_modules \
 *   SANRO_STUDIO_QA_ENV=staging node ops/hermes/sanro-studio-pr30-capture-staging-login.cjs
 */
const os=require('node:os');
const fs=require('node:fs');
const readline=require('node:readline');
const {ORIGIN,STATE_FILE,allowedLoginRequest,validStagingAdminState,
  saveStagingAdminState}=require('../../tests/lib/staging-session-capture-policy.cjs');

function block(message) {const e=new Error(message);e.blocked=true;throw e;}
if(process.env.SANRO_STUDIO_QA_ENV!=='staging' ||
   os.hostname().split('.')[0]!=='vmi3646685' ||
   os.userInfo().username!=='sanro' ||
   !process.stdin.isTTY || !process.stdout.isTTY) {
  console.error('BLOCKED: interactive sanro@Hermes staging-only terminal required');
  process.exit(2);
}
if(fs.existsSync(STATE_FILE)){
  console.error('BLOCKED: staging admin browser state already exists; never overwrite automatically');
  process.exit(2);
}
let chromium;
try {({chromium}=require('playwright'))}
catch (_) {
  console.error('BLOCKED: existing Hermes Playwright module unavailable');
  process.exit(2);
}
async function askUsername(){
  const rl=readline.createInterface({input:process.stdin,output:process.stdout});
  try {
    return await new Promise(resolve=>rl.question('Username WordPress STAGING: ',resolve));
  } finally {rl.close()}
}
async function askHiddenPassword(){
  return new Promise((resolve,reject)=>{
    const input=process.stdin,output=process.stdout;
    if(!input.setRawMode)return reject(Error('TTY_UNSUPPORTED'));
    output.write('Password STAGING (tidak terlihat): ');
    let answer='';
    const wasRaw=input.isRaw===true;
    const finish=(error)=>{
      input.off('data',onData);
      input.setRawMode(wasRaw);
      output.write('\n');
      if(error)reject(error);else resolve(answer);
    };
    const onData=chunk=>{
      for(const char of chunk.toString('utf8')){
        if(char==='\u0003' || char==='\u0004')return finish(Error('INPUT_CANCELLED'));
        if(char==='\r' || char==='\n')return finish(null);
        if(char==='\b' || char==='\u007f'){
          answer=answer.slice(0,-1);continue;
        }
        if(char>=' ' && answer.length<1024)answer+=char;
      }
    };
    input.setRawMode(true);
    input.resume();
    input.on('data',onData);
  });
}
async function main(){
  const options={headless:true};
  if(process.env.SANRO_CHROMIUM_EXECUTABLE){
    options.executablePath=process.env.SANRO_CHROMIUM_EXECUTABLE;
  }
  const browser=await chromium.launch(options);
  try {
    const context=await browser.newContext({acceptDownloads:false,
      viewport:{width:1366,height:900},locale:'id-ID'});
    let loginSubmits=0;
    await context.route('**/*',async route=>{
      const req=route.request();
      const approved=allowedLoginRequest(req.method(),req.url());
      if(!approved)return route.abort('blockedbyclient');
      if(req.method()==='POST'){
        loginSubmits++;
        if(loginSubmits>1)return route.abort('blockedbyclient');
      }
      return route.continue();
    });
    const page=await context.newPage();
    const landing=await page.goto(ORIGIN+'/wp-login.php',{
      waitUntil:'domcontentloaded',timeout:30000
    });
    if(!landing || landing.status()!==200 ||
       !await page.locator('#user_login').count() ||
       !await page.locator('#user_pass').count() ||
       !await page.locator('#wp-submit').count()) {
      block('WordPress staging login form unavailable (custom/SSO/2FA may need separate method)');
    }
    let username=await askUsername();
    if(!username || username.length>128)block('Invalid staging username');
    let password=await askHiddenPassword();
    if(!password || password.length>1024)block('Empty or malformed staging password');
    await page.locator('#user_login').fill(username);
    username='';
    await page.locator('#user_pass').fill(password);
    password='';
    // Login is the single allowed POST; all other writes are blocked.
    await page.locator('#wp-submit').click({timeout:15000});
    try{
      await page.waitForURL(url=>url.origin===ORIGIN &&
        url.pathname.startsWith('/wp-admin/'),{timeout:30000});
    }catch(_){
      block('WordPress login did not reach wp-admin; 2FA/SSO/invalid credentials possible');
    }
    await page.locator('#adminmenu').waitFor({state:'attached',timeout:15000})
      .catch(()=>block('WordPress administrator navigation not available'));
    const snapshot=await context.storageState();
    if(!validStagingAdminState(snapshot))block('Staging login cookie not verified');
    saveStagingAdminState(snapshot);
    console.log('SESSION_CAPTURE: PASS');
    console.log('SESSION_FILE: '+STATE_FILE);
    console.log('STAGING_ONLY: YES');
    console.log('CREDENTIALS_LOGGED: NO');
    console.log('NEXT: run PR29 read-only browser QA using this owner-only state');
  }finally{
    await browser.close();
  }
}
main().catch(error=>{
  if(error && error.blocked){
    console.error('BLOCKED: staging admin login/session not verified');
    process.exitCode=2;
  }else{
    console.error('BLOCKED: interactive staging login could not complete');
    process.exitCode=2;
  }
});
