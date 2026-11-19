'use strict';
const assert=require('node:assert/strict');
const price=require('../plugins/sanro-studio/assets/estimate.js');
const sizes=['S','M','L','XL','2XL','3XL','4XL','5XL'];
const cats=['A5','A4','A3','A3+'];
const table=[[62000,72000,82000,87000],[65000,75000,85000,90000],[68000,78000,88000,93000],[72000,82000,92000,97000],[77000,87000,97000,102000],[80000,90000,100000,105000],[83000,93000,103000,108000],[85000,95000,105000,110000]];
const extras=[15000,25000,35000,40000];
let checks=0;
for(let i=0;i<sizes.length;i++){
 for(let j=0;j<cats.length;j++){
  assert.equal(price.unit(sizes[i],cats[j],null),table[i][j]);checks++;
  assert.equal(price.unit(sizes[i],null,cats[j]),table[i][j]);checks++;
  assert.equal(price.unit(sizes[i],cats[j],null,true),table[i][j]+10000);checks++;
  for(let k=0;k<cats.length;k++){
   const expected=table[i][Math.max(j,k)]+extras[Math.min(j,k)];
   assert.equal(price.unit(sizes[i],cats[j],cats[k]),expected);checks++;
   assert.equal(price.unit(sizes[i],cats[k],cats[j]),expected);checks++;
  }
 }
}
assert.deepEqual(price.estimate({S:2,XL:3},'A4',null),{pieces:5,total:390000,currency:'IDR'});checks++;
for(const result of [
 price.unit('S',null,null),price.unit('unknown','A5',null),price.unit('S','BAD',null),
 price.unit('S','A5',null,1),price.estimate({S:-1},'A5',null),
 price.estimate({S:1.5},'A5',null),price.estimate({S:1000},'A5',null),
 price.estimate({BAD:1},'A5',null),price.estimate(null,'A5',null),
 price.estimate({},'A5',null),price.estimate({S:0,M:0},'A5',null),
 price.estimate({S:999,M:999,L:999,XL:999,'2XL':999,'3XL':6},'A5',null),
]){assert.equal(result,null);checks++;}
assert.equal(price.estimate({S:999,M:999,L:999,XL:999,'2XL':999,'3XL':5},'A5',null).pieces,5000);checks++;
console.log('SANRO estimator: '+checks+' assertions PASS, 0 FAIL');
