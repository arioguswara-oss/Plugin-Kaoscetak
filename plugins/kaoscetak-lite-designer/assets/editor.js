(()=>{'use strict';
const cfg=window.KLD_CONFIG,openBtn=document.getElementById('kld-open-designer'),root=document.getElementById('kld-editor-root');
if(!cfg||!openBtn||!root)return;
const isMug=cfg.productType==='mug-11oz';
const T=cfg.tiers, tiers=Object.keys(T), sides={front:makeSide(),back:makeSide()};
if(isMug)sides.front.tier='MUG';
function makeSide(){return{tier:'none',image:null,text:null,file:null,upload:'idle',uploadError:'',imagePixels:null,alpha:false,restored:false,placement:{dx:0,dy:0}};}
let current='front',shirtSize=isMug?'ONE':'L',color=cfg.colors[0]||'',designId=null,designToken=null,creating=null,busy=false,dragging=null,uploadCounter=0;
const $=s=>root.querySelector(s),money=n=>'Rp'+Math.round(n).toLocaleString('id-ID');
function xss(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function visible(){return sides[current]};
function api(path,data){return fetch(cfg.rest+path,{method:'POST',headers:{'Content-Type':'application/json','X-KLD-Token':designToken||'','X-WP-Nonce':cfg.nonce},body:JSON.stringify({...data,design_id:designId}),credentials:'same-origin'}).then(async r=>{let j;try{j=await r.json();}catch(e){j={message:'Respons server tidak dikenal.'};}if(!r.ok)throw new Error(j.message||'Gagal memproses permintaan.');return j;});}
function showStatus(s,type='info'){const el=$('.kld-status');if(el){el.textContent=s;el.dataset.type=type;}}
function dims(t){return T[t]||[0,0]};
function resetLayer(side){const s=sides[side];if(s.tier==='none')return;const [w,h]=dims(s.tier);if(s.image){const factor=Math.min(1,w/s.image.w,h/s.image.h);s.image.w*=factor;s.image.h*=factor;s.image.x=Math.max(0,Math.min(s.image.x,w-s.image.w));s.image.y=Math.max(0,Math.min(s.image.y,h-s.image.h));}if(s.text){s.text.w=Math.min(s.text.w,w);s.text.h=Math.min(s.text.h,h);s.text.x=Math.max(0,Math.min(s.text.x,w-s.text.w));s.text.y=Math.max(0,Math.min(s.text.y,h-s.text.h));}}
function setTier(side,t){if(isMug)return;const s=sides[side];s.tier=t;if(t==='none'){s.image=null;s.text=null;s.file=null;s.upload='idle';}else{resetLayer(side);if(s.file&&!s.image)addImageLayer(s);}refresh();}
function pricePerSize(size){if(isMug)return size==='ONE' && sides.front.tier==='MUG'?Number(cfg.matrix.ONE?.MUG||0):0;const m=cfg.matrix[size]||{},f=sides.front.tier,b=sides.back.tier;const selected=[f,b].filter(a=>tiers.includes(a)).sort((a,b)=>tiers.indexOf(a)-tiers.indexOf(b));if(!selected.length)return 0;const large=selected[selected.length-1],base=m[large];if(!base)return 0;return Number(base)+(selected.length===2?Number(cfg.surcharges[selected[0]]||0):0);}
function template(){
root.innerHTML=`<div class="kld-overlay" role="dialog" aria-modal="true" aria-label="Kaoscetak Designer">
 <div class="kld-shell"><header class="kld-header"><button type="button" class="kld-back" aria-label="Tutup editor">←</button><strong>Kaoscetak Lite Designer</strong><span class="kld-mark">${cfg.previewOnly?'MODE PREVIEW':isMug?'CUSTOM MUG':'CUSTOM KAOS'}</span></header>
 <div class="kld-content">
  <section class="kld-workspace">${isMug?'<div class="kld-mug-label">Mug Putih 11 oz · Bidang cetak 18 × 8,5 cm</div>':'<div class="kld-tabs"><button type="button" data-side="front" class="active">Depan</button><button type="button" data-side="back">Belakang</button></div>'}
  <div class="kld-preview-wrap"><canvas class="kld-stage" width="600" height="710" aria-label="Preview desain produk"></canvas></div>
  <div class="kld-hint">${isMug?'Geser gambar atau teks di bidang 18 × 8,5 cm. Ini pratinjau bidang cetak terbentang, bukan simulasi keliling mug 3D.':'Geser gambar atau teks di dalam area cetak. Geser bagian kosong dalam garis putus-putus untuk memindahkan seluruh area cetak di kaos.'}</div>
  </section>
  <section class="kld-controls">
    <div class="kld-row2"><label>${isMug?'Warna mug':'Warna kaos'}<select class="kld-color">${cfg.colors.length?cfg.colors.map(x=>`<option value="${xss(x)}">${xss(x)}</option>`).join(''):'<option value="">Semua warna sedang tidak tersedia</option>'}</select></label><label>${isMug?'Jenis produk':'Preview size'}<select class="kld-size">${cfg.sizes.map(x=>`<option value="${xss(x)}">${x==='ONE'?'Mug 11 oz':xss(x)}</option>`).join('')}</select></label></div>
    ${isMug?'':`<div class="kld-swatches" role="group" aria-label="Pilihan warna produk">${cfg.colors.map(x=>`<button type="button" class="kld-swatch" data-color="${xss(x)}" aria-label="Warna ${xss(x)}" aria-pressed="false" title="${xss(x)}"><span style="background:${xss(cfg.colorSwatches?.[x]||'#777777')}"></span></button>`).join('')}</div>`}
    <label>${isMug?'Area cetak tetap':'Ukuran cetak'} <strong class="kld-current-side">depan</strong><select class="kld-tier">${isMug?'':'<option value="none">Tidak cetak</option>'}${tiers.map(x=>`<option value="${xss(x)}">${xss(x)} — ${T[x][0]} × ${T[x][1]} cm</option>`).join('')}</select></label>
    <div class="kld-pane kld-image-pane"><div class="kld-pane-title">Gambar (maks. 1 ${isMug?'per mug':'per sisi'})</div><label class="kld-file"><span>+ Pilih PNG / JPEG (maks. 10 MB)</span><input type="file" class="kld-fileinput" accept="image/png,image/jpeg"/></label>
     <div class="kld-upload-state" role="status"></div>
     <div class="kld-image-actions"><button class="kld-remove-bg" type="button" disabled>Hapus background</button><button class="kld-retry" type="button" hidden>Coba upload ulang</button></div>
     <label>Lebar gambar <span class="kld-image-width-label"></span><input class="kld-image-width" type="range" min="1" max="32" step="0.2" value="10" disabled></label>
    </div>
    <div class="kld-pane"><div class="kld-pane-title">Teks (maks. 1 blok ${isMug?'per mug':'per sisi'})</div><textarea class="kld-text" rows="2" maxlength="160" placeholder="Opsional: nama, slogan, atau teks desain"></textarea>
     <div class="kld-row2"><label>Font<select class="kld-font"><option>Arial</option><option>Georgia</option><option>Verdana</option><option>Tahoma</option></select></label><label>Warna teks<input class="kld-text-color" type="color" value="#ffffff"></label></div>
     <label>Ukuran teks <span class="kld-text-width-label"></span><input class="kld-text-width" type="range" min="1" max="32" step="0.2" value="10"></label>
    </div>
    <div class="kld-pane"><div class="kld-pane-title">${isMug?'Jumlah mug (pcs)':'Jumlah pesanan per size'}</div><div class="kld-qty-grid">${cfg.sizes.map(s=>`<label class="kld-qty"><span>${s==='ONE'?'Jumlah':xss(s)}</span><input type="number" min="0" max="100" step="1" data-qty="${xss(s)}" value="${s==='L'||s==='ONE'?'1':'0'}"></label>`).join('')}</div></div>
    <div class="kld-total"><span>Total pesanan <small class="kld-count"></small></span><strong class="kld-total-number">Pilih area cetak</strong></div>
    <div class="kld-status" role="status" aria-live="polite"></div>
    <div class="kld-actions"><button class="kld-save" type="button">Simpan Desain</button><button class="kld-cart" type="button">Masukkan Keranjang</button></div>
    <div class="kld-mail"><strong>Kelola desain tanpa akun</strong><p>Masukkan email untuk menerima tautan verifikasi kepemilikan.</p><div class="kld-row2"><input type="email" class="kld-email" placeholder="email@contoh.com"><button type="button" class="kld-verify">Kirim verifikasi</button></div></div>
    <p class="kld-footnote">Preview adalah simulasi. File asli tetap disimpan untuk operator cetak, tanpa diproses di hosting WordPress.</p>
  </section></div></div></div>`;
 $('.kld-back').addEventListener('click',close);
 root.querySelectorAll('[data-side]').forEach(btn=>btn.addEventListener('click',()=>{current=btn.dataset.side;refresh();}));
 $('.kld-tier').addEventListener('change',e=>setTier(current,e.target.value));
 $('.kld-color').addEventListener('change',e=>{color=e.target.value;refresh();});
 root.querySelectorAll('.kld-swatch').forEach(b=>b.addEventListener('click',()=>{color=b.dataset.color;refresh();}));
 $('.kld-size').addEventListener('change',e=>{shirtSize=e.target.value;refresh();});
 $('.kld-fileinput').addEventListener('change',async e=>{if(e.target.files[0])await onFile(e.target.files[0],current);e.target.value='';});
 $('.kld-remove-bg').addEventListener('click',removeSolidBackground);
 $('.kld-retry').addEventListener('click',()=>{if(visible().file)uploadOriginal(visible().file,current);});
 $('.kld-image-width').addEventListener('input',e=>{const s=visible(),L=dims(s.tier);if(!s.image)return;const ratio=s.image.aspect;let w=Math.min(L[0],+e.target.value),h=w/ratio;if(h>L[1]){h=L[1];w=h*ratio;}s.image.w=w;s.image.h=h;resetLayer(current);refresh(false);});
 $('.kld-text').addEventListener('input',e=>{if(visible().tier==='none'){showStatus('Pilih kategori ukuran cetak terlebih dahulu.','warn');return;}updateText();});
 $('.kld-font').addEventListener('change',updateText);$('.kld-text-color').addEventListener('input',updateText);
 $('.kld-text-width').addEventListener('input',updateText);
 root.querySelectorAll('[data-qty]').forEach(i=>i.addEventListener('input',refreshTotal));
 $('.kld-save').addEventListener('click',saveDesign);
 $('.kld-cart').addEventListener('click',addCart);
 $('.kld-verify').addEventListener('click',requestVerify);
 const canvas=$('.kld-stage');canvas.addEventListener('pointerdown',pointerDown);canvas.addEventListener('pointermove',pointerMove);canvas.addEventListener('pointerup',pointerUp);canvas.addEventListener('pointercancel',pointerUp);
 refresh();
}
function close(){if(busy){showStatus('Tunggu proses penyimpanan selesai.','warn');return;}const el=$('.kld-overlay');if(el)el.remove();document.body.classList.remove('kld-no-scroll');}
async function createDraft(){if(cfg.previewOnly)return;if(designId&&designToken)return {design_id:designId,token:designToken};if(creating)return creating;
 creating=api('init',{product_id:cfg.productId}).then(r=>{designId=r.design_id;designToken=r.token;return r;}).catch(e=>{showStatus(e.message,'error');throw e;});return creating;
}
function open(){if(root.querySelector('.kld-overlay'))return;template();document.body.classList.add('kld-no-scroll');if(!cfg.previewOnly)createDraft().catch(()=>{});}
openBtn.addEventListener('click',open);
/** Fragment never reaches WordPress HTTP logs; immediately remove token from history. */
async function openForkFromFragment(){
 const match=/^#kld-fork=([1-9][0-9]*)\.([a-f0-9]{64})$/.exec(window.location.hash||'');
 if(!match)return;
 history.replaceState(null,'',window.location.pathname+window.location.search);
 if(cfg.previewOnly)return;
 designId=Number(match[1]);designToken=match[2];template();document.body.classList.add('kld-no-scroll');
 try{
  const data=await api('load',{});if(Number(data.product_id)!==Number(cfg.productId))throw Error('Produk tidak cocok.');
  for(const side of ['front','back']){
   const s=sides[side],saved=data.sides?.[side]||{tier:'none'};
   s.tier=tiers.includes(saved.tier)?saved.tier:'none';
   s.placement.dx=Number(saved.placement?.center_offset_cm||0);
   s.placement.dy=Number(saved.placement?.top_from_collar_cm??10.83)-10.83;
   if(saved.image){
    const preview=data.layers?.[side];if(!preview)throw Error('Preview layer tidak tersedia untuk sisi '+side);
    const img=new Image();img.src=preview;await img.decode();
    s.image={...saved.image,aspect:saved.image.w/saved.image.h};
    s.imagePixels={bitmap:img,w:img.naturalWidth,h:img.naturalHeight,alpha:false};
    const cv=document.createElement('canvas');cv.width=32;cv.height=32;
    const ctx=cv.getContext('2d',{willReadFrequently:true});ctx.drawImage(img,0,0,32,32);
    const bytes=ctx.getImageData(0,0,32,32).data;
    for(let i=3;i<bytes.length;i+=4){if(bytes[i]<245){s.alpha=true;break;}}
    s.upload='done';s.restored=true;
   }
   if(saved.text)s.text={...saved.text};
   if(saved.needs_bg_removal)s.bgPreviewOnly=true;
  }
  if(cfg.colors.includes(data.color))color=data.color;
  refresh();showStatus('Salinan independen berhasil dibuka. Desain asli tidak berubah.','ok');
 }catch(e){designId=null;designToken=null;showStatus('Gagal membuka salinan: '+e.message,'error');}
}
if(window.location.hash.startsWith('#kld-fork='))openForkFromFragment();

function addImageLayer(s){if(!s.imagePixels||s.tier==='none')return;const [maxW,maxH]=dims(s.tier);const aspect=s.imagePixels.w/s.imagePixels.h;let w=Math.min(maxW*0.82,24),h=w/aspect;if(h>maxH*.82){h=maxH*.82;w=h*aspect;}s.image={w,h,x:(maxW-w)/2,y:(maxH-h)/2,aspect};}
async function getSmallBitmap(file){
 // Check compressed-file headers before any costly image decode on low-RAM phones.
 if(file.type==='image/jpeg'){
  const b=new Uint8Array(await file.slice(0,262144).arrayBuffer());
  if(b.length<12||b[0]!==255||b[1]!==216)throw Error('Header JPEG tidak valid.');
  let w=0,h=0,i=2;
  while(i+9<b.length){
   if(b[i++]!==255)continue;
   while(i<b.length&&b[i]===255)i++;
   const marker=b[i++];if(marker===217||marker===218)break;
   if(marker===1||(marker>=208&&marker<=215))continue;
   if(i+2>b.length)break;
   const len=b[i]*256+b[i+1];if(len<2||i+len>b.length)break;
   if([192,193,194,195,198,199,201,202,203,205,206,207].includes(marker)){
    h=b[i+3]*256+b[i+4];w=b[i+5]*256+b[i+6];break;
   }
   i+=len;
  }
  if(!w||!h||w>16000||h>16000||w*h>26000000)throw Error('Resolusi JPEG tidak didukung (maks. 26 megapiksel).');
 }
 if(file.type==='image/png'){
  const a=new Uint8Array(await file.slice(0,24).arrayBuffer());
  if(a.length<24||![137,80,78,71,13,10,26,10].every((v,i)=>a[i]===v))throw Error('Header PNG tidak valid.');
  const d=new DataView(a.buffer),w=d.getUint32(16),h=d.getUint32(20);
  if(w>16000||h>16000||w*h>26000000||!w||!h)throw Error('Gambar terlalu besar untuk editor HP. Maksimal 26 megapiksel.');
 }
 let url=URL.createObjectURL(file),img=new Image();img.decoding='async';img.src=url;
 try{await img.decode();}catch(e){URL.revokeObjectURL(url);throw Error('Gambar tidak dapat dibaca.');}
 const naturalW=img.naturalWidth,naturalH=img.naturalHeight;
 if(!naturalW||!naturalH||naturalW>16000||naturalH>16000){URL.revokeObjectURL(url);throw Error('Dimensi gambar tidak didukung.');}
 const max=1100,scale=Math.min(1,max/Math.max(naturalW,naturalH)),w=Math.max(1,Math.round(naturalW*scale)),h=Math.max(1,Math.round(naturalH*scale));
 let bitmap;
 try{bitmap=await createImageBitmap(file,{resizeWidth:w,resizeHeight:h,resizeQuality:'high'});}
 catch(e){const c=document.createElement('canvas');c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);bitmap=c;}
 URL.revokeObjectURL(url);
 const c=document.createElement('canvas');c.width=64;c.height=64;const ctx=c.getContext('2d',{willReadFrequently:true});ctx.drawImage(bitmap,0,0,64,64);
 const rgba=ctx.getImageData(0,0,64,64).data;let alpha=false;
 for(let i=3;i<rgba.length;i+=4){if(rgba[i]<245){alpha=true;break;}}
 return {bitmap,w:naturalW,h:naturalH,alpha};
}
async function onFile(file,side){
 if(!['image/jpeg','image/png'].includes(file.type)){showStatus('Pilih file PNG atau JPEG.','error');return;}
 if(file.size>cfg.maxBytes){showStatus('Ukuran file maksimal 10 MB.','error');return;}
 if(sides[side].tier==='none'){showStatus('Pilih ukuran cetak terlebih dahulu.','warn');return;}
 if(sides[side].upload==='upload'||String(sides[side].upload).startsWith('Mengirim')){showStatus('Tunggu upload sebelumnya selesai sebelum mengganti gambar.','warn');return;}
 const id=++uploadCounter;
 sides[side].bgPreviewOnly=false;
 showStatus('Menyiapkan preview ringan…');
 try{const pixels=await getSmallBitmap(file);const s=sides[side];s.file=file;s.imagePixels=pixels;s.alpha=pixels.alpha;s.restored=false;s.image=null;addImageLayer(s);s.upload=cfg.previewOnly?'preview':'pending';refresh();
 if(!cfg.previewOnly){showStatus('Gambar siap diatur. File asli sedang dikirim ke Drive.');await uploadOriginal(file,side,id);}
 else showStatus('Mode preview: gambar hanya ada di browser. Belum dikirim ke Google Drive.','warn');
 }catch(e){showStatus(e.message,'error');}
}
async function uploadOriginal(file,side,id){const s=sides[side];s.upload='upload';s.uploadError='';refreshUpload();
 try{await createDraft();const start=await api('start-upload',{side,size:file.size,mime:file.type});
  const reply=await new Promise((resolve,reject)=>{
   const xhr=new XMLHttpRequest();xhr.open('PUT',start.upload_url,true);xhr.setRequestHeader('Content-Type',file.type);
   xhr.upload.onprogress=e=>{if(e.lengthComputable){s.upload='Mengirim '+Math.round(e.loaded/e.total*100)+'%';refreshUpload();}};
   xhr.onload=()=>{if(xhr.status>=200&&xhr.status<300){try{resolve(JSON.parse(xhr.responseText));}catch(e){reject(new Error('Respons penyimpanan file tidak dikenali.'));}}else reject(new Error('Upload Google Drive gagal (HTTP '+xhr.status+').'));};
   xhr.onerror=()=>reject(new Error('Upload langsung ke Google Drive gagal. Periksa koneksi/CORS di domain asli.'));
   xhr.send(file);
  });
  if(!reply.id)throw Error('Google Drive tidak mengembalikan file ID.');
  await api('confirm-upload',{side,file_id:reply.id});s.upload='done';refreshUpload();showStatus('File asli sisi '+(side==='front'?'depan':'belakang')+' telah tersimpan.','ok');
 }catch(e){s.upload='failed';s.uploadError=e.message;refreshUpload();showStatus(e.message,'error');}
}
function refreshUpload(){const s=visible();const el=$('.kld-upload-state');if(!el)return;
 el.textContent=s.restored?'✓ File master asli tersedia (salinan)':!s.file?'Belum ada gambar':s.upload==='done'?'✓ File asli tersimpan':s.upload==='failed'?'✕ '+s.uploadError:s.upload==='preview'?'Mode preview: file hanya di browser':s.upload==='pending'?'Menyiapkan upload…':s.upload==='upload'?'Mengirim file asli…':typeof s.upload==='string'?s.upload:'';
 $('.kld-retry').hidden=s.upload!=='failed';$('.kld-remove-bg').disabled=!s.imagePixels||s.alpha;
 $('.kld-image-width').disabled=!s.image;$('.kld-image-width-label').textContent=s.image?s.image.w.toFixed(1)+' cm':'';
 if(s.image){$('.kld-image-width').max=dims(s.tier)[0];$('.kld-image-width').value=s.image.w;}
}
function removeSolidBackground(){
 const s=visible();if(!s.imagePixels||s.alpha)return;
 const original=s.imagePixels.bitmap,c=document.createElement('canvas');c.width=Math.min(original.width||1100,900);c.height=Math.min(original.height||1100,900);
 const ctx=c.getContext('2d',{willReadFrequently:true});ctx.drawImage(original,0,0,c.width,c.height);
 const data=ctx.getImageData(0,0,c.width,c.height),px=data.data,w=c.width,h=c.height;
 const ref=[px[0],px[1],px[2]],tol=48,seen=new Uint8Array(w*h),queue=[];
 function check(i){if(i<0||i>=w*h||seen[i])return;seen[i]=1;let p=i*4;if(Math.abs(px[p]-ref[0])<tol&&Math.abs(px[p+1]-ref[1])<tol&&Math.abs(px[p+2]-ref[2])<tol)queue.push(i);}
 for(let x=0;x<w;x++){check(x);check((h-1)*w+x);}for(let y=0;y<h;y++){check(y*w);check(y*w+w-1);}
 let head=0;while(head<queue.length){const i=queue[head++],p=i*4;px[p+3]=0;const x=i%w,y=(i-x)/w;if(x>0)check(i-1);if(x<w-1)check(i+1);if(y>0)check(i-w);if(y<h-1)check(i+w);}
 ctx.putImageData(data,0,0);s.imagePixels.bitmap=c;s.alpha=true;s.bgPreviewOnly=true;refresh();
 showStatus('Background dihapus hanya pada preview. File asli di Drive tetap utuh; operator produksi perlu memeriksa penghapusan background sebelum cetak.','warn');
}
function updateText(){let s=visible();if(s.tier==='none')return;
 const val=$('.kld-text').value.slice(0,160);if(!val.trim()){s.text=null;refresh(false);return;}
 const [maxW,maxH]=dims(s.tier),w=Math.min(+$('.kld-text-width').value,maxW),lines=val.split('\n').slice(0,5),h=Math.min(maxH,Math.max(2.2,lines.length*1.65));
 if(!s.text)s.text={x:(maxW-w)/2,y:Math.max(0,maxH-h-1),w,h};
 Object.assign(s.text,{value:val,font:$('.kld-font').value,color:$('.kld-text-color').value,w,h});resetLayer(current);refresh(false);
}
const garments=cfg.garments&&Object.keys(cfg.garments).length?cfg.garments:{S:[43,65],M:[46,69],L:[49,73],XL:[51,73],'2XL':[54,76],'3XL':[60,78],'4XL':[63,80],'5XL':[66,80]};
function stageMetrics(){
 if(isMug)return {w:600,h:710,px:156,py:325,scale:16,tierW:18,tierH:8.5,minX:0,maxX:0,minY:0,maxY:0};
 const w=600,h=710,ti=dims(visible().tier),g=garments[shirtSize]||garments.L,scale=Math.min(6,490/g[1],480/g[0]);
 const safeLeft=300-(g[0]-8)*scale/2,safeRight=300+(g[0]-8)*scale/2;
 const safeTop=180,safeBottom=155+g[1]*scale-25;
 const baseX=300-ti[0]*scale/2,baseY=225;
 const state=visible(),dx=state.placement.dx,dy=state.placement.dy;
 const placed=['image','text'].map(k=>state[k]).filter(Boolean);
 const minLayerX=placed.length?Math.min(...placed.map(v=>v.x)):0;
 const minLayerY=placed.length?Math.min(...placed.map(v=>v.y)):0;
 const maxLayerX=placed.length?Math.max(...placed.map(v=>v.x+v.w)):ti[0];
 const maxLayerY=placed.length?Math.max(...placed.map(v=>v.y+v.h)):ti[1];
 const minX=(safeLeft-baseX)/scale-minLayerX,maxX=(safeRight-baseX)/scale-maxLayerX;
 const minY=(safeTop-baseY)/scale-minLayerY,maxY=(safeBottom-baseY)/scale-maxLayerY;
 const allowedX=Math.max(minX,Math.min(maxX,dx)),allowedY=Math.max(minY,Math.min(maxY,dy));
 return {w,h,px:baseX+allowedX*scale,py:baseY+allowedY*scale,scale,tierW:ti[0],tierH:ti[1],minX,maxX,minY,maxY};
}
function draw(){const cv=$('.kld-stage');if(!cv)return;const ctx=cv.getContext('2d'),{px,py,scale,tierW,tierH}=stageMetrics();ctx.clearRect(0,0,600,710);ctx.fillStyle='#f2f4f7';ctx.fillRect(0,0,600,710);
 if(isMug){drawMug(ctx,{px,py,scale,tierW,tierH});return;}
 ctx.fillStyle=cfg.colorSwatches?.[color]||'#363c44';ctx.strokeStyle='#b8bcc2';ctx.lineWidth=2;
 // Standard adult 24s dimensions, centimetres: front panel width x shirt length.
 const garment={S:[43,65],M:[46,69],L:[49,73],XL:[51,73],'2XL':[54,76],'3XL':[60,78],'4XL':[63,80],'5XL':[66,80]};
 const g=garment[shirtSize]||garment.L, left=300-g[0]*scale/2,right=300+g[0]*scale/2,hem=155+g[1]*scale;
 const kind=cfg.productType||'reguler-24s';
 const longSleeve=['hoodie','sweatshirt','lengan-panjang-24s'].includes(kind);
 const tunik=kind==='tunik-oversize34';
 const sleeveDrop=longSleeve?Math.min(hem-60,415):(tunik?330:258);
 const edge=Math.max(12,left-62),far=Math.min(588,right+62);
 ctx.beginPath();ctx.moveTo(left+35,103);ctx.lineTo(left+75,86);
 ctx.quadraticCurveTo(300,123,right-75,86);ctx.lineTo(right-35,103);
 ctx.lineTo(far,180);ctx.lineTo(right+27,sleeveDrop);ctx.lineTo(right,Math.min(sleeveDrop-25,260));
 ctx.lineTo(right,hem);ctx.quadraticCurveTo(300,hem+9,left,hem);
 ctx.lineTo(left,Math.min(sleeveDrop-25,260));ctx.lineTo(left-27,sleeveDrop);ctx.lineTo(edge,180);
 ctx.closePath();ctx.fill();ctx.stroke();
 ctx.strokeStyle=color==='Putih'?'#bbbbbb':'#20262c';ctx.lineWidth=6;
 ctx.beginPath();ctx.moveTo(left+75,86);ctx.quadraticCurveTo(300,162,right-75,86);ctx.stroke();
 if(kind==='hoodie'){
  ctx.strokeStyle=color==='Putih'?'#b3b3b3':'#171e25';ctx.lineWidth=5;
  ctx.beginPath();ctx.moveTo(left+76,100);ctx.quadraticCurveTo(300,-5,right-76,100);ctx.stroke();
  ctx.beginPath();ctx.moveTo(287,147);ctx.lineTo(287,205);ctx.moveTo(313,147);ctx.lineTo(313,205);ctx.stroke();
  ctx.strokeRect(260,hem-125,80,42);
 }
 if(kind==='sweatshirt'||kind==='lengan-panjang-24s'){
  ctx.strokeStyle=color==='Putih'?'#c2c2c2':'#191f25';ctx.lineWidth=2;
  ctx.beginPath();ctx.moveTo(left,hem-16);ctx.lineTo(right,hem-16);ctx.stroke();
 }
 if(tunik){ctx.strokeStyle=color==='Putih'?'#c2c2c2':'#191f25';ctx.lineWidth=2;ctx.beginPath();ctx.moveTo(left,hem-20);ctx.lineTo(right,hem-20);ctx.stroke();}
 if(visible().tier==='none'){ctx.fillStyle='#555';ctx.font='20px Arial';ctx.textAlign='center';ctx.fillText('Pilih ukuran cetak pada sisi ini',300,355);return;}
 ctx.strokeStyle='#4993f5';ctx.lineWidth=2;ctx.setLineDash([9,7]);ctx.strokeRect(px,py,tierW*scale,tierH*scale);ctx.setLineDash([]);
 const s=visible();if(s.image&&s.imagePixels){ctx.drawImage(s.imagePixels.bitmap,px+s.image.x*scale,py+s.image.y*scale,s.image.w*scale,s.image.h*scale);}
 if(s.text&&s.text.value){const t=s.text;ctx.fillStyle=t.color;ctx.textAlign='center';ctx.font=(t.w*scale/Math.max(3,t.value.split('\n').reduce((n,a)=>Math.max(n,a.length),0))*1.1)+'px '+t.font;
 const lines=t.value.split('\n').slice(0,5),lineHeight=t.h*scale/Math.max(1,lines.length);
 lines.forEach((line,i)=>ctx.fillText(line,px+(t.x+t.w/2)*scale,py+(t.y+(i+0.7)*t.h/lines.length)*scale,t.w*scale));}
 ctx.fillStyle='#46586a';ctx.font='13px Arial';ctx.textAlign='center';ctx.fillText((current==='front'?'DEPAN':'BELAKANG')+' • '+s.tier+' • '+tierW+'×'+tierH+' cm',300,686);
}
/** Lightweight vector preview: 18x8.5cm is shown as an unfolded print panel.
 * Curved production artwork still uses original file and recorded cm coordinates.
 */
function drawMug(ctx,{px,py,scale,tierW,tierH}){
 const c=ctx;
 // Side handle and soft shadow keep the vector mockup usable without loading assets.
 c.fillStyle='#cbd5e1';c.beginPath();c.ellipse(309,568,174,13,0,0,Math.PI*2);c.fill();
 c.lineWidth=24;c.strokeStyle='#e3e6eb';c.beginPath();c.ellipse(485,382,62,85,0,-Math.PI/2,Math.PI/2);c.stroke();
 c.lineWidth=4;c.strokeStyle='#aab2bb';c.stroke();
 const gradient=c.createLinearGradient(118,250,480,250);gradient.addColorStop(0,'#d5d9dd');gradient.addColorStop(.15,'#f6f7f8');gradient.addColorStop(.50,'#ffffff');gradient.addColorStop(.85,'#f5f7f9');gradient.addColorStop(1,'#cfd4d9');
 c.fillStyle=gradient;c.beginPath();c.moveTo(125,250);c.lineTo(475,250);c.lineTo(463,538);c.quadraticCurveTo(300,566,137,538);c.closePath();c.fill();
 c.strokeStyle='#aeb5bc';c.lineWidth=2;c.stroke();
 c.fillStyle='#f9fafb';c.beginPath();c.ellipse(300,250,175,22,0,0,Math.PI*2);c.fill();c.stroke();
 c.fillStyle='#c5cad0';c.beginPath();c.ellipse(300,250,150,12,0,0,Math.PI*2);c.fill();
 c.font='14px Arial';c.textAlign='center';c.fillStyle='#5b6772';c.fillText('MUG PUTIH 11 OZ',300,194);
 c.setLineDash([9,7]);c.strokeStyle='#268be4';c.lineWidth=2;c.strokeRect(px,py,tierW*scale,tierH*scale);c.setLineDash([]);
 const state=sides.front;
 if(state.image&&state.imagePixels)c.drawImage(state.imagePixels.bitmap,px+state.image.x*scale,py+state.image.y*scale,state.image.w*scale,state.image.h*scale);
 if(state.text&&state.text.value){
  const t=state.text;c.fillStyle=t.color;c.textAlign='center';
  c.font=(t.w*scale/Math.max(3,t.value.split('\n').reduce((n,a)=>Math.max(n,a.length),0))*1.1)+'px '+t.font;
  t.value.split('\n').slice(0,5).forEach((line,i,arr)=>c.fillText(line,px+(t.x+t.w/2)*scale,py+(t.y+(i+0.7)*t.h/arr.length)*scale,t.w*scale));
 }
 c.textAlign='center';c.fillStyle='#4d6275';c.font='13px Arial';
 c.fillText('Area desain terbentang: 18 × 8,5 cm · simulasi',300,625);
}
function canvasPoint(e){const cv=$('.kld-stage'),r=cv.getBoundingClientRect();return {x:(e.clientX-r.left)/r.width*cv.width,y:(e.clientY-r.top)/r.height*cv.height};}
function pointerDown(e){const s=visible(),p=canvasPoint(e),m=stageMetrics();
 for(const key of ['text','image']){
  const layer=s[key];if(!layer)continue;
  const x=m.px+layer.x*m.scale,y=m.py+layer.y*m.scale;
  if(p.x>=x&&p.x<=x+layer.w*m.scale&&p.y>=y&&p.y<=y+layer.h*m.scale){dragging={pointer:e.pointerId,key,start:p,original:{x:layer.x,y:layer.y}};e.currentTarget.setPointerCapture(e.pointerId);return;}
 }
 if(!isMug&&s.tier!=='none'&&p.x>=m.px&&p.x<=m.px+m.tierW*m.scale&&p.y>=m.py&&p.y<=m.py+m.tierH*m.scale){
  dragging={pointer:e.pointerId,key:'area',start:p,original:{...s.placement}};
  e.currentTarget.setPointerCapture(e.pointerId);
 }
}
function pointerMove(e){if(!dragging||dragging.pointer!==e.pointerId)return;
 const p=canvasPoint(e),m=stageMetrics(),s=visible();
 if(dragging.key==='area'){
  s.placement.dx=Math.max(m.minX,Math.min(m.maxX,dragging.original.dx+(p.x-dragging.start.x)/m.scale));
  s.placement.dy=Math.max(m.minY,Math.min(m.maxY,dragging.original.dy+(p.y-dragging.start.y)/m.scale));draw();return;
 }
 const layer=s[dragging.key];if(!layer)return;
 layer.x=Math.max(0,Math.min(m.tierW-layer.w,dragging.original.x+(p.x-dragging.start.x)/m.scale));
 layer.y=Math.max(0,Math.min(m.tierH-layer.h,dragging.original.y+(p.y-dragging.start.y)/m.scale));draw();
}
function pointerUp(){dragging=null;}
function refresh(updateTextControls=true){const s=visible();root.querySelectorAll('[data-side]').forEach(b=>b.classList.toggle('active',b.dataset.side===current));$('.kld-current-side').textContent=current==='front'?'depan':'belakang';$('.kld-tier').value=s.tier;$('.kld-color').value=color;$('.kld-size').value=shirtSize;
 root.querySelectorAll('.kld-swatch').forEach(b=>{const selected=b.dataset.color===color;b.classList.toggle('active',selected);b.setAttribute('aria-pressed',selected?'true':'false');});
 if(updateTextControls){$('.kld-text').value=s.text?s.text.value:'';$('.kld-font').value=s.text?s.text.font:'Arial';$('.kld-text-color').value=s.text?s.text.color:'#ffffff';$('.kld-text-width').value=s.text?s.text.w:Math.max(1,dims(s.tier)[0]||10);}
 $('.kld-text-width').max=dims(s.tier)[0]||32;$('.kld-text-width-label').textContent=s.text?s.text.w.toFixed(1)+' cm':'';
 refreshUpload();refreshTotal();draw();}
function quantities(){const q={};root.querySelectorAll('[data-qty]').forEach(i=>q[i.dataset.qty]=Math.max(0,Math.min(100,Math.floor(Number(i.value)||0))));return q;}
function refreshTotal(){let n=0,total=0,q=quantities();for(const size in q){n+=q[size];total+=q[size]*pricePerSize(size);}$('.kld-count').textContent=n+' pcs';$('.kld-total-number').textContent=total?money(total):'Pilih area cetak';}
function jsonSide(s){const result={tier:s.tier,placement:isMug?{center_offset_cm:0,top_from_collar_cm:0}:{center_offset_cm:Math.round(s.placement.dx*100)/100,top_from_collar_cm:Math.round((10.83+s.placement.dy)*100)/100}};if(s.tier!=='none'&&s.image)result.image=roundLayer(s.image);if(s.tier!=='none'&&s.text)result.text={...roundLayer(s.text),value:s.text.value,font:s.text.font,color:s.text.color};if(s.bgPreviewOnly)result.needs_bg_removal=true;return result;}
function roundLayer(l){const r={};for(const k of ['x','y','w','h'])r[k]=Math.round(l[k]*100)/100;return r;}
function renderThumb(side,cb){const old=current;current=side;draw();const src=$('.kld-stage');const c=document.createElement('canvas');c.width=380;c.height=450;const x=c.getContext('2d');x.drawImage(src,0,0,c.width,c.height);
 let encoded=c.toDataURL('image/jpeg',0.52);
 if(encoded.length>82000){c.width=290;c.height=345;c.getContext('2d').drawImage(src,0,0,290,345);encoded=c.toDataURL('image/jpeg',.4);}
 current=old;draw();return encoded;
}
function artworkThumbnail(s){
 if(s.tier==='none'||!s.image||!s.imagePixels)return '';
 const src=s.imagePixels.bitmap,w=src.width||src.naturalWidth,h=src.height||src.naturalHeight;
 if(!w||!h)return '';
 for(const [max,quality] of [[440,.65],[360,.48],[280,.35],[210,.27]]){
  const ratio=Math.min(1,max/Math.max(w,h)),canvas=document.createElement('canvas');
  canvas.width=Math.max(1,Math.round(w*ratio));canvas.height=Math.max(1,Math.round(h*ratio));
  canvas.getContext('2d').drawImage(src,0,0,canvas.width,canvas.height);
  const img=canvas.toDataURL('image/webp',quality);
  if(img.startsWith('data:image/webp;base64,')&&img.length<=82000)return img;
 }
 return '';
}
async function saveDesign(){if(!color){showStatus('Tidak ada warna yang aktif untuk produk ini. Hubungi admin Kaoscetak.','error');return;}if(busy)return false;if(cfg.previewOnly){showStatus('Mode preview admin: hubungkan Google Drive untuk mengaktifkan simpan pesanan.','warn');return false;}
 busy=true;$('.kld-save').disabled=true;
 try{await createDraft();if(!['front','back'].some(k=>sides[k].tier!=='none'))throw Error('Pilih minimal satu sisi cetak.');
  for(const k of ['front','back'])if(sides[k].image&&sides[k].upload!=='done')throw Error('File sisi '+(k==='front'?'depan':'belakang')+' belum selesai tersimpan di Google Drive.');
  showStatus('Menyimpan desain dan preview…');const r=await api('save',{sides:{front:jsonSide(sides.front),back:jsonSide(sides.back)},previews:{front:renderThumb('front'),back:isMug?'':renderThumb('back')},artwork:{front:artworkThumbnail(sides.front),back:isMug?'':artworkThumbnail(sides.back)},color});
  showStatus('Desain berhasil disimpan.','ok');return true;
 }catch(e){showStatus(e.message,'error');return false;}finally{busy=false;$('.kld-save').disabled=false;}
}
async function addCart(){if(!color){showStatus('Tidak ada warna yang aktif untuk produk ini. Hubungi admin Kaoscetak.','error');return;}if(cfg.previewOnly){showStatus('Mode preview: checkout dinonaktifkan sampai Drive terhubung.','warn');return;}
 if(!await saveDesign())return;
 busy=true;$('.kld-cart').disabled=true;
 try{showStatus('Menyiapkan keranjang…');const r=await api('add-cart',{quantities:quantities(),color});if(!r.added)throw Error('Keranjang belum siap.');window.location.assign(r.cart_url);}
 catch(e){showStatus(e.message,'error');}finally{busy=false;$('.kld-cart').disabled=false;}
}
async function requestVerify(){if(cfg.previewOnly)return;const email=$('.kld-email').value.trim();if(!email){showStatus('Isi email yang valid.','warn');return;}
 if(!await saveDesign())return;
 try{await api('request-verify',{email});showStatus('Email verifikasi sudah dikirim. Periksa kotak masuk.','ok');}catch(e){showStatus(e.message,'error');}
}
})();