'use strict';
(function(){
 const root=document.getElementById('sanro-studio-buyer'),config=window.SANRO_STUDIO_EDITOR_CONFIG;
 if(!root||!config||!config.safe_areas)return;
 const q=s=>root.querySelector(s),sides={front:{},back:{}};
 const make=()=>({url:null,file:null,text:'',imageSize:60,fontSize:22,textColor:'#171717',imageX:10,imageY:10,textX:10,textY:65,category:'A5'});
 sides.front=make();sides.back=make();let side=(config.safe_areas[config.default_side]?config.default_side:(config.safe_areas.front?'front':'back'));
 const image=q('.sanro-safe img'),text=q('.sanro-text'),safe=q('.sanro-safe');
 const categories=config.categories;
 const shirtColors=new Set(config.palette&&Object.keys(config.palette).length?Object.keys(config.palette):['#ffffff','#171717','#b91c1c','#1d4ed8','#166534']);
 const mockupSize=q('#sanro-mockup-size');
 if(mockupSize)mockupSize.addEventListener('change',()=>render());
 function mappedMockup(name,size){
  const matrix=config.mockup_matrix;
  if(matrix&&Object.keys(matrix).length){const color=matrix[shirtColor.value]||{};return (color[size]||color['*']||{})[name]||null;}
  return config.mockups&&config.mockups[name]||null;
 }
 const shirtColor=q('#sanro-shirt-color');
 shirtColor.addEventListener('change',()=>{
  if(shirtColors.has(shirtColor.value)){editRevision++;q('.sanro-shirt').style.setProperty('--sanro-shirt-color',shirtColor.value);render();}
 });
 q('.sanro-shirt').style.setProperty('--sanro-shirt-color',shirtColor.value);
 const qtyInputs=Array.from(root.querySelectorAll('[data-size]'));
 function designCategory(d){return d.url||d.text.trim()?d.category:null;}
 function updateEstimate(){
  const output=q('#sanro-price-estimate'),calc=window.SanroStudioEstimate;
  if(!calc||!['cotton_combed_24s','tunik_oversize_34'].includes(config.pricing_profile)){output.textContent='Estimasi belum tersedia';return;}
  if(qtyInputs.some(input=>!input.validity.valid)){
   output.textContent='Estimasi tidak tersedia: periksa jumlah per ukuran';return;
  }
  const qty=Object.fromEntries(qtyInputs.map(input=>[input.dataset.size,Number(input.value)]));
  const result=calc.estimate(qty,designCategory(sides.front),designCategory(sides.back),q('#sanro-sleeve').value==='long',config.pricing||null);
  output.textContent=result&&result.pieces>0?'Estimasi: '+new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(result.total)+' (belum final)':'Estimasi harga: lengkapi desain dan jumlah';
 }
 function updateQty(){
  let total=0;
  for(const input of qtyInputs){
   const value=Number(input.value);
   if(!Number.isSafeInteger(value)||value<0||value>999){
    input.setCustomValidity('Jumlah harus bilangan bulat 0–999');
   }else{
    input.setCustomValidity('');
    total+=value;
   }
  }
  const invalid=qtyInputs.some(input=>!input.validity.valid);
  q('#sanro-qty-total').textContent=invalid?'Periksa jumlah tiap ukuran':total>5000?'Maksimal 5.000 pcs per pesanan':'Total: '+total+' pcs';
  updateEstimate();
 }
 qtyInputs.forEach(input=>input.addEventListener('input',()=>{editRevision++;updateQty()}));
 q('#sanro-sleeve').addEventListener('change',()=>{editRevision++;updateEstimate()});
 updateQty();

 // A visual print zone is independent for each product side. All physical
 // limits are supplied by server-owned safe_areas (cm), not these percentages.
 function visualPrintZone(name){
  const zone=config.print_zones&&config.print_zones[name];
  const valid=zone&&['x','y','width','height'].every(key=>
   typeof zone[key]==='number'&&Number.isFinite(zone[key]))
   &&zone.x>=0&&zone.y>=0&&zone.width>0&&zone.height>0
   &&zone.x+zone.width<=100&&zone.y+zone.height<=100;
  return valid?zone:{x:27,y:22,width:46,height:54};
 }
 // Measure an inactive side using its own visual geometry, then restore the
 // currently displayed side before the browser can paint another frame.
 function withPrintZone(name,measure){
  const previous={left:safe.style.left,top:safe.style.top,width:safe.style.width,height:safe.style.height};
  const zone=visualPrintZone(name);
  safe.style.left=zone.x+'%';
  safe.style.top=zone.y+'%';
  safe.style.width=zone.width+'%';
  safe.style.height=zone.height+'%';
  try{return measure();}finally{
   safe.style.left=previous.left;
   safe.style.top=previous.top;
   safe.style.width=previous.width;
   safe.style.height=previous.height;
  }
 }
 function render(){
  // Rendering is not itself a user edit; revision changes only in input handlers.
  const d=sides[side];
  // Merchant-selected WooCommerce Media Library mockup; presentation only.
  // Design geometry, authoritative area and DTF master bindings are unchanged.
  const mockup=mappedMockup(side,mockupSize?mockupSize.value:'S');
  const shirt=q('.sanro-shirt');
  shirt.setAttribute('data-side',side);
  shirt.setAttribute('data-mockup',String(typeof mockup==='string' && /^https?:\/\//i.test(mockup)));
  shirt.style.backgroundImage=(typeof mockup==='string' && /^https?:\/\//i.test(mockup))
    ? 'url('+JSON.stringify(mockup)+')' : '';
  // Merchant-controlled visual overlay; authoritative geometry uses
  // independent centimeter bounds from the server. An unsafe zone falls back
  // to the existing 27/22/46/54 layout without modifying the saved design.
  const placement=visualPrintZone(side);
  safe.style.left=placement.x+'%';
  safe.style.top=placement.y+'%';
  safe.style.width=placement.width+'%';
  safe.style.height=placement.height+'%';
  q('#sanro-text-input').value=d.text;
  q('#sanro-image-size').min=String(Math.min(10,d.imageSize));
  q('#sanro-image-size').value=d.imageSize;
  q('#sanro-font-size').value=d.fontSize;q('#sanro-text-color').value=d.textColor;q('#sanro-category').value=d.category;
  image.hidden=!d.url;if(d.url&&image.getAttribute('src')!==d.url)image.setAttribute('src',d.url);if(!d.url)image.removeAttribute('src');
  image.style.left=d.imageX+'%';image.style.top=d.imageY+'%';image.style.width=d.imageSize+'%';image.style.height='auto';
  text.hidden=!d.text;text.textContent=d.text;text.style.left=d.textX+'%';text.style.top=d.textY+'%';text.style.fontSize=d.fontSize+'px';text.style.color=d.textColor;
  root.querySelectorAll('[data-side]').forEach(b=>{b.disabled=!config.safe_areas[b.dataset.side];b.setAttribute('aria-pressed',String(b.dataset.side===side))});
  q('#sanro-side-summary').textContent=['front','back'].map(name=>(name==='front'?'Depan':'Belakang')+': '+(sides[name].url||sides[name].text.trim()?'ada desain ('+sides[name].category+')':'kosong')).join(' · ');
  updateEstimate();requestAnimationFrame(validate);
  root.dispatchEvent(new CustomEvent('sanro:render'));
  if(typeof refreshBackgroundControl==='function')refreshBackgroundControl();
 }
 function validate(){
  const area=config.safe_areas[side],d=sides[side],rect=safe.getBoundingClientRect(),objects=[];
  if(!area||!rect.width||!rect.height)return;
  for(const [el,type,x,y] of [[image,'image',d.imageX,d.imageY],[text,'text',d.textX,d.textY]]){
   if(el.hidden)continue;
   if(type==='image' && (!image.complete || !image.naturalWidth || !image.naturalHeight)){
    q('#sanro-validation').textContent='Memuat gambar untuk pemeriksaan area cetak…';return;
   }
   const box=el.getBoundingClientRect();
   objects.push({type,x:x*area.width/100,y:y*area.height/100,width:box.width*area.width/rect.width,height:box.height*area.height/rect.height});
  }
  if(!objects.length){q('#sanro-validation').textContent='Belum ada objek cetak.';return}
  const x1=Math.min(...objects.map(o=>o.x)),y1=Math.min(...objects.map(o=>o.y));
  const x2=Math.max(...objects.map(o=>o.x+o.width)),y2=Math.max(...objects.map(o=>o.y+o.height));
  const w=x2-x1,h=y2-y1,limit=Object.prototype.hasOwnProperty.call(categories,d.category)?categories[d.category]:null;
  const outside=objects.some(o=>o.x<0||o.y<0||o.x+o.width>area.width+1e-6||o.y+o.height>area.height+1e-6);
  const valid=!outside&&Array.isArray(limit)&&limit.length===2&&w<=limit[0]+1e-6&&h<=limit[1]+1e-6;
  if(valid){
   q('#sanro-validation').textContent='Sisi '+(side==='front'?'depan':'belakang')+' sesuai batas '+d.category+': '+w.toFixed(2)+' × '+h.toFixed(2)+' cm.';
  }else if(outside){
   const names=objects.filter(o=>o.x<0||o.y<0||o.x+o.width>area.width+1e-6||o.y+o.height>area.height+1e-6).map(o=>o.type==='image'?'gambar':'teks');
   q('#sanro-validation').textContent='Sisi '+(side==='front'?'depan':'belakang')+': '+names.join(' dan ')+' melewati area cetak. Pindahkan atau kecilkan pada tab '+(names.includes('gambar')?'Gambar':'Teks')+'.';
  }else{
   q('#sanro-validation').textContent='Desain sisi '+(side==='front'?'depan':'belakang')+' '+w.toFixed(2)+' × '+h.toFixed(2)+' cm melebihi batas kategori '+d.category+'. Pilih kategori cetak yang lebih besar pada tab Produk.';
  }
 }
 root.querySelectorAll('[data-side]').forEach(b=>b.addEventListener('click',()=>{if(config.safe_areas[b.dataset.side]){side=b.dataset.side;render()}}));
 q('#sanro-text-input').addEventListener('input',e=>{editRevision++;sides[side].text=e.target.value;render()});
 q('#sanro-image-size').addEventListener('input',e=>{
  const d=sides[side],next=Number(e.target.value);
  if(!Number.isFinite(next)||next<Number(e.target.min)||next>100)return;
  if(!d.url){editRevision++;d.imageSize=next;render();return;}
  // Scale proportionally around the current center, then constrain within the safe area.
  const ratio=next/d.imageSize;
  const currentHeight=image.naturalWidth>0&&safe.clientHeight>0?d.imageSize*(safe.clientWidth/safe.clientHeight)*image.naturalHeight/image.naturalWidth:0;
  const nextHeight=currentHeight*ratio;
  if(nextHeight>100){
   e.target.value=String(d.imageSize);
   q('#sanro-validation').textContent='Ukuran gambar terlalu tinggi untuk area aman.';
   return;
  }
  d.imageX=Math.max(0,Math.min(100-next,d.imageX+(d.imageSize-next)/2));
  d.imageY=Math.max(0,Math.min(100-nextHeight,d.imageY+(currentHeight-nextHeight)/2));
  editRevision++;d.imageSize=next;render();
 });
 q('#sanro-font-size').addEventListener('input',e=>{editRevision++;sides[side].fontSize=Number(e.target.value);render()});
 q('#sanro-text-color').addEventListener('input',e=>{editRevision++;sides[side].textColor=e.target.value;render()});
 q('#sanro-remove-text').addEventListener('click',()=>{editRevision++;sides[side].text='';render()});
 q('#sanro-center-text').addEventListener('click',()=>{
  const d=sides[side];if(!d.text.trim())return;
  const previous={content:text.textContent,font:text.style.fontSize,hidden:text.hidden};
  text.hidden=false;text.textContent=d.text;text.style.fontSize=d.fontSize+'px';
  const rect=safe.getBoundingClientRect(),box=text.getBoundingClientRect();
  text.textContent=previous.content;text.style.fontSize=previous.font;text.hidden=previous.hidden;
  if(!rect.width||!rect.height||!box.width||!box.height)return;
  editRevision++;
  d.textX=Math.max(0,(rect.width-box.width)/rect.width*50);
  d.textY=Math.max(0,(rect.height-box.height)/rect.height*50);render();
 });
 q('#sanro-category').addEventListener('change',e=>{editRevision++;sides[side].category=e.target.value;render()});
 q('#sanro-copy-text').addEventListener('click',()=>{
  const other=side==='front'?'back':'front',source=sides[side],target=sides[other],status=q('#sanro-copy-status');
  if(!config.safe_areas[other]){status.textContent='Sisi tujuan belum dikonfigurasi.';return}
  if(!source.text.trim()){status.textContent='Tambahkan teks sebelum menyalin.';return}
  if(target.text.trim()&&!window.confirm('Ganti teks pada sisi '+(other==='front'?'depan':'belakang')+'?'))return;
  // Preserve relative placement and text style, but do not copy temporary images.
  target.text=source.text;target.fontSize=source.fontSize;target.textColor=source.textColor;
  target.textX=source.textX;target.textY=source.textY;
  target.category=source.category;
  editRevision++;render();
  status.textContent='Teks disalin ke sisi '+(other==='front'?'depan':'belakang')+'. Periksa batas cetaknya.';
 });
 q('#sanro-reset-side').addEventListener('click',()=>{
  const d=sides[side];
  if(!d.url&&!d.pending&&!d.text.trim())return;
  if(!window.confirm('Kosongkan gambar dan teks pada sisi '+(side==='front'?'depan':'belakang')+'?'))return;
  if(d.url)URL.revokeObjectURL(d.url);
  if(d.pending)URL.revokeObjectURL(d.pending);
  sides[side]=make();
  editRevision++;
  render();
 });
 q('#sanro-image').addEventListener('change',e=>{
  const file=e.target.files[0];if(!file)return;
  if(!['image/png','image/jpeg'].includes(file.type)||file.size===0||file.size>10485760){q('#sanro-validation').textContent='File ditolak: PNG/JPEG tidak kosong, maksimal 10 MB';e.target.value='';return}
  const uploadSide=side,target=sides[uploadSide],url=URL.createObjectURL(file),probe=new Image();
  editRevision++;
  if(target.pending)URL.revokeObjectURL(target.pending);
  // Claim ownership before assigning src; cancellation invalidates this pending URL.
  target.pending=url;
  probe.onload=()=>{
   if(target.pending!==url)return;
   // Reject images with excessive decoded dimensions before previewing them.
   // File size alone does not bound browser memory consumption.
   const width=probe.naturalWidth,height=probe.naturalHeight;
   if(!width||!height||width>10000||height>10000||width*height>25000000){
    URL.revokeObjectURL(url);target.pending=null;
    if(side===uploadSide)q('#sanro-validation').textContent='Gambar terlalu besar: maksimal 25 megapiksel dan 10.000 px per sisi.';
    return;
   }
   if(target.url)URL.revokeObjectURL(target.url);
   target.url=url;target.file=file;target.pending=null;
   delete boundMasterRefs[uploadSide];
   // Fit the image into the safe preview without changing its aspect ratio.
   const safeWidth=safe.clientWidth,safeHeight=safe.clientHeight;
   if(safeWidth>0&&safeHeight>0&&probe.naturalWidth>0&&probe.naturalHeight>0){
    const maxWidth=Math.min(100,100*safeHeight*probe.naturalWidth/(safeWidth*probe.naturalHeight));
    target.imageSize=Math.min(60,maxWidth);
    // Tall images may need less than the slider's 10% minimum.
    if(side===uploadSide)q('#sanro-image-size').min=String(Math.min(10,target.imageSize));
    target.imageX=Math.max(0,(100-target.imageSize)/2);
    target.imageY=Math.max(0,(100-target.imageSize*safeWidth*probe.naturalHeight/(safeHeight*probe.naturalWidth))/2);
   }
   // A slow image load must never redraw or alter the currently selected opposite side.
   if(side===uploadSide)render();
   else updateEstimate();
  };
  probe.onerror=()=>{if(target.pending===url){URL.revokeObjectURL(url);target.pending=null;if(side===uploadSide)q('#sanro-validation').textContent='Gambar tidak valid'}};
  probe.src=url;e.target.value='';
 });
 q('#sanro-center-image').addEventListener('click',()=>{
  const d=sides[side];
  if(!d.url||!image.complete||!image.naturalWidth||!image.naturalHeight)return;
  const rect=safe.getBoundingClientRect(),box=image.getBoundingClientRect();
  if(!rect.width||!rect.height||!box.width||!box.height)return;
  // Center using the rendered dimensions, including tall-image aspect ratio.
  d.imageX=Math.max(0,(rect.width-box.width)/rect.width*50);
  d.imageY=Math.max(0,(rect.height-box.height)/rect.height*50);
  editRevision++;render();
 });
 q('#sanro-remove-image').addEventListener('click',()=>{editRevision++;const d=sides[side];if(d.url)URL.revokeObjectURL(d.url);if(d.pending)URL.revokeObjectURL(d.pending);d.url=null;d.file=null;d.pending=null;image.removeAttribute('src');render()});
 // Object URLs are browser-local temporary resources; never persist them as draft masters.
 window.addEventListener('pagehide',()=>{
  for(const name of ['front','back']){
   const d=sides[name];
   if(d.url)URL.revokeObjectURL(d.url);
   if(d.pending)URL.revokeObjectURL(d.pending);
   d.url=null;d.pending=null;
  }
 },{once:true});
 function drag(el,prefix){
  let start=null;
  el.addEventListener('pointerdown',e=>{
   if(el.hidden||!e.isPrimary)return;
   const d=sides[side];
   start={pointerId:e.pointerId,side,x:e.clientX,y:e.clientY,px:d[prefix+'X'],py:d[prefix+'Y']};
   el.setPointerCapture(e.pointerId);
  });
  el.addEventListener('pointermove',e=>{
   if(!start||e.pointerId!==start.pointerId||side!==start.side)return;
   const d=sides[start.side],rect=safe.getBoundingClientRect();
   if(!rect.width||!rect.height)return;
   const maxX=Math.max(0,100-el.offsetWidth/rect.width*100);
   const maxY=Math.max(0,100-el.offsetHeight/rect.height*100);
   editRevision++;
   d[prefix+'X']=Math.max(0,Math.min(maxX,start.px+(e.clientX-start.x)/rect.width*100));
   d[prefix+'Y']=Math.max(0,Math.min(maxY,start.py+(e.clientY-start.y)/rect.height*100));
   render();
  });
  const finish=e=>{if(start&&e.pointerId===start.pointerId)start=null;};
  el.addEventListener('pointerup',finish);
  el.addEventListener('pointercancel',finish);
  el.addEventListener('lostpointercapture',()=>{start=null;});
 }
 // Visual D-pad uses the same bounded geometry as native pointer dragging.
 root.sanroMoveObject=function(kind,dx,dy){
  if(!['image','text'].includes(kind))return;const el=kind==='image'?image:text;if(el.hidden)return;
  const rect=safe.getBoundingClientRect();if(!rect.width||!rect.height)return;
  const d=sides[side];editRevision++;
  d[kind+'X']=Math.max(0,Math.min(Math.max(0,100-el.offsetWidth/rect.width*100),d[kind+'X']+dx));
  d[kind+'Y']=Math.max(0,Math.min(Math.max(0,100-el.offsetHeight/rect.height*100),d[kind+'Y']+dy));render();
 };
 // Owner-bound draft session; master/preview APIs and checkout use independent server gates.
 const saveButton=q('#sanro-save-draft'),saveStatus=q('#sanro-save-status');
 let savedId=null,ownerToken=null,saving=false,uploadingMaster=false,editRevision=0,savedRevision=-1,lastKnownDesignHash=null;
 const boundMasterRefs={};
 const draftStorageKey='sanro-studio-draft:'+String(config.product_id);
 async function readDraftMaster(id,token,name,fileId){
  if(!config.master_upload_enabled||typeof fileId!=='string'
   ||!fileId.startsWith(id.toLowerCase()+'-'+name+'-'))
   throw new Error('Referensi master '+name+' tidak valid.');
  const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(id)
   +'/master-read/'+name,{
   method:'GET',credentials:'same-origin',cache:'no-store',
   headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':token}
  });
  if(!response.ok)throw new Error('Master privat '+name+' tidak tersedia ('+response.status+').');
  const data=await response.json();
  if(!data.valid||data.file_id!==fileId||data.side!==name
   ||!['image/png','image/jpeg'].includes(data.mime)
   ||typeof data.image_base64!=='string'||data.image_base64.length>14000000)
   throw new Error('Respons master '+name+' tidak valid.');
  const binary=atob(data.image_base64),bytes=new Uint8Array(binary.length);
  for(let i=0;i<binary.length;i++)bytes[i]=binary.charCodeAt(i);
  const hash=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',bytes)))
   .map(value=>value.toString(16).padStart(2,'0')).join('');
  if(hash!==data.sha256)throw new Error('Checksum master '+name+' tidak cocok.');
  return URL.createObjectURL(new Blob([bytes],{type:data.mime}));
 }
 function restoreDesign(design,masterUrls={}){
  if(!design||typeof design!=='object')throw new Error('Metadata draft tidak valid.');
  for(const name of ['front','back']){
   const entry=design[name];
   if(!entry){
    const d=sides[name];
    if(d.url&&d.url.startsWith('blob:'))URL.revokeObjectURL(d.url);
    Object.assign(d,make());
    continue;
   }
   if(!config.safe_areas[name]||!categories[entry.category]||!Array.isArray(entry.objects)
    ||entry.objects.length<1||entry.objects.length>2
    ||entry.objects.some(obj=>!['image','text'].includes(obj.type))
    ||new Set(entry.objects.map(obj=>obj.type)).size!==entry.objects.length)
    throw new Error('Format draft tidak didukung.');
   const hasText=entry.objects.some(obj=>obj.type==='text');
   const hasImage=entry.objects.some(obj=>obj.type==='image');
   if(hasImage&&!masterUrls[name])throw new Error('Master privat '+name+' belum tersedia.');
   if(hasText&&(typeof entry.text!=='string'||!Number.isInteger(entry.font_size)
    ||entry.font_size<10||entry.font_size>48||typeof entry.text_color!=='string'
    ||!/^#[0-9a-f]{6}$/i.test(entry.text_color)))throw new Error('Format teks draft tidak valid.');
   const area=config.safe_areas[name];
   for(const obj of entry.objects){
    if(![obj.x,obj.y,obj.width,obj.height].every(Number.isFinite)||obj.width<=0||obj.height<=0
     ||obj.x<0||obj.y<0||obj.x+obj.width>area.width+0.000001
     ||obj.y+obj.height>area.height+0.000001)throw new Error('Geometri draft tidak valid.');
   }
  }
  if(design.long_sleeve!==undefined){
   if(typeof design.long_sleeve!=='boolean')throw new Error('Model lengan draft tidak valid.');
   if(config.pricing&&config.pricing.fixed_sleeve){
    if(design.long_sleeve)throw new Error('Model lengan draft tidak sesuai produk.');
    q('#sanro-sleeve').value=config.pricing.fixed_sleeve;
   }else q('#sanro-sleeve').value=design.long_sleeve?'long':'short';
  }
  if(design.shirt_color!==undefined){
   if(!shirtColors.has(design.shirt_color))throw new Error('Warna draft tidak valid.');
   shirtColor.value=design.shirt_color;q('.sanro-shirt').style.setProperty('--sanro-shirt-color',shirtColor.value);
  }
  if(design.quantities!==undefined){
   if(!design.quantities||typeof design.quantities!=='object'||Array.isArray(design.quantities))
    throw new Error('Jumlah draft tidak valid.');
   for(const input of qtyInputs){
    const count=design.quantities[input.dataset.size]??0;
    if(!Number.isInteger(count)||count<0||count>999)throw new Error('Jumlah draft tidak valid.');
   }
   for(const input of qtyInputs)input.value=design.quantities[input.dataset.size]??0;
   updateQty();
  }
  for(const name of ['front','back']){
   const entry=design[name];const d=sides[name];
   if(!entry){d.text='';continue}
   const textObject=entry.objects.find(obj=>obj.type==='text');
   const imageObject=entry.objects.find(obj=>obj.type==='image');
   d.text=textObject?entry.text:'';d.category=entry.category;
   if(textObject){
    d.fontSize=entry.font_size;d.textColor=entry.text_color;
    d.textX=textObject.x/config.safe_areas[name].width*100;
    d.textY=textObject.y/config.safe_areas[name].height*100;
   }
   if(imageObject){
    if(d.url)URL.revokeObjectURL(d.url);
    d.url=masterUrls[name];d.file=null;d.pending=null;
    d.imageX=imageObject.x/config.safe_areas[name].width*100;
    d.imageY=imageObject.y/config.safe_areas[name].height*100;
    d.imageSize=imageObject.width/config.safe_areas[name].width*100;
   }
  }
  render();
 }
 async function reopenDraft(){
  let stored;
  try{stored=sessionStorage.getItem(draftStorageKey)}catch(_error){return false}
  if(!stored)return false;
  const revisionAtRequest=editRevision;
  let credentials;
  try{credentials=JSON.parse(stored)}catch(_error){return false}
  if(!credentials||typeof credentials!=='object')return false;
  if(typeof credentials.id!=='string'||typeof credentials.token!=='string'
   ||!/^[a-f0-9-]{36}$/i.test(credentials.id)||!/^[a-f0-9]{64}$/.test(credentials.token))return false;
  const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(credentials.id),{
   method:'GET',headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':credentials.token},
   credentials:'same-origin',cache:'no-store'});
  if(response.status===410){
   try{sessionStorage.removeItem(draftStorageKey)}catch(_error){}
   throw new Error('Draft telah kedaluwarsa (7 hari). Simpan desain sebagai draft baru.');
  }
  if(!response.ok)throw new Error('Draft tidak dapat dibuka ('+response.status+').');
  const result=await response.json();
  if(!result.valid||Number(result.product_id)!==Number(config.product_id))throw new Error('Draft bukan untuk produk ini.');
  if(editRevision!==revisionAtRequest){
   saveStatus.textContent='Draft lama tidak diterapkan karena desain sudah diubah. Muat ulang halaman untuk membuka draft.';
   return false;
  }
  const masterUrls={};
  try{
   for(const name of ['front','back']){
    const entry=result.design&&result.design[name];
    if(!entry||!Array.isArray(entry.objects)
     ||!entry.objects.some(object=>object.type==='image'))continue;
    const ref=result.master_refs&&result.master_refs[name];
    masterUrls[name]=await readDraftMaster(credentials.id,credentials.token,name,ref);
   }
   if(editRevision!==revisionAtRequest)throw new Error('Desain berubah selama pemulihan draft.');
   restoreDesign(result.design,masterUrls);
  }catch(error){
   Object.values(masterUrls).forEach(url=>URL.revokeObjectURL(url));
   throw error;
  }
  Object.assign(boundMasterRefs,result.master_refs||{});refreshBackgroundControl();
  savedId=credentials.id;ownerToken=credentials.token;savedRevision=editRevision;
  lastKnownDesignHash=typeof result.design_hash==='string'?result.design_hash:null;
  saveStatus.textContent='Draft berhasil dibuka kembali. Design ID: '+savedId;
  return true;
 }
 function exportTextDraft(ignoreImages=false,allowEmpty=false){
  const design={};
  for(const name of ['front','back']){
   const d=sides[name],area=config.safe_areas[name];
   if(!area)continue;
   if((d.url||d.pending)&&!ignoreImages)throw new Error('Gambar belum dapat disimpan. Upload master privat terlebih dahulu.');
   if(!d.text.trim())continue;
   // Measure the other side without switching editor state or touching inputs.
   const {rect,box}=withPrintZone(name,()=>{
    const previous={content:text.textContent,font:text.style.fontSize,left:text.style.left,top:text.style.top,hidden:text.hidden};
    try{
     text.hidden=false;text.textContent=d.text;text.style.fontSize=d.fontSize+'px';
     text.style.left=d.textX+'%';text.style.top=d.textY+'%';
     return {rect:safe.getBoundingClientRect(),box:text.getBoundingClientRect()};
    }finally{
     text.textContent=previous.content;text.style.fontSize=previous.font;
     text.style.left=previous.left;text.style.top=previous.top;text.hidden=previous.hidden;
    }
   });
   if(!rect.width||!rect.height||!box.width||!box.height)throw new Error('Ukuran pratinjau belum siap.');
   const object={type:'text',x:d.textX*area.width/100,y:d.textY*area.height/100,
    width:box.width*area.width/rect.width,height:box.height*area.height/rect.height};
   const limit=categories[d.category];
   if(!limit||object.x<0||object.y<0||object.x+object.width>area.width+0.000001
    ||object.y+object.height>area.height+0.000001
    ||object.width>limit[0]+0.000001||object.height>limit[1]+0.000001){
    throw new Error('Teks di luar area aman atau kategori cetak.');
   }
   design[name]={category:d.category,objects:[object],text:d.text,font_size:d.fontSize,text_color:d.textColor};
  }
  if(!Object.keys(design).length&&!allowEmpty)throw new Error('Tambahkan teks terlebih dahulu.');
  const quantities=Object.fromEntries(qtyInputs.map(input=>[input.dataset.size,Number(input.value)]));
  if(Object.values(quantities).some(v=>!Number.isInteger(v)||v<0||v>999)
   ||Object.values(quantities).reduce((a,b)=>a+b,0)>5000)throw new Error('Jumlah per ukuran tidak valid.');
  design.shirt_color=shirtColor.value;design.quantities=quantities;
  design.long_sleeve=q('#sanro-sleeve').value==='long';
  return design;
 }
 async function ensureProductionFont(){
  if(document.fonts&&typeof document.fonts.load==='function'){
   const faces=await document.fonts.load('22px SanroProduction');
   if(!faces.length)throw new Error('Font produksi belum tersedia. Muat ulang editor.');
  }
 }
 async function exportBoundDraft(){
  await ensureProductionFont();
  const design=exportTextDraft(true,true);
  for(const name of ['front','back']){
   const d=sides[name],area=config.safe_areas[name];
   if(!area||!d.url)continue;
   // Never accept a locally replaced image with the prior bound master ID.
   if(d.pending||d.file)
    throw new Error('Unggah gambar baru '+name+' sebelum menyimpan.');
   if(!boundMasterRefs[name])
    throw new Error('Master privat '+name+' belum terikat.');
   const dimensions=await new Promise((resolve,reject)=>{
    const probe=new Image();
    probe.onload=()=>resolve([probe.naturalWidth,probe.naturalHeight]);
    probe.onerror=()=>reject(new Error('Gambar '+name+' tidak dapat dibaca.'));
    probe.src=d.url;
   });
   const rect=withPrintZone(name,()=>safe.getBoundingClientRect());
   if(!rect.width||!rect.height||!dimensions[0]||!dimensions[1])
    throw new Error('Area gambar belum siap.');
   const width=d.imageSize*area.width/100;
   const height=width*rect.width/rect.height*dimensions[1]/dimensions[0]*area.height/area.width;
   const imageGeometry={type:'image',x:d.imageX*area.width/100,
    y:d.imageY*area.height/100,width,height};
   if(imageGeometry.x<0||imageGeometry.y<0
    ||imageGeometry.x+width>area.width+0.000001
    ||imageGeometry.y+height>area.height+0.000001)
    throw new Error('Gambar '+name+' di luar area aman.');
   if(!design[name])design[name]={category:d.category,objects:[]};
   design[name].objects.push(imageGeometry);
  }
  if(!design.front&&!design.back)throw new Error('Tambahkan desain terlebih dahulu.');
  return design;
 }
 // Derived private JPEG preview, not original print-resolution artwork.
 // Require the owner-bound master before previewing an uploaded image.
 async function makeDesignPreview(name,variantSize){
  await ensureProductionFont();
  const d=sides[name],area=config.safe_areas[name];
  // A blank garment side still needs an owner-bound preview for the native
  // order. It remains absent from design.objects and adds no print surcharge.
  if(!area&&(d.url||d.text.trim()))throw new Error('Sisi cetak tidak dikonfigurasi.');
  if(d.pending||d.file||(d.url&&!boundMasterRefs[name]))
   throw new Error('Unggah dan simpan master '+name+' sebelum membuat preview.');
  const bounds=withPrintZone(name,()=>safe.getBoundingClientRect());
  if(!bounds.width||!bounds.height)throw new Error('Area preview belum siap.');
  let imagePreview=null;
  if(d.url){
   imagePreview=await new Promise((resolve,reject)=>{
    const probe=new Image();
    probe.onload=()=>probe.naturalWidth>0&&probe.naturalHeight>0
     ?resolve(probe):reject(new Error('Gambar preview tidak valid.'));
    probe.onerror=()=>reject(new Error('Gambar preview gagal dibaca.'));
    probe.src=d.url;
   });
  }
  const mockupUrl=mappedMockup(name,variantSize||(mockupSize?mockupSize.value:'S'));
  let garment=null;
  if(mockupUrl){
   garment=await new Promise((resolve,reject)=>{const img=new Image();img.crossOrigin='anonymous';img.onload=()=>resolve(img);img.onerror=()=>reject(new Error('Mockup produk gagal dimuat.'));img.src=mockupUrl;});
  }
  // Fit the private 50 KiB server cap by reducing resolution, then quality.
  // Source/master image bytes are never serialized into this preview payload.
  for(const size of [640,480,360,256]){
   const canvas=document.createElement('canvas');
   canvas.width=size;canvas.height=Math.round(size*bounds.height/bounds.width);
   const ctx=canvas.getContext('2d');
   if(!ctx)throw new Error('Canvas tidak tersedia.');
   ctx.clearRect(0,0,size,size);
   if(imagePreview){
    const width=d.imageSize/100*size;
    const height=width*imagePreview.naturalHeight/imagePreview.naturalWidth;
    ctx.drawImage(imagePreview,d.imageX/100*size,d.imageY/100*canvas.height,width,height);
   }
   if(d.text.trim()){
    const fontPx=Math.max(8,Math.round(d.fontSize*size/bounds.width));
    const x=d.textX/100*size,y=d.textY/100*canvas.height;
    ctx.fillStyle=d.textColor;
    ctx.font=fontPx+'px SanroProduction';ctx.textBaseline='top';
    d.text.split('\n').forEach((line,i)=>
     ctx.fillText(line,x,y+i*fontPx*1.2,Math.max(0,size-x)));
   }
   const output=document.createElement('canvas');output.width=size;output.height=Math.round(size*420/390);
   const view=output.getContext('2d');if(!view)throw new Error('Canvas preview tidak tersedia.');
   view.fillStyle='#f5f7fb';view.fillRect(0,0,output.width,output.height);
   if(garment){const ratio=Math.min(output.width/garment.naturalWidth,output.height/garment.naturalHeight);const w=garment.naturalWidth*ratio,h=garment.naturalHeight*ratio;view.drawImage(garment,(output.width-w)/2,(output.height-h)/2,w,h);}
   else{
    view.fillStyle=shirtColor.value;view.beginPath();const points=[[30,5],[40,1],[60,1],[70,5],[100,22],[86,43],[75,37],[75,99],[25,99],[25,37],[14,43],[0,22]];
    points.forEach(([x,y],i)=>i?view.lineTo(x/100*output.width,y/100*output.height):view.moveTo(x/100*output.width,y/100*output.height));view.closePath();view.fill();
   }
   const zone=visualPrintZone(name);view.drawImage(canvas,zone.x/100*output.width,zone.y/100*output.height,zone.width/100*output.width,zone.height/100*output.height);
   for(const quality of [0.75,0.55,0.35,0.2]){
    const blob=await new Promise(resolve=>output.toBlob(resolve,'image/jpeg',quality));
    if(blob&&blob.size>=100&&blob.size<=51200){
     return await new Promise((resolve,reject)=>{
      const reader=new FileReader();
      reader.onload=()=>resolve(String(reader.result).split(',')[1]);
      reader.onerror=()=>reject(new Error('Preview gagal dibaca.'));
      reader.readAsDataURL(blob);
     });
    }
   }
  }
  throw new Error('Preview melebihi batas 50 KB.');
 }
 let backgroundScan=0;
 async function refreshBackgroundControl(){
  const button=q('#sanro-remove-background');if(!button)return;button.disabled=true;
  const url=sides[side].url,scan=++backgroundScan;if(!url||sides[side].file||!boundMasterRefs[side])return;
  try{const img=await new Promise((resolve,reject)=>{const probe=new Image();probe.onload=()=>resolve(probe);probe.onerror=reject;probe.src=url;});
   const canvas=document.createElement('canvas');canvas.width=img.naturalWidth;canvas.height=Math.min(32,img.naturalHeight);const ctx=canvas.getContext('2d',{willReadFrequently:true});if(!ctx)return;
   for(let y=0;y<img.naturalHeight;y+=32){
    if(scan!==backgroundScan||sides[side].url!==url)return;
    const h=Math.min(32,img.naturalHeight-y);ctx.clearRect(0,0,canvas.width,canvas.height);ctx.drawImage(img,0,y,img.naturalWidth,h,0,0,img.naturalWidth,h);
    const rgba=ctx.getImageData(0,0,canvas.width,h).data;for(let i=3;i<rgba.length;i+=4)if(rgba[i]<255)return;
    if(y%512===0)await new Promise(resolve=>setTimeout(resolve,0));
   }
   if(scan===backgroundScan)button.disabled=false;
  }catch(_error){}
 }
 root.sanroCollectionReady=async()=>{
  if(saving||uploadingMaster||!savedId||!ownerToken||savedRevision!==editRevision)throw new Error('Simpan draft terbaru terlebih dahulu.');
  if(typeof root.sanroUploadCurrentPreviews==='function')await root.sanroUploadCurrentPreviews();
  return {id:savedId,token:ownerToken};
 };
 for(const [selector,restore] of [['#sanro-remove-background',false],['#sanro-restore-background',true]]){
  const button=q(selector);if(!button)continue;button.addEventListener('click',async()=>{
   const status=q('#sanro-background-status');
   if(saving||uploadingMaster||!savedId||savedRevision!==editRevision||!boundMasterRefs[side]){status.textContent='Unggah master dan simpan draft terbaru dahulu.';return;}
   const name=side,source=sides[name].url;uploadingMaster=true;button.disabled=true;status.textContent='Memproses gambar secara lokal…';
   try{
    const response=await fetch(config.draft_api.url+'/'+savedId+'/remove-background/'+name,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken},body:JSON.stringify({expected_design_hash:lastKnownDesignHash,restore,tolerance:Number(q('#sanro-background-tolerance').value)})});
    const value=await window.SanroStudioTransport.readJson(response);if(!response.ok||!value.valid)throw new Error(value.message||'Pemrosesan gambar ditolak.');
    const url=await readDraftMaster(savedId,ownerToken,name,value.file_id);
    if(sides[name].url!==source){URL.revokeObjectURL(url);throw new Error('Gambar editor berubah. Simpan ulang atau buka draft server.');}
    if(source)URL.revokeObjectURL(source);sides[name].url=url;sides[name].file=null;boundMasterRefs[name]=value.file_id;render();
    status.textContent=restore?'Gambar asli dipulihkan. Perbarui preview sebelum checkout.':'Latar polos dihapus. Gambar asli tetap tersedia; perbarui preview sebelum checkout.';
   }catch(error){status.textContent=error.message;}finally{uploadingMaster=false;refreshBackgroundControl();}
  });
 }
 if(saveButton&&config.draft_api){
  const requestEmail=q('#sanro-request-owner-email'),confirmEmail=q('#sanro-confirm-owner-email');
  if(requestEmail&&confirmEmail){
   const emailStatus=q('#sanro-owner-email-status');
   async function verifyOwner(operation){
    if(!savedId||!ownerToken||saving||uploadingMaster){emailStatus.textContent='Simpan desain dan tunggu proses upload selesai dahulu.';return;}
    const email=q('#sanro-owner-email'),code=q('#sanro-owner-email-code');
    if(operation==='request'&&(!email.value.trim()||!email.validity.valid)){emailStatus.textContent='Masukkan alamat email yang valid.';return;}
    if(operation==='complete'&&!/^[a-f0-9]{64}$/.test(code.value.trim())){emailStatus.textContent='Salin kode lengkap dari email.';return;}
    requestEmail.disabled=confirmEmail.disabled=true;
    try{
     const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)+'/owner-email/'+operation,{
      method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken},
      body:JSON.stringify(operation==='request'?{email:email.value.trim()}:{code:code.value.trim()})
     });
     const result=await response.json();
     if(!response.ok||!result.valid)throw new Error('Verifikasi belum berhasil. Periksa kode, kepemilikan draft atau coba lagi nanti.');
     if(operation==='complete')code.value='';
     emailStatus.textContent=operation==='request'?'Kode dikirim ke email. Berlaku 30 menit; jangan bagikan kode.':'Email pemilik terverifikasi. Gunakan email yang sama saat checkout; repeat 5% tetap memerlukan riwayat pesanan berbayar.';
    }catch(error){emailStatus.textContent='Verifikasi belum berhasil. Periksa koneksi, kode atau coba lagi nanti.';}
    finally{requestEmail.disabled=confirmEmail.disabled=false;}
   }
   requestEmail.addEventListener('click',()=>verifyOwner('request'));
   confirmEmail.addEventListener('click',()=>verifyOwner('complete'));
  }

  const masterButton=q('#sanro-upload-master'),masterStatus=q('#sanro-master-upload-status');
  if(masterButton&&config.master_upload_enabled){
   masterButton.addEventListener('click',async()=>{
    if(saving||uploadingMaster){masterStatus.textContent='Tunggu operasi draft sebelumnya selesai.';return;}
    if(!savedId||!ownerToken){
     masterStatus.textContent='Simpan draft awal terlebih dahulu, kemudian upload gambar.';return;
    }
    const selected=['front','back'].filter(name=>sides[name].file&&sides[name].url&&!sides[name].pending);
    if(!selected.length){masterStatus.textContent='Pilih gambar PNG/JPEG untuk depan atau belakang.';return;}
    if(['front','back'].some(name=>sides[name].pending)){
     masterStatus.textContent='Tunggu gambar selesai dimuat.';return;
    }
    const revisionAtStart=editRevision;
    uploadingMaster=true;masterButton.disabled=true;masterStatus.textContent='Mengunggah master privat…';
    try{
     const draftResponse=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId),{
      method:'GET',credentials:'same-origin',cache:'no-store',
      headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken}
     });
     if(!draftResponse.ok)throw new Error('Draft tidak dapat diverifikasi ('+draftResponse.status+').');
     const currentDraft=await draftResponse.json();
     if(!currentDraft.valid||currentDraft.status!=='draft'
      ||typeof currentDraft.design_hash!=='string'
      ||!/^[a-f0-9]{64}$/.test(currentDraft.design_hash))
      throw new Error('Revisi draft tidak valid.');
     if(editRevision!==revisionAtStart)
      throw new Error('Desain berubah selama persiapan upload.');
     await ensureProductionFont();
     const localTextDesign=exportTextDraft(true,true);
     // A reopened draft can contain saved image geometry. Compare only the
     // text/order metadata; keep existing private image geometry intact.
     const serverTextDesign=JSON.parse(JSON.stringify(currentDraft.design));
     for(const name of ['front','back']){
      if(!serverTextDesign[name])continue;
      serverTextDesign[name].objects=serverTextDesign[name].objects.filter(item=>item.type!=='image');
      if(!serverTextDesign[name].objects.length)delete serverTextDesign[name];
     }
     // A reopened text position may differ by <=1e-6 due to IEEE-754
     // reconstruction. Normalize ONLY matching text x/y for comparison;
     // do not modify the draft, hash, binding, or other metadata.
     const comparableTextDesign=JSON.parse(JSON.stringify(localTextDesign));
     for(const name of ['front','back']){
      const local=comparableTextDesign[name],server=serverTextDesign[name];
      if(!local||!server||!Array.isArray(local.objects)
       ||!Array.isArray(server.objects))continue;
      for(let i=0;i<local.objects.length;i++){
       const a=local.objects[i],b=server.objects[i];
       if(!a||!b||a.type!=='text'||b.type!=='text')continue;
       for(const key of ['x','y']){
        if(typeof a[key]==='number'&&Number.isFinite(a[key])
         &&typeof b[key]==='number'&&Number.isFinite(b[key])
         &&Math.abs(a[key]-b[key])<=0.000001)a[key]=b[key];
       }
      }
     }
     if(JSON.stringify(comparableTextDesign)!==JSON.stringify(serverTextDesign))
      throw new Error('Metadata teks, jumlah, atau warna berubah. Simpan draft teks sebelum upload master.');
     const designHash=currentDraft.design_hash;
     const completed=[];
     for(const name of selected){
      const file=sides[name].file;
      if(editRevision!==revisionAtStart||sides[name].file!==file)
       throw new Error('Desain berubah. Ulangi upload setelah perubahan selesai.');
      const encoded=await new Promise((resolve,reject)=>{
       const reader=new FileReader();
       reader.onload=()=>resolve(String(reader.result).split(',')[1]||'');
       reader.onerror=()=>reject(new Error('Gagal membaca gambar.'));
       reader.readAsDataURL(file);
      });
      if(editRevision!==revisionAtStart||sides[name].file!==file)
       throw new Error('Desain berubah selama persiapan upload.');
      const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)+'/masters/'+name,{
       method:'POST',credentials:'same-origin',cache:'no-store',
       headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),
        'X-Sanro-Owner-Token':ownerToken},
       body:JSON.stringify({image_base64:encoded,design_hash:designHash})
      });
      const result=await response.json();
      if(!response.ok||!result.valid||typeof result.file_id!=='string'
       ||!result.file_id.startsWith(savedId.toLowerCase()+'-'+name+'-'))
       throw new Error('Upload master '+name+' gagal ('+response.status+').');
      // Verify owner-bound readback and byte-for-byte integrity before success.
      const readback=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)
       +'/master-read/'+name,{
       method:'GET',credentials:'same-origin',cache:'no-store',
       headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken}
      });
      if(!readback.ok)throw new Error('Verifikasi master '+name+' gagal ('+readback.status+').');
      const verified=await readback.json();
      if(!verified.valid||verified.file_id!==result.file_id
       ||verified.image_base64!==encoded||verified.mime!==file.type
       ||verified.sha256!==result.sha256)
       throw new Error('Integritas master '+name+' tidak sesuai.');
      if(editRevision!==revisionAtStart||sides[name].file!==file)
       throw new Error('Desain berubah selama upload. Periksa ulang draft.');
      completed.push(name);
     }
     if(editRevision!==revisionAtStart)throw new Error('Desain berubah selama upload. Simpan ulang.');
     // Commit only geometry, never image bytes or a client-provided master ID.
     // The server verifies every image side against its owner-bound private master.
     const design=JSON.parse(JSON.stringify(currentDraft.design));
     for(const name of completed){
      const d=sides[name],area=config.safe_areas[name];
      const dimensions=await new Promise((resolve,reject)=>{
       const probe=new Image();
       probe.onload=()=>resolve([probe.naturalWidth,probe.naturalHeight]);
       probe.onerror=()=>reject(new Error('Dimensi gambar '+name+' tidak tersedia.'));
       probe.src=d.url;
      });
      const rect=safe.getBoundingClientRect();
      if(!rect.width||!rect.height||!dimensions[0]||!dimensions[1])
       throw new Error('Area cetak belum siap.');
      const width=d.imageSize*area.width/100;
      const height=width*rect.width/rect.height*dimensions[1]/dimensions[0]*area.height/area.width;
      const object={type:'image',x:d.imageX*area.width/100,y:d.imageY*area.height/100,width,height};
      if(object.x<0||object.y<0||object.x+width>area.width+0.000001
       ||object.y+height>area.height+0.000001)
       throw new Error('Gambar '+name+' di luar area aman.');
      if(!design[name])design[name]={category:d.category,objects:[]};
      design[name].category=d.category;
      design[name].objects=design[name].objects.filter(item=>item.type!=='image');
      design[name].objects.push(object);
     }
     if(editRevision!==revisionAtStart)throw new Error('Desain berubah sebelum metadata disimpan.');
     const commit=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId),{
      method:'POST',credentials:'same-origin',cache:'no-store',
      headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),
       'X-Sanro-Owner-Token':ownerToken},
      body:JSON.stringify({design,expected_design_hash:designHash})
     });
     const committed=await commit.json();
     if(!commit.ok||!committed.valid)
      throw new Error('Master terunggah, tetapi metadata gambar belum tersimpan ('+commit.status+').');
     const fresh=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId),{
      method:'GET',credentials:'same-origin',cache:'no-store',
      headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken}
     });
     if(!fresh.ok)throw new Error('Metadata tersimpan, tetapi verifikasi revisi gagal.');
     const updated=await fresh.json();
     if(!updated.valid||typeof updated.design_hash!=='string'
      ||!updated.master_refs||completed.some(name=>!updated.master_refs[name]))
      throw new Error('Metadata gambar tidak terikat ke master privat.');
     Object.assign(boundMasterRefs,updated.master_refs);
     // A successful server readback turns the local file into a bound master.
     // Do not accidentally upload the same source again on the next click.
     for(const name of completed)sides[name].file=null;
     await refreshBackgroundControl();
     lastKnownDesignHash=updated.design_hash;
     if(editRevision===revisionAtStart)savedRevision=editRevision;
     masterStatus.textContent='Master dan posisi gambar terverifikasi untuk '+completed.join(', ')+'. Draft dapat dibuka kembali.';
    }catch(error){masterStatus.textContent=error.message||'Upload master gagal.';}
    finally{uploadingMaster=false;masterButton.disabled=false;}
   });
  }
  const quoteButton=q('#sanro-server-quote'),quoteStatus=q('#sanro-server-quote-status');
  const uploadButton=q('#sanro-upload-previews'),uploadStatus=q('#sanro-preview-upload-status');
  if(uploadButton&&config.preview_upload_enabled){
   async function uploadCurrentPreviews(){
    if(uploadButton.disabled)throw new Error('Upload preview sedang berlangsung.');
    if(!savedId||!ownerToken||savedRevision!==editRevision){
     throw new Error('Simpan draft terbaru sebelum mengirim preview.');
    }
    if(typeof lastKnownDesignHash!=='string'||!/^[a-f0-9]{64}$/.test(lastKnownDesignHash))
     throw new Error('Revisi desain belum terverifikasi. Simpan ulang desain.');
    uploadButton.disabled=true;
    const revisionAtStart=editRevision;
    uploadStatus.textContent='Menyiapkan preview privat…';
    try{
     const completed=[];
     for(const name of ['front','back']){
      const encoded=await makeDesignPreview(name);
      if(!encoded)continue;
      if(editRevision!==revisionAtStart)throw new Error('Desain berubah. Simpan ulang sebelum upload.');
      const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)+'/previews/'+name,{
       method:'POST',credentials:'same-origin',cache:'no-store',
       headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken},
       body:JSON.stringify({image_base64:encoded,expected_design_hash:lastKnownDesignHash})
      });
      if(response.status===410)throw new Error('Draft kedaluwarsa. Simpan draft baru.');
      const result=await response.json();
      if(!response.ok||!result.valid||typeof result.file_id!=='string'
       ||!result.file_id.startsWith(savedId.toLowerCase()+'-'+name+'-'))
       throw new Error('Upload '+name+' gagal ('+response.status+').');
      // Verify private readback before reporting success. Preview is not a master file.
      const readback=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)
       +'/preview-read/'+name,{
       method:'GET',credentials:'same-origin',cache:'no-store',
       headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken}
      });
      const verified=await window.SanroStudioTransport.readJson(readback);
      if(!verified.valid||verified.file_id!==result.file_id||verified.image_base64!==encoded
       ||verified.mime!=='image/jpeg')throw new Error('Integritas preview '+name+' tidak sesuai.');
      completed.push(name);
      if(editRevision!==revisionAtStart)
       throw new Error('Desain berubah selama upload. Simpan draft dan unggah preview terbaru.');
     }
     if(config.mockup_matrix&&Object.keys(config.mockup_matrix).length){
      for(const input of qtyInputs.filter(input=>Number(input.value)>0))for(const name of ['front','back']){
       const encoded=await makeDesignPreview(name,input.dataset.size);if(!encoded)continue;
       if(editRevision!==revisionAtStart)throw new Error('Desain berubah selama preview ukuran.');
       const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)+'/previews/'+name+'/variant/'+encodeURIComponent(input.dataset.size),{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken},body:JSON.stringify({image_base64:encoded,expected_design_hash:lastKnownDesignHash})});
       const value=await window.SanroStudioTransport.readJson(response);if(!response.ok||!value.valid)throw new Error('Preview ukuran '+input.dataset.size+' gagal.');
      }
     }
     if(!completed.length)throw new Error('Tidak ada desain yang dapat dipratinjau.');
     if(editRevision!==revisionAtStart)throw new Error('Desain berubah setelah preview disimpan. Simpan ulang.');
     uploadStatus.textContent='Preview terbaru tersimpan dan siap ditautkan ke pesanan.';
     return completed;
    }catch(error){uploadStatus.textContent=error.message||'Upload preview gagal.';throw error;}
    finally{uploadButton.disabled=false}
   }
   uploadButton.addEventListener('click',()=>{uploadCurrentPreviews().catch(()=>{});});
   root.sanroUploadCurrentPreviews=uploadCurrentPreviews;
  }

  quoteButton.addEventListener('click',async()=>{
   if(!savedId||!ownerToken){quoteStatus.textContent='Simpan draft teks terlebih dahulu.';return}
   if(editRevision!==savedRevision){quoteStatus.textContent='Ada perubahan belum tersimpan. Simpan draft terlebih dahulu untuk harga terbaru.';return}
   quoteButton.disabled=true;
   const revisionAtRequest=editRevision;
   quoteStatus.textContent='Memeriksa harga server…';
   try{
    const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)+'/quote',{
     method:'GET',headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken},
     credentials:'same-origin',cache:'no-store'
    });
    if(response.status===410){
     savedId=null;ownerToken=null;savedRevision=-1;
     try{sessionStorage.removeItem(draftStorageKey)}catch(_error){}
     throw new Error('Draft telah kedaluwarsa. Simpan draft baru sebelum meminta harga.');
    }
    const result=await response.json();
    if(!response.ok||!result.valid||!result.quote||!Number.isSafeInteger(result.quote.total_price))
     throw new Error('Harga server belum tersedia ('+response.status+').');
    const money=value=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(value);
    const rows=result.quote.items;
    if(!rows||typeof rows!=='object'||Array.isArray(rows))throw new Error('Rincian harga server tidak valid.');
    const details=Object.entries(rows).map(([size,item])=>{
     if(!/^(S|M|L|XL|[2-5]XL)$/.test(size)||!item
      ||!Number.isSafeInteger(item.quantity)||!Number.isSafeInteger(item.unit_price)
      ||!Number.isSafeInteger(item.subtotal))throw new Error('Rincian harga server tidak valid.');
     return size+': '+item.quantity+' × '+money(item.unit_price)+' = '+money(item.subtotal);
    });
    quoteStatus.textContent='Harga server draft tersimpan: '+money(result.quote.total_price)
     +' untuk '+result.quote.total_quantity+' pcs. '+details.join(' · ')+'. '
     +(result.quote.repeat_discount?'Repeat 5% terverifikasi; hemat '+money(result.quote.repeat_discount.discount_total)+'. ':'Repeat 5% memerlukan email checkout yang cocok dan riwayat berbayar. ')
     +(editRevision!==revisionAtRequest?'Desain lokal berubah selama pemeriksaan; simpan ulang untuk harga terbaru. ':'')
     +(q('#sanro-add-to-cart')?'Harga terverifikasi; lanjutkan melalui keranjang.':'Checkout belum diaktifkan.');
   }catch(error){quoteStatus.textContent=error.message||'Gagal memeriksa harga server.'}
   finally{quoteButton.disabled=false}
  });
  const reopenButton=q('#sanro-reopen-draft'),reopenStatus=q('#sanro-reopen-status');
  if(reopenButton&&reopenStatus){
   reopenButton.addEventListener('click',async()=>{
    if(saving||uploadingMaster){reopenStatus.textContent='Tunggu penyimpanan atau upload master selesai.';return;}
    reopenButton.disabled=true;
    reopenStatus.textContent='Memeriksa draft tersimpan…';
    try{
     const stored=sessionStorage.getItem(draftStorageKey);
     if(!stored){reopenStatus.textContent='Belum ada draft tersimpan pada sesi browser ini.';return;}
     const reopened=await reopenDraft();
     reopenStatus.textContent=reopened?'Draft berhasil dibuka kembali.':'Draft belum dapat diterapkan karena desain editor telah berubah.';
    }catch(error){reopenStatus.textContent=error.message||'Draft tidak dapat dibuka kembali.';}
    finally{reopenButton.disabled=false;}
   });
  }
  try{reopenDraft().catch(error=>{saveStatus.textContent=error.message})}catch(_error){}
  const cartButton=q('#sanro-add-to-cart'),cartStatus=q('#sanro-cart-status');
  // Prevent a second Cart POST while the first is pending, even if a DOM
  // optimiser replaces the disabled button before the response arrives.
  let cartPending=false;
  if(cartButton&&cartStatus){
   // Delegate from the stable editor root. A layout/optimisation plugin may
   // replace the Cart button DOM after buyer.js initialises; binding directly
   // to the old node would leave a visible button without a click handler.
   root.addEventListener('click',async event=>{
    const target=event.target&&typeof event.target.closest==='function'
     ?event.target.closest('#sanro-add-to-cart'):null;
    if(!target||!root.contains(target)||cartPending)return;
    const status=q('#sanro-cart-status');
    if(!status)return;
    if(saving){
     status.textContent='Tunggu sampai penyimpanan draft selesai sebelum membuka keranjang.';return;
    }
    if(uploadingMaster){
     status.textContent='Tunggu sampai upload master selesai sebelum membuka keranjang.';return;
    }
    if(!savedId||!ownerToken){
     status.textContent='Draft belum tersedia pada sesi editor ini. Simpan desain sebelum membuka keranjang.';return;
    }
    if(editRevision!==savedRevision){
     status.textContent='Desain berubah setelah draft terakhir disimpan. Simpan ulang sebelum membuka keranjang.';return;
    }
    cartPending=true;
    target.disabled=true;
    status.textContent='Memvalidasi draft dan harga server…';
    try{
     if(config.preview_upload_enabled&&typeof root.sanroUploadCurrentPreviews==='function'){
      status.textContent='Menyimpan preview depan/belakang terbaru…';
      await root.sanroUploadCurrentPreviews();
      if(editRevision!==savedRevision)throw new Error('Desain berubah selama upload preview. Simpan ulang.');
     }
     status.textContent='Memvalidasi draft dan harga server…';
     const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId)+'/cart',{
      method:'POST',credentials:'same-origin',cache:'no-store',
      headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken}
     });
     const result=await response.json();
     if(!response.ok||!result.valid)throw new Error('Keranjang belum siap ('+response.status+').');
     status.textContent='Desain masuk keranjang. Lanjutkan melalui halaman keranjang untuk otorisasi checkout.';
     const safeCartUrl=typeof window.SanroStudioSafeCartUrl==='function'
      ?window.SanroStudioSafeCartUrl(result.cart_url,location.href):null;
     if(safeCartUrl)location.assign(safeCartUrl);
    }catch(error){status.textContent=error.message||'Gagal menambah ke keranjang.'}
    finally{cartPending=false;target.disabled=false}
   });
  }
  saveButton.addEventListener('click',async()=>{
   if(saving||uploadingMaster){saveStatus.textContent='Tunggu penyimpanan atau upload master selesai.';return;}
   let design;
   try{
    await ensureProductionFont();
    const hasLocalImage=['front','back'].some(name=>sides[name].url||sides[name].pending);
    design=savedId?await exportBoundDraft()
     :hasLocalImage&&config.master_upload_enabled
      ?exportTextDraft(true,true):exportTextDraft();
   }catch(error){saveStatus.textContent=error.message;return}
   const revisionAtSave=editRevision;
   saving=true;saveButton.disabled=true;saveStatus.textContent='Menyimpan metadata teks…';
   try{
    const headers={'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{})};
    if(savedId&&ownerToken)headers['X-Sanro-Owner-Token']=ownerToken;
    const endpoint=savedId?config.draft_api.url+'/'+encodeURIComponent(savedId):config.draft_api.url;
    const payload=savedId?{design,expected_design_hash:lastKnownDesignHash}:{product_id:Number(config.product_id),design};
    const response=await fetch(endpoint,{method:'POST',headers,body:JSON.stringify(payload),credentials:'same-origin',cache:'no-store'});
    const result=await response.json();
    if(!response.ok||!result.valid)throw new Error('Penyimpanan gagal ('+response.status+').');
    if(!savedId){savedId=result.design_id;ownerToken=result.owner_token}
    const verify=await fetch(config.draft_api.url+'/'+encodeURIComponent(savedId),{
     method:'GET',credentials:'same-origin',cache:'no-store',
     headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':ownerToken}
    });
    if(!verify.ok)throw new Error('Draft tersimpan tetapi verifikasi revisi gagal. Muat ulang sebelum mengedit.');
    const verified=await verify.json();
    if(!verified.valid||typeof verified.design_hash!=='string'
     ||!/^[a-f0-9]{64}$/.test(verified.design_hash))
     throw new Error('Fingerprint revisi draft tidak tersedia.');
    for(const name of ['front','back']){
     if(verified.master_refs&&verified.master_refs[name])boundMasterRefs[name]=verified.master_refs[name];
     else delete boundMasterRefs[name];
    }
    lastKnownDesignHash=verified.design_hash;
    if(editRevision===revisionAtSave)savedRevision=editRevision;
    try{sessionStorage.setItem(draftStorageKey,JSON.stringify({id:savedId,token:ownerToken}))}catch(_error){}
    if(editRevision!==revisionAtSave){
     saveStatus.textContent='Versi sebelumnya tersimpan. Ada perubahan baru yang belum tersimpan; klik Simpan lagi.';
    }else if(hasLocalImage&&config.master_upload_enabled){
     saveStatus.replaceChildren(document.createTextNode('Draft tersimpan dan terverifikasi. Unggah gambar asli sebagai langkah berikutnya agar master privat dan preview desain terikat. '));
     const next=document.createElement('button');next.type='button';next.className='sanro-next-action';next.textContent='Lanjut unggah gambar';
     next.addEventListener('click',()=>{root.dispatchEvent(new CustomEvent('sanro:show-panel',{detail:{name:'image'}}));window.setTimeout(()=>q('#sanro-upload-master')?.focus(),0);});saveStatus.append(next);
    }else{
     saveStatus.textContent='Draft tersimpan dan terverifikasi. Design ID: '+savedId+'. Bisa dibuka ulang pada tab ini selama sesi browser masih tersedia.';
    }
   }catch(error){saveStatus.textContent=error.message||'Penyimpanan gagal.'}
   finally{saving=false;saveButton.disabled=false}
  });
 }
 image.addEventListener('load',validate);
 drag(image,'image');drag(text,'text');window.addEventListener('resize',validate);render();
})();
