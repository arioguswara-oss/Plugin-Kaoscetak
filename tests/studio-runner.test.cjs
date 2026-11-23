'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
test('runner separates offline/support/integration and propagates failures',()=>{
 const root=fs.mkdtempSync(path.join(os.tmpdir(),'studio-runner-'));
 try {
  const write=(name,text)=>{const file=path.join(root,name);fs.mkdirSync(path.dirname(file),{recursive:true});fs.writeFileSync(file,text);return file;};
  const exec=(cmd,args,env=process.env)=>spawnSync(cmd,args,{cwd:root,env,encoding:'utf8'});
  const runner=write('tests/run-all.sh',fs.readFileSync(path.join(__dirname,'run-all.sh')));
  write('tests/studio-fixture-offline.php','<?php // synthetic');
  write('tests/fixture.test.cjs',"console.log('synthetic Node PASS');");
  write('tests/staging-buyer-e2e.cjs',"throw Error('MUST NOT RUN integration');");
  write('tests/lib/helper.cjs',"throw Error('MUST NOT RUN support');");
  const php=write('bin/php','#!/bin/sh\n[ "$1" = "-l" ] && exit 0\n[ "${FAIL_PHP:-0}" = "1" ] && exit 23\nexit 0\n');
  fs.chmodSync(php,0o755);
  assert.equal(exec('git',['init','-q']).status,0);
  assert.equal(exec('git',['add','tests']).status,0);
  assert.equal(exec('git',['-c','user.name=Fixture','-c','user.email=fixture@example.test','commit','-qm','synthetic']).status,0);
  const sha=exec('git',['rev-parse','HEAD']).stdout.trim();
  const env={...process.env,PATH:path.join(root,'bin')+path.delimiter+process.env.PATH};
  let run=exec('/bin/bash',[runner],env);
  assert.equal(run.status,0,run.stderr); assert.equal(run.stdout,`PASS ${sha}\n`);
  assert.match(run.stderr,/NOT TESTED tests\/staging-buyer-e2e.cjs/);
  assert.doesNotMatch(run.stderr,/RUN node tests\/lib\/helper/);
  run=exec('/bin/bash',[runner],{...env,FAIL_PHP:'1'});
  assert.equal(run.status,1); assert.equal(run.stdout,`FAIL ${sha}\n`);
  write('tests/unknown.php','<?php');
  run=exec('/bin/bash',[runner],env);
  assert.equal(run.status,1); assert.match(run.stderr,/unclassified executable/);
  fs.unlinkSync(path.join(root,'tests/unknown.php'));
  write('plugins/synthetic/tests/smoke.php','<?php');
  run=exec('/bin/bash',[runner],env);
  assert.equal(run.status,0,run.stderr); assert.match(run.stderr,/RUN php plugins\/synthetic\/tests\/smoke.php/);
  fs.unlinkSync(php);
  // A controlled PATH proves that missing PHP fails even when the host has PHP installed.
  for(const command of ['git','dirname','sort','mktemp','rm','node']){
   const target=exec('/bin/bash',['-c','command -v '+command]).stdout.trim();
   fs.symlinkSync(target,path.join(root,'bin',command));
  }
  run=exec('/bin/bash',[runner],{...process.env,PATH:path.join(root,'bin')});
  assert.equal(run.status,1); assert.match(run.stderr,/MISSING php/); assert.equal(run.stdout,`FAIL ${sha}\n`);
 } finally {fs.rmSync(root,{recursive:true,force:true});}
});
