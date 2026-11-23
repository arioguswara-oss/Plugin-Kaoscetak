'use strict';
/* Cross-runtime regression: compare the actual PHP pricing module with the actual browser estimator. */
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
const estimate=require('../plugins/sanro-studio/assets/estimate.js');
const pricing=path.resolve(__dirname,'../plugins/sanro-studio/includes/pricing.php');
const sizes=['S','M','L','XL','2XL','3XL','4XL','5XL'];
const cats=[null,'A5','A4','A3','A3+'];
const cases=[];
for(const size of sizes){
 for(const front of cats){
  for(const back of cats){
   for(const sleeve of [false,true]){
    cases.push({size,front,back,sleeve});
   }
  }
 }
}
const php=String.raw`define('ABSPATH', __DIR__); require $argv[1]; $cases=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); $out=[]; foreach($cases as $c){$p=sanro_studio_24s_unit_price($c['size'],$c['front'],$c['back'],$c['sleeve']);$out[]=empty($p['valid'])?null:$p['unit_price'];} echo json_encode($out,JSON_THROW_ON_ERROR);`;
const run=spawnSync('php',['-r',php,pricing],{input:JSON.stringify(cases),encoding:'utf8',maxBuffer:1024*1024});
if(run.error)throw run.error;
assert.equal(run.status,0,run.stderr||'PHP pricing execution failed');
const actual=JSON.parse(run.stdout);
assert.equal(actual.length,cases.length);
for(let i=0;i<cases.length;i++){
 const c=cases[i];
 assert.equal(estimate.unit(c.size,c.front,c.back,c.sleeve),actual[i],
  'PHP/JS mismatch: '+JSON.stringify(c));
}
console.log('SANRO PHP/JS pricing parity: '+cases.length+' cases PASS, 0 FAIL');
