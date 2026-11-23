'use strict';
/* Display-only estimate. WooCommerce must recalculate and validate all prices. */
(function(root,factory){
 const api=factory();
 if(typeof module==='object'&&module.exports)module.exports=api;
 else root.SanroStudioEstimate=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(){
 const sizes=['S','M','L','XL','2XL','3XL','4XL','5XL'];
 const categories=['A5','A4','A3','A3+'];
 const prices=[[62000,72000,82000,87000],[65000,75000,85000,90000],[68000,78000,88000,93000],[72000,82000,92000,97000],[77000,87000,97000,102000],[80000,90000,100000,105000],[83000,93000,103000,108000],[85000,95000,105000,110000]];
 const extras=[15000,25000,35000,40000];
 function unit(size,front,back,longSleeve=false,profile=null){
  if(profile){
   if(!Array.isArray(profile.sizes)||!profile.sizes.includes(size)||!profile.table||!Array.isArray(profile.table[size])||profile.table[size].length!==4||profile.table[size].some(p=>!Number.isSafeInteger(p)||p<1)||typeof profile.sleeve_extra!=='number'||(profile.fixed_sleeve&&longSleeve))return null;
  }
  const i=sizes.indexOf(size);
  if(i<0||typeof longSleeve!=='boolean')return null;
  const active=[front,back].filter(x=>x!==null);
  if(!active.length||active.length>2||active.some(x=>!categories.includes(x)))return null;
  const sorted=active.map(x=>categories.indexOf(x)).sort((a,b)=>b-a);
  return (profile?profile.table[size][sorted[0]]:prices[i][sorted[0]])+(sorted.length===2?extras[sorted[1]]:0)+(longSleeve?(profile?profile.sleeve_extra:10000):0);
 }
 function estimate(qty,front,back,longSleeve=false,profile=null){
  if(!qty||typeof qty!=='object'||Array.isArray(qty))return null;
  let total=0,pieces=0;
  for(const [size,count] of Object.entries(qty)){
   if(!sizes.includes(size)||!Number.isSafeInteger(count)||count<0||count>999)return null;
   const p=unit(size,front,back,longSleeve,profile);
   if(p===null)return null;
   pieces+=count;
   if(pieces>5000)return null;
   total+=count*p;
  }
  if(pieces===0)return null;
  return {pieces,total,currency:'IDR'};
 }
 return Object.freeze({unit,estimate});
});
