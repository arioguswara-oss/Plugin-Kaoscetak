// Runs the actual editor closure with minimal DOM doubles; not browser/layout QA.
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../plugins/kaoscetak-lite-designer/assets/editor.js'),'utf8');
assert.match(source,/\}\)\(\);\s*$/);
const instrumented=source.replace(/\}\)\(\);\s*$/,'globalThis.kldTest={pricePerSize,xss,jsonSide,sides,api,template,editState,remember,changeEdit,stepHistory,resetHistory,histories,selection,effectiveDpi,snapLayer,stageMetrics,renderThumb,pointerDown,pointerMove,pointerUp,mockupUrl,loadMockup,mockupCache};})();');
const regular={S:[62000,72000,82000,87000],M:[65000,75000,85000,90000],L:[68000,78000,88000,93000],XL:[72000,82000,92000,97000],'2XL':[77000,87000,97000,102000],'3XL':[80000,90000,100000,105000],'4XL':[83000,93000,103000,108000],'5XL':[85000,95000,105000,110000]};
const tiers=['A5','A4','A3','A3+'],extras=[15000,25000,35000,40000];
function load(matrix,options={}){
 const calls=[],nodes=new Map(),paint=[];let doc,lastExport=[];
 const ctx=new Proxy({measureText:text=>({width:String(text).length*60}),createLinearGradient:()=>({addColorStop(){}})}, {get:(o,k)=>k in o?o[k]:(...args)=>paint.push({method:k,args,strokeStyle:o.strokeStyle}),set:(o,k,v)=>(o[k]=v,true)});
 function element(key=''){return {key,value:'',dataset:{},disabled:false,hidden:false,checked:true,tagName:'BUTTON',textContent:'',listeners:{},classList:{toggle(){},add(){},remove(){}},addEventListener(type,fn){(this.listeners[type]??=[]).push(fn);},emit(type,data={}){for(const fn of this.listeners[type]||[])fn({type,target:this,currentTarget:this,preventDefault(){},...data});},focus(){if(doc)doc.activeElement=this;},getClientRects(){return this.hidden?[]:[{}];},getBoundingClientRect(){return {left:0,top:0,width:600,height:710};},setPointerCapture(){},getContext:()=>ctx,width:600,height:710,toDataURL(){lastExport=paint.slice();return 'data:image/jpeg;base64,c3ludGhldGlj';},remove(){}};}
 const button=element('open'),root={innerHTML:'',querySelector(key){if(!nodes.has(key))nodes.set(key,element(key));return nodes.get(key);},querySelectorAll(key){if(key==='[data-side]')return sideButtons;if(key==='[data-qty]')return qtyInputs;if(key==='[data-align]')return alignButtons;if(key==='[data-nudge]')return nudges;if(key==='.kld-swatch')return [];return [...nodes.values()];}};
 const cfg={productType:'reguler-24s',productId:901,sizes:Object.keys(matrix),colors:['Hitam'],matrix,tiers:Object.fromEntries(tiers.map(t=>[t,[32,48]])),surcharges:Object.fromEntries(tiers.map((t,i)=>[t,extras[i]])),rest:'https://fixture.invalid/kld/v1/',nonce:'',...options};
 const sideButtons=['front','back'].map(side=>Object.assign(element(),{dataset:{side}})),qtyInputs=cfg.sizes.map((qty,i)=>Object.assign(element(),{dataset:{qty},value:i===0?'1':'0'})),alignButtons=['x','y'].map(align=>Object.assign(element(),{dataset:{align}})),nudges=['left','up','down','right'].map(nudge=>Object.assign(element(),{dataset:{nudge}}));
 doc={getElementById:id=>id==='kld-open-designer'?button:root,createElement:()=>element(),body:{classList:{add(){},remove(){}}}};
 class FixtureImage{set src(value){this.url=value;[this.naturalWidth,this.naturalHeight]=options.imageDimensions||[600,710];queueMicrotask(()=>this.onload?.());}}
 const sandbox={window:{KLD_CONFIG:cfg,location:{hash:'',href:'https://fixture.invalid/product/',origin:'https://fixture.invalid'}},Image:FixtureImage,URL,queueMicrotask,document:doc,fetch:async(url,args)=>{calls.push({url,args});return {ok:true,json:async()=>({saved:true})};}};
 vm.runInNewContext(instrumented,sandbox,{filename:'actual-editor.js'});return {...sandbox.kldTest,calls,root,sideButtons,nudges,alignButtons,paint,getLastExport:()=>lastExport,node:key=>root.querySelector(key)};
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
test('Undo/Redo restores typed text through actual editor event handlers',()=>{
 const e=load(tariff(regular));e.template();e.node('.kld-tier').value='A5';e.node('.kld-tier').emit('change');
 const text=e.node('.kld-text');text.value='KAOS';text.emit('input');text.value='KAOSCETAK';text.emit('input');
 assert.equal(e.sides.front.text.value,'KAOSCETAK');e.node('.kld-undo').emit('click');assert.equal(e.sides.front.text,null);e.node('.kld-redo').emit('click');assert.equal(e.sides.front.text.value,'KAOSCETAK');
});
test('history is bounded metadata and never rewinds master or upload state',()=>{
 const e=load(tariff(regular)),s=e.sides.front;s.tier='A5';s.upload='done';s.file={privateFile:'sensitive-file'};s.uploadSession={url:'private-capability'};s.imagePixels={bitmap:{huge:true}};
 for(let i=0;i<45;i++)e.changeEdit(()=>s.placement.dx=i+1);
 assert.equal(e.histories.front.past.length,30);const serial=JSON.stringify(e.histories);for(const value of ['sensitive-file','private-capability','bitmap','upload'])assert.equal(serial.includes(value),false);
 const pixels=s.imagePixels,file=s.file;e.stepHistory();assert.equal(s.placement.dx,44);assert.equal(s.upload,'done');assert.equal(s.imagePixels,pixels);assert.equal(s.file,file);
});
test('fresh edit after Undo invalidates redo; new master resets only that side',()=>{
 const e=load(tariff(regular));e.changeEdit(()=>e.sides.front.placement.dx=1);e.stepHistory();e.changeEdit(()=>e.sides.front.placement.dy=2);assert.equal(e.stepHistory(true),false);e.resetHistory('front');assert.equal(e.stepHistory(),false);
});
test('side switching preserves independent histories, category and server layout',()=>{
 const e=load(tariff(regular));e.template();e.node('.kld-tier').value='A4';e.node('.kld-tier').emit('change');e.sideButtons[1].emit('click');e.node('.kld-tier').value='A3';e.node('.kld-tier').emit('change');e.node('.kld-undo').emit('click');assert.equal(e.sides.back.tier,'none');assert.equal(e.sides.front.tier,'A4');e.sideButtons[0].emit('click');e.node('.kld-undo').emit('click');assert.equal(e.sides.front.tier,'none');e.node('.kld-redo').emit('click');assert.equal(e.sides.front.tier,'A4');assert.equal(e.jsonSide(e.sides.front).tier,'A4');
});
test('context alignment, deletion and Undo retain pixels for an image',()=>{
 const e=load(tariff(regular));e.template();const s=e.sides.front;s.tier='A5';s.image={x:1,y:2,w:4,h:4,aspect:1};s.imagePixels={bitmap:{}};s.sourcePixels={width:1200,height:1200};
 e.node('.kld-position-layer').value='image';e.node('.kld-position-layer').emit('change');assert.equal(e.node('.kld-context').hidden,false);e.alignButtons[0].emit('click');assert.equal(s.image.x,14);e.node('.kld-delete-layer').emit('click');assert.equal(s.image,null);assert.equal(e.node('.kld-context').hidden,true);e.node('.kld-undo').emit('click');assert.equal(s.image.x,14);assert.ok(s.imagePixels.bitmap);
});
test('text fields keep native keyboard Undo; canvas keyboard Undo uses editor history',()=>{
 const e=load(tariff(regular));e.template();e.changeEdit(()=>e.sides.front.placement.dx=2);
 e.node('.kld-overlay').emit('keydown',{key:'z',ctrlKey:true,target:{tagName:'TEXTAREA'}});assert.equal(e.sides.front.placement.dx,2);
 e.node('.kld-overlay').emit('keydown',{key:'z',ctrlKey:true,target:{tagName:'CANVAS'}});assert.equal(e.sides.front.placement.dx,0);
 e.node('.kld-overlay').emit('keydown',{key:'z',metaKey:true,shiftKey:true,target:{tagName:'CANVAS'}});assert.equal(e.sides.front.placement.dx,2);
});
test('Snap centers near objects without altering dimensions or tier; toggle disables it',()=>{
 const e=load(tariff(regular)),m={tierW:20,tierH:20,safeX0:0,safeX1:20,safeY0:0,safeY1:20},l={x:7.8,y:7.7,w:4,h:4};
 let r=e.snapLayer(l,m,null,.5);assert.equal(r.x,8);assert.equal(r.y,8);assert.equal(r.guides.length,2);assert.equal(l.w,4);assert.equal(l.x,7.8);
 r=e.snapLayer(l,m,null,.5,false);assert.equal(r.x,7.8);assert.equal(r.guides.length,0);
 r=e.snapLayer({...l,x:4,y:4},m,null,.5);assert.equal(r.x,4);assert.equal(r.y,4);
});
test('Snap respects category and garment safe bounds across many positions',()=>{
 const e=load(tariff(regular)),m={tierW:20,tierH:20,safeX0:2,safeX1:18,safeY0:1,safeY1:19};
 for(let n=-40;n<=240;n++){const r=e.snapLayer({x:n/10,y:(200-n)/10,w:4,h:3},m,{x:7,y:7,w:3,h:3},.6);assert.ok(r.x>=2&&r.x<=14);assert.ok(r.y>=1&&r.y<=16);}
});
test('pointer gesture is one history action; pointer cancellation restores it',()=>{
 const e=load(tariff(regular));e.template();const s=e.sides.front;s.tier='A5';s.image={x:1,y:1,w:4,h:4,aspect:1};s.imagePixels={bitmap:{}};const m=e.stageMetrics(),canvas=e.node('.kld-stage');
 const event={pointerId:3,currentTarget:canvas,clientX:m.px+2*m.scale,clientY:m.py+2*m.scale};e.pointerDown(event);
 for(let i=1;i<=10;i++)e.pointerMove({...event,clientX:event.clientX+i*m.scale});e.pointerUp({...event,type:'pointerup'});assert.equal(e.histories.front.past.length,1);assert.notEqual(s.image.x,1);e.stepHistory();assert.equal(s.image.x,1);
 e.pointerDown(event);e.pointerMove({...event,clientX:event.clientX+4*m.scale});e.pointerUp({...event,type:'pointercancel'});assert.equal(s.image.x,1);assert.equal(e.histories.front.past.length,0);
});
test('effective DPI uses original pixels at actual cm, never preview pixels',()=>{
 const e=load(tariff(regular)),s=e.sides.front;s.image={w:25.4,h:12.7};s.imagePixels={w:1100,h:550};assert.equal(e.effectiveDpi(s),null);
 s.sourcePixels={width:3000,height:1500};assert.equal(e.effectiveDpi(s),300);s.image.h=25.4;assert.equal(e.effectiveDpi(s),150);s.image.h=50.8;assert.equal(e.effectiveDpi(s),75);s.sourcePixels.width=NaN;assert.equal(e.effectiveDpi(s),null);
});
test('P1 UI state and original pixels are excluded from saved layout',()=>{
 const e=load(tariff(regular)),s=e.sides.front;s.tier='A4';s.image={x:1,y:2,w:5,h:6,aspect:1};s.sourcePixels={width:3000,height:2000};e.changeEdit(()=>s.placement.dx=1);const data=JSON.stringify(e.jsonSide(s));for(const field of ['sourcePixels','past','future','aspect','selection'])assert.equal(data.includes(field),false);assert.equal(JSON.parse(data).tier,'A4');
});
test('photo config rejects foreign origins and capability schemes',()=>{
 const e=load(tariff(regular),{mockups:{Hitam:{front:'https://fixture.invalid/photo.jpg',back:'https://foreign.invalid/photo.jpg'}}});assert.equal(e.mockupUrl('front'),'https://fixture.invalid/photo.jpg');assert.equal(e.mockupUrl('back'),'');
 const bad=load(tariff(regular),{mockups:{Hitam:{front:'data:image/svg+xml,unsafe'}}});assert.equal(bad.mockupUrl(),'');
});
test('public opt-in disables new history, Snap and contextual UI together',()=>{
 const e=load(tariff(regular),{uxEnabled:false});e.template();const s=e.sides.front;s.tier='A5';s.image={x:1,y:1,w:4,h:4};e.node('.kld-position-layer').value='image';e.node('.kld-position-layer').emit('change');e.changeEdit(()=>s.placement.dx=2);assert.equal(e.histories.front.past.length,0);assert.equal(e.node('.kld-context').hidden,true);assert.equal(e.stepHistory(),false);const r=e.snapLayer({x:7.8,y:7.7,w:4,h:4},{tierW:20,tierH:20,safeX0:0,safeX1:20,safeY0:0,safeY1:20},null,.5);assert.equal(r.x,7.8);assert.equal(r.guides.length,0);
});
test('selection guides never enter exported order thumbnail',()=>{
 const e=load(tariff(regular));e.template();e.sides.front.tier='A5';e.sides.front.image={x:1,y:1,w:4,h:4};e.sides.front.imagePixels={bitmap:{}};e.node('.kld-position-layer').value='image';e.node('.kld-position-layer').emit('change');e.paint.length=0;e.renderThumb('front');assert.ok(e.paint.some(call=>call.method==='strokeRect'&&call.strokeStyle==='#b733b0'));assert.equal(e.getLastExport().some(call=>call.strokeStyle==='#b733b0'),false);
});
test('calibrated photos load anonymously; cache retains only current colour pair',async()=>{
 const e=load(tariff(regular),{mockups:{Hitam:{front:'https://fixture.invalid/black-front.jpg',back:'https://fixture.invalid/black-back.jpg'},Putih:{front:'https://fixture.invalid/white-front.jpg',back:'https://fixture.invalid/white-back.jpg'}}});e.template();await Promise.all([e.loadMockup('front'),e.loadMockup('back')]);assert.equal(e.mockupCache.size,2);assert.equal([...e.mockupCache.values()][0].img.crossOrigin,'anonymous');e.node('.kld-color').value='Putih';e.node('.kld-color').emit('change');await e.loadMockup('front');await e.loadMockup('back');assert.equal(e.mockupCache.size,2);assert.ok([...e.mockupCache.keys()].every(url=>url.includes('white-')));
 const invalid=load(tariff(regular),{imageDimensions:[100,100],mockups:{Hitam:{front:'https://fixture.invalid/bad.jpg'}}});assert.equal(await invalid.loadMockup('front'),null);
});
