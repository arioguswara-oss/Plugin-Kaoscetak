'use strict';
(function(){
 const root=document.getElementById('sanro-studio-buyer'),config=window.SANRO_STUDIO_EDITOR_CONFIG;
 if(!root||!config||!config.safe_areas)return;
 const q=s=>root.querySelector(s),sides={front:{},back:{}};
 const make=()=>({url:null,text:'',imageSize:60,fontSize:22,textColor:'#171717',imageX:10,imageY:10,textX:10,textY:65,category:'A5'});
 sides.front=make();sides.back=make();let side=config.safe_areas.front?'front':'back';
 const image=q('.sanro-safe img'),text=q('.sanro-text'),safe=q('.sanro-safe');
 const categories=config.categories;
 const shirtColors=new Set(['#ffffff','#171717','#b91c1c','#1d4ed8','#166534']);
 const shirtColor=q('#sanro-shirt-color');
 shirtColor.addEventListener('change',()=>{
  if(shirtColors.has(shirtColor.value)){editRevision++;q('.sanro-shirt').style.backgroundColor=shirtColor.value;}
 });
 q('.sanro-shirt').style.backgroundColor=shirtColor.value;
 const qtyInputs=Array.from(root.querySelectorAll('[data-size]'));
 function designCategory(d){return d.url||d.text.trim()?d.category:null;}
 function updateEstimate(){
  const output=q('#sanro-price-estimate'),calc=window.SanroStudioEstimate;
  if(!calc||config.pricing_profile!=='cotton_combed_24s'){output.textContent='Estimasi belum tersedia';return;}
  const qty=Object.fromEntries(qtyInputs.map(input=>[input.dataset.size,Number(input.value)]));
  const result=calc.estimate(qty,designCategory(sides.front),designCategory(sides.back));
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
 updateQty();
 function render(){
  // Rendering is not itself a user edit; revision changes only in input handlers.
  const d=sides[side];q('#sanro-text-input').value=d.text;
  q('#sanro-image-size').min=String(Math.min(10,d.imageSize));
  q('#sanro-image-size').value=d.imageSize;
  q('#sanro-font-size').value=d.fontSize;q('#sanro-text-color').value=d.textColor;q('#sanro-category').value=d.category;
  image.hidden=!d.url;if(d.url&&image.getAttribute('src')!==d.url)image.setAttribute('src',d.url);if(!d.url)image.removeAttribute('src');
  image.style.left=d.imageX+'%';image.style.top=d.imageY+'%';image.style.width=d.imageSize+'%';image.style.height='auto';
  text.hidden=!d.text;text.textContent=d.text;text.style.left=d.textX+'%';text.style.top=d.textY+'%';text.style.fontSize=d.fontSize+'px';text.style.color=d.textColor;
  root.querySelectorAll('[data-side]').forEach(b=>{b.disabled=!config.safe_areas[b.dataset.side];b.setAttribute('aria-pressed',String(b.dataset.side===side))});
  q('#sanro-side-summary').textContent=['front','back'].map(name=>(name==='front'?'Depan':'Belakang')+': '+(sides[name].url||sides[name].text.trim()?'ada desain ('+sides[name].category+')':'kosong')).join(' · ');
  updateEstimate();requestAnimationFrame(validate);
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
  q('#sanro-validation').textContent=(valid?'Pratinjau sesuai batas: ':'Di luar batas cetak: ')+w.toFixed(2)+' × '+h.toFixed(2)+' cm';
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
 q('#sanro-image').addEventListener('change',e=>{
  const file=e.target.files[0];if(!file)return;
  if(!['image/png','image/jpeg'].includes(file.type)||file.size>10485760){q('#sanro-validation').textContent='File ditolak: PNG/JPEG maksimal 10 MB';e.target.value='';return}
  const target=sides[side],url=URL.createObjectURL(file),probe=new Image();
  editRevision++;
  if(target.pending)URL.revokeObjectURL(target.pending);
  probe.onload=()=>{
   if(target.pending!==url)return;
   if(target.url)URL.revokeObjectURL(target.url);
   target.url=url;target.pending=null;
   // Fit the image into the safe preview without changing its aspect ratio.
   const safeWidth=safe.clientWidth,safeHeight=safe.clientHeight;
   if(safeWidth>0&&safeHeight>0&&probe.naturalWidth>0&&probe.naturalHeight>0){
    const maxWidth=Math.min(100,100*safeHeight*probe.naturalWidth/(safeWidth*probe.naturalHeight));
    target.imageSize=Math.min(60,maxWidth);
    // Tall images may need less than the slider's 10% minimum.
    q('#sanro-image-size').min=String(Math.min(10,target.imageSize));
    target.imageX=Math.max(0,(100-target.imageSize)/2);
    target.imageY=Math.max(0,(100-target.imageSize*safeWidth*probe.naturalHeight/(safeHeight*probe.naturalWidth))/2);
   }
   render();
  };
  probe.onerror=()=>{if(target.pending===url){URL.revokeObjectURL(url);target.pending=null;q('#sanro-validation').textContent='Gambar tidak valid'}};
  target.pending=url;probe.src=url;e.target.value='';
 });
 q('#sanro-remove-image').addEventListener('click',()=>{editRevision++;const d=sides[side];if(d.url)URL.revokeObjectURL(d.url);if(d.pending)URL.revokeObjectURL(d.pending);d.url=null;d.pending=null;image.removeAttribute('src');render()});
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
 // Text-only staging draft: no master upload, checkout or public share.
 const saveButton=q('#sanro-save-draft'),saveStatus=q('#sanro-save-status');
 let savedId=null,ownerToken=null,saving=false,editRevision=0;
 const draftStorageKey='sanro-studio-draft:'+String(config.product_id);
 function restoreDesign(design){
  if(!design||typeof design!=='object')throw new Error('Metadata draft tidak valid.');
  for(const name of ['front','back']){
   const entry=design[name];if(!entry)continue;
   if(!config.safe_areas[name]||!categories[entry.category]||!Array.isArray(entry.objects)
    ||entry.objects.length!==1||entry.objects[0].type!=='text'||typeof entry.text!=='string'
    ||!Number.isInteger(entry.font_size)||entry.font_size<10||entry.font_size>48
    ||typeof entry.text_color!=='string'||!/^#[0-9a-f]{6}$/i.test(entry.text_color))throw new Error('Format draft tidak didukung.');
   const obj=entry.objects[0],area=config.safe_areas[name];
   if(![obj.x,obj.y,obj.width,obj.height].every(Number.isFinite)||obj.width<=0||obj.height<=0
    ||obj.x<0||obj.y<0||obj.x+obj.width>area.width+0.000001
    ||obj.y+obj.height>area.height+0.000001)throw new Error('Geometri draft tidak valid.');
  }
  if(design.shirt_color!==undefined){
   if(!shirtColors.has(design.shirt_color))throw new Error('Warna draft tidak valid.');
   shirtColor.value=design.shirt_color;q('.sanro-shirt').style.backgroundColor=shirtColor.value;
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
   d.text=entry.text;d.category=entry.category;d.fontSize=entry.font_size;d.textColor=entry.text_color;
   d.textX=entry.objects[0].x/config.safe_areas[name].width*100;
   d.textY=entry.objects[0].y/config.safe_areas[name].height*100;
  }
  render();
 }
 async function reopenDraft(){
  let stored;
  try{stored=sessionStorage.getItem(draftStorageKey)}catch(_error){return}
  if(!stored)return;
  const revisionAtRequest=editRevision;
  let credentials;
  try{credentials=JSON.parse(stored)}catch(_error){return}
  if(!credentials||typeof credentials!=='object')return;
  if(typeof credentials.id!=='string'||typeof credentials.token!=='string'
   ||!/^[a-f0-9-]{36}$/i.test(credentials.id)||!/^[a-f0-9]{64}$/.test(credentials.token))return;
  const response=await fetch(config.draft_api.url+'/'+encodeURIComponent(credentials.id),{
   method:'GET',headers:{'X-Sanro-Draft-Nonce':config.draft_api.nonce,'X-Sanro-Owner-Token':credentials.token},
   credentials:'same-origin',cache:'no-store'});
  if(!response.ok)throw new Error('Draft tidak dapat dibuka ('+response.status+').');
  const result=await response.json();
  if(!result.valid||Number(result.product_id)!==Number(config.product_id))throw new Error('Draft bukan untuk produk ini.');
  if(editRevision!==revisionAtRequest){
   saveStatus.textContent='Draft lama tidak diterapkan karena desain sudah diubah. Muat ulang halaman untuk membuka draft.';
   return;
  }
  restoreDesign(result.design);savedId=credentials.id;ownerToken=credentials.token;
  saveStatus.textContent='Draft teks berhasil dibuka kembali. Design ID: '+savedId;
 }
 function exportTextDraft(){
  const design={};
  for(const name of ['front','back']){
   const d=sides[name],area=config.safe_areas[name];
   if(!area)continue;
   if(d.url||d.pending)throw new Error('Gambar belum dapat disimpan. Hapus gambar terlebih dahulu.');
   if(!d.text.trim())continue;
   // Measure the other side without switching editor state or touching inputs.
   const previous={content:text.textContent,font:text.style.fontSize,left:text.style.left,top:text.style.top,hidden:text.hidden};
   text.hidden=false;text.textContent=d.text;text.style.fontSize=d.fontSize+'px';
   text.style.left=d.textX+'%';text.style.top=d.textY+'%';
   const rect=safe.getBoundingClientRect(),box=text.getBoundingClientRect();
   text.textContent=previous.content;text.style.fontSize=previous.font;
   text.style.left=previous.left;text.style.top=previous.top;text.hidden=previous.hidden;
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
  if(!Object.keys(design).length)throw new Error('Tambahkan teks terlebih dahulu.');
  const quantities=Object.fromEntries(qtyInputs.map(input=>[input.dataset.size,Number(input.value)]));
  if(Object.values(quantities).some(v=>!Number.isInteger(v)||v<0||v>999)
   ||Object.values(quantities).reduce((a,b)=>a+b,0)>5000)throw new Error('Jumlah per ukuran tidak valid.');
  design.shirt_color=shirtColor.value;design.quantities=quantities;
  return design;
 }
 if(saveButton&&config.draft_api){
  try{reopenDraft().catch(error=>{saveStatus.textContent=error.message})}catch(_error){}
  saveButton.addEventListener('click',async()=>{
   if(saving)return;
   let design;
   try{design=exportTextDraft()}catch(error){saveStatus.textContent=error.message;return}
   const revisionAtSave=editRevision;
   saving=true;saveButton.disabled=true;saveStatus.textContent='Menyimpan metadata teks…';
   try{
    const headers={'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce};
    if(savedId&&ownerToken)headers['X-Sanro-Owner-Token']=ownerToken;
    const endpoint=savedId?config.draft_api.url+'/'+encodeURIComponent(savedId):config.draft_api.url;
    const payload=savedId?{design}:{product_id:Number(config.product_id),design};
    const response=await fetch(endpoint,{method:'POST',headers,body:JSON.stringify(payload),credentials:'same-origin',cache:'no-store'});
    const result=await response.json();
    if(!response.ok||!result.valid)throw new Error('Penyimpanan gagal ('+response.status+').');
    if(!savedId){savedId=result.design_id;ownerToken=result.owner_token}
    try{sessionStorage.setItem(draftStorageKey,JSON.stringify({id:savedId,token:ownerToken}))}catch(_error){}
    saveStatus.textContent=editRevision===revisionAtSave
     ?'Draft teks tersimpan. Design ID: '+savedId+'. Bisa dibuka ulang pada tab ini selama sesi browser masih tersedia.'
     :'Versi sebelumnya tersimpan. Ada perubahan baru yang belum tersimpan; klik Simpan lagi.';
   }catch(error){saveStatus.textContent=error.message||'Penyimpanan gagal.'}
   finally{saving=false;saveButton.disabled=false}
  });
 }
 image.addEventListener('load',validate);
 drag(image,'image');drag(text,'text');window.addEventListener('resize',validate);render();
})();