// Runs the actual editor closure with minimal DOM doubles; not browser/layout QA.
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../plugins/kaoscetak-lite-designer/assets/editor.js'),'utf8');
assert.match(source,/\}\)\(\);\s*$/);
const instrumented=source.replace(/\}\)\(\);\s*$/,'globalThis.kldTest={pricePerSize,xss,jsonSide,sides,api};})();');
const regular={S:[62000,72000,82000,87000],M:[65000,75000,85000,90000],L:[68000,78000,88000,93000],XL:[72000,82000,92000,97000],'2XL':[77000,87000,97000,102000],'3XL':[80000,90000,100000,105000],'4XL':[83000,93000,103000,108000],'5XL':[85000,95000,105000,110000]};
const tiers=['A5','A4','A3','A3+'],extras=[15000,25000,35000,40000];
function load(matrix,options={}){
 const root={querySelector:()=>null},button={addEventListener(){}};const calls=[];
 const cfg={productType:'reguler-24s',productId:901,sizes:Object.keys(matrix),colors:['Hitam'],matrix,tiers:Object.fromEntries(tiers.map(t=>[t,[32,48]])),surcharges:Object.fromEntries(tiers.map((t,i)=>[t,extras[i]])),rest:'https://fixture.invalid/kld/v1/',nonce:'',...options};
 const sandbox={window:{KLD_CONFIG:cfg,location:{hash:''}},document:{getElementById:id=>id==='kld-open-designer'?button:root},fetch:async(url,args)=>{calls.push({url,args});return {ok:true,json:async()=>({saved:true})};}};
 vm.runInNewContext(instrumented,sandbox,{filename:'actual-editor.js'});return {...sandbox.kldTest,calls};
}
function tariff(rows,extra=0){return Object.fromEntries(Object.entries(rows).map(([s,v])=>[s,Object.fromEntries(tiers.map((t,i)=>[t,v[i]+extra]))]));}
for(const [label,rows,supplement] of [['regular',regular,0],['long sleeves',regular,10000],['tunic',{M:[85000,95000,105000,110000],L:[90000,100000,110000,115000],XL:[95000,105000,115000,120000]},0]]){
 test('actual editor tariff: '+label,()=>{
  const e=load(tariff(rows,supplement));
  for(const [size,prices] of Object.entries(rows))for(let i=0;i<4;i++){
   e.sides.front.tier=tiers[i];e.sides.back.tier='none';assert.equal(e.pricePerSize(size),prices[i]+supplement);
   e.sides.front.tier='none';e.sides.back.tier=tiers[i];assert.equal(e.pricePerSize(size),prices[i]+supplement);
   for(let j=0;j<4;j++){e.sides.front.tier=tiers[i];e.sides.back.tier=tiers[j];assert.equal(e.pricePerSize(size),prices[Math.max(i,j)]+supplement+extras[Math.min(i,j)]);}
  }
 });
}
test('editor empty, missing size and mug prices',()=>{
 const e=load(tariff(regular));assert.equal(e.pricePerSize('L'),0);e.sides.front.tier='A3+';e.sides.back.tier='A4';assert.equal(e.pricePerSize('L'),118000);assert.equal(e.pricePerSize('INVALID'),0);
 const m=load({ONE:{MUG:25000}},{productType:'mug-11oz',tiers:{MUG:[18,8.5]}});assert.equal(m.pricePerSize('ONE'),25000);assert.equal(m.pricePerSize('L'),0);
});
test('editor escapes HTML and serializes layout without upload capabilities',()=>{
 const e=load(tariff(regular));assert.equal(e.xss('<script>"&\''),'&lt;script&gt;&quot;&amp;&#39;');
 const s=e.sides.front;s.tier='A5';s.placement={dx:1.234,dy:2};s.image={x:1,y:2,w:3,h:4};s.uploadSession={url:'private-fixture-capability'};
 const data=JSON.parse(JSON.stringify(e.jsonSide(s)));assert.equal(data.placement.center_offset_cm,1.23);assert.equal(data.placement.top_from_collar_cm,12.83);assert.deepEqual(data.image,{x:1,y:2,w:3,h:4});assert.equal(JSON.stringify(data).includes('private-fixture-capability'),false);
});
test('editor REST nonce handling and same-origin credentials',async()=>{
 const e=load(tariff(regular));await e.api('save',{color:'Hitam'});assert.equal(e.calls[0].args.credentials,'same-origin');assert.equal(Object.hasOwn(e.calls[0].args.headers,'X-WP-Nonce'),false);
 const a=load(tariff(regular),{nonce:'fixture-only-nonce'});await a.api('save',{});assert.equal(a.calls[0].args.headers['X-WP-Nonce'],'fixture-only-nonce');assert.equal(a.calls[0].url,'https://fixture.invalid/kld/v1/save');
});
