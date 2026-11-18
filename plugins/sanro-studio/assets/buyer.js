'use strict';
(function(){
 const root=document.getElementById('sanro-studio-buyer'),config=window.SANRO_STUDIO_EDITOR_CONFIG;
 if(!root||!config||!config.safe_areas)return;
 const q=s=>root.querySelector(s),sides={front:{},back:{}};
 const make=()=>({url:null,text:'',imageSize:60,fontSize:22,imageX:10,imageY:10,textX:10,textY:65,category:'A5'});
 sides.front=make();sides.back=make();let side=config.safe_areas.front?'front':'back';
 const image=q('.sanro-safe img'),text=q('.sanro-text'),safe=q('.sanro-safe');
 const categories=config.categories;
 const shirtColors=new Set(['#ffffff','#171717','#b91c1c','#1d4ed8','#166534']);
 const shirtColor=q('#sanro-shirt-color');
 shirtColor.addEventListener('change',()=>{
  if(shirtColors.has(shirtColor.value))q('.sanro-shirt').style.backgroundColor=shirtColor.value;
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
 qtyInputs.forEach(input=>input.addEventListener('input',updateQty));
 updateQty();
 function render(){
  const d=sides[side];q('#sanro-text-input').value=d.text;q('#sanro-image-size').value=d.imageSize;
  q('#sanro-font-size').value=d.fontSize;q('#sanro-category').value=d.category;
  image.hidden=!d.url;if(d.url&&image.getAttribute('src')!==d.url)image.setAttribute('src',d.url);if(!d.url)image.removeAttribute('src');
  image.style.left=d.imageX+'%';image.style.top=d.imageY+'%';image.style.width=d.imageSize+'%';
  text.hidden=!d.text;text.textContent=d.text;text.style.left=d.textX+'%';text.style.top=d.textY+'%';text.style.fontSize=d.fontSize+'px';
  root.querySelectorAll('[data-side]').forEach(b=>{b.disabled=!config.safe_areas[b.dataset.side];b.setAttribute('aria-pressed',String(b.dataset.side===side))});
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
 q('#sanro-text-input').addEventListener('input',e=>{sides[side].text=e.target.value;render()});
 q('#sanro-image-size').addEventListener('input',e=>{sides[side].imageSize=Number(e.target.value);render()});
 q('#sanro-font-size').addEventListener('input',e=>{sides[side].fontSize=Number(e.target.value);render()});
 q('#sanro-category').addEventListener('change',e=>{sides[side].category=e.target.value;render()});
 q('#sanro-image').addEventListener('change',e=>{
  const file=e.target.files[0];if(!file)return;
  if(!['image/png','image/jpeg'].includes(file.type)||file.size>10485760){q('#sanro-validation').textContent='File ditolak: PNG/JPEG maksimal 10 MB';e.target.value='';return}
  const target=sides[side],url=URL.createObjectURL(file),probe=new Image();
  if(target.pending)URL.revokeObjectURL(target.pending);
  probe.onload=()=>{if(target.pending!==url)return;if(target.url)URL.revokeObjectURL(target.url);target.url=url;target.pending=null;render()};
  probe.onerror=()=>{if(target.pending===url){URL.revokeObjectURL(url);target.pending=null;q('#sanro-validation').textContent='Gambar tidak valid'}};
  target.pending=url;probe.src=url;e.target.value='';
 });
 q('#sanro-remove-image').addEventListener('click',()=>{const d=sides[side];if(d.url)URL.revokeObjectURL(d.url);if(d.pending)URL.revokeObjectURL(d.pending);d.url=null;d.pending=null;image.removeAttribute('src');render()});
 function drag(el,prefix){let start=null;el.addEventListener('pointerdown',e=>{if(el.hidden)return;const d=sides[side];start={side,x:e.clientX,y:e.clientY,px:d[prefix+'X'],py:d[prefix+'Y']};el.setPointerCapture(e.pointerId)});
 el.addEventListener('pointermove',e=>{if(!start)return;const d=sides[start.side],rect=safe.getBoundingClientRect();if(!rect.width||!rect.height)return;
  d[prefix+'X']=Math.max(0,Math.min(100-el.offsetWidth/rect.width*100,start.px+(e.clientX-start.x)/rect.width*100));
  d[prefix+'Y']=Math.max(0,Math.min(100-el.offsetHeight/rect.height*100,start.py+(e.clientY-start.y)/rect.height*100));render()});
 el.addEventListener('pointerup',()=>start=null);el.addEventListener('pointercancel',()=>start=null)}
 image.addEventListener('load',validate);
 drag(image,'image');drag(text,'text');window.addEventListener('resize',validate);render();
})();