'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const estimate=require('../plugins/sanro-studio/assets/estimate.js');
const cart=require('../plugins/sanro-studio/assets/cart-redirect.js');
test('official examples and two-side symmetry across sizes/categories',()=>{
 assert.equal(estimate.unit('S','A5',null),62000);
 assert.equal(estimate.unit('S','A5',null,true),72000);
 assert.equal(estimate.unit('L','A3','A5'),103000);
 for(const size of ['S','M','L','XL','2XL','3XL','4XL','5XL'])
  for(const a of ['A5','A4','A3','A3+'])for(const b of ['A5','A4','A3','A3+'])
   assert.equal(estimate.unit(size,a,b),estimate.unit(size,b,a));
});
test('quantity validation and aggregate ceiling',()=>{
 assert.deepEqual(estimate.estimate({S:2,L:1},'A5','A3'),{pieces:3,total:297000,currency:'IDR'});
 for(const q of [{S:-1},{S:1.5},{S:'1'},{S:1000},{bogus:1},{S:0},[],null])assert.equal(estimate.estimate(q,'A5',null),null);
 assert.equal(estimate.estimate({S:999,M:999,L:999,XL:999,'2XL':999,'3XL':999},'A5',null),null);
});
test('cart destinations reject off-origin, script, credentials and control characters',()=>{
 const origin='https://shop.example.test/product/tee/';
 assert.equal(cart('/cart/',origin),'https://shop.example.test/cart/');
 for(const url of ['https://evil.example/cart','//evil.example/cart','javascript:alert(1)','https://user:pass@shop.example.test/cart','/cart/\n','\\evil.example/cart'])assert.equal(cart(url,origin),null);
});
