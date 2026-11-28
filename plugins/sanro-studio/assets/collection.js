'use strict';
(function(){
 const root=document.getElementById('sanro-studio-buyer'),config=window.SANRO_STUDIO_EDITOR_CONFIG;
 if(!root||!config||!config.draft_api)return;
 const panel=root.querySelector('#sanro-collection');if(!panel)return;
 const q=s=>panel.querySelector(s),status=q('[data-status]'),list=q('[data-list]');
 try{const saved=JSON.parse(sessionStorage.getItem('sanro-studio-draft:'+config.product_id)||'null');if(saved&&typeof saved.collectionTitle==='string')q('[data-title]').value=saved.collectionTitle;}catch(_error){}
 const base=config.draft_api.url.replace(/\/drafts\/?$/,'');let session='',shared=null,cursor=null,busy=false;
 try{session=sessionStorage.getItem('sanro-collection-session')||'';}catch(_error){}
 function remember(token){session=token;try{sessionStorage.setItem('sanro-collection-session',token);}catch(_error){}}
 async function api(path,body,share){
  const response=await fetch(base+path,{method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',
   headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Collection-Token':session,'X-Sanro-Share-Token':share||''},body:body===undefined?undefined:JSON.stringify(body)});
  const value=await window.SanroStudioTransport.readJson(response);
  if(!response.ok||!value.valid)throw new Error(value.message||'Koleksi belum dapat diakses ('+response.status+').');return value;
 }
 function button(label,action,parent){const b=document.createElement('button');b.type='button';b.textContent=label;b.addEventListener('click',()=>run(()=>action(b)));parent.append(b);return b;}
 function showEmpty(message){const p=document.createElement('p');p.className='sanro-collection-empty';p.textContent=message;list.replaceChildren(p);}
 async function run(action){if(busy)return;busy=true;panel.setAttribute('aria-busy','true');status.textContent='Memeriksa akses koleksi…';try{await action();}catch(error){status.textContent=error.message||'Koleksi belum dapat diakses.';if(!list.children.length)showEmpty('Koleksi privat. Periksa email/kode akses, atau buka koleksi yang tersimpan pada sesi ini.');}finally{busy=false;panel.removeAttribute('aria-busy');}}
 async function fork(entry,secret,reorder=false){
  const fresh=await api('/collection/'+entry.id+'/fork',{},secret);
  sessionStorage.setItem('sanro-studio-draft:'+fresh.product_id,JSON.stringify({id:fresh.design_id,token:fresh.owner_token,collectionTitle:entry.title}));
  const target=new URL(location.href);target.hash=reorder?'sanro-reorder':'';target.searchParams.set('sanro_studio_editor','1');target.searchParams.set('product_id',fresh.product_id);location.assign(target.href);
 }
 async function card(entry,secret){
  const article=document.createElement('article'),title=document.createElement('h4');article.className='sanro-collection-card';title.textContent=(entry.title||'Desain saya')+(entry.public_design_id?' · '+entry.public_design_id:'');article.append(title);list.append(article);
  for(const side of ['front','back']){
   try{const preview=await api('/collection/'+entry.id+'/preview/'+side,undefined,secret);if(!['image/png','image/jpeg','image/webp'].includes(preview.mime))continue;
    const img=document.createElement('img');img.width=160;img.height=172;img.style.objectFit='contain';img.alt=side==='front'?'Preview depan':'Preview belakang';img.src='data:'+preview.mime+';base64,'+preview.image_base64;article.append(img);
   }catch(_error){const p=document.createElement('p');p.textContent='Preview '+side+' belum tersedia.';article.append(p);}
  }
  button(secret?'Buka Salinan':'Buka Desain',()=>fork(entry,secret),article);
  button('Pesan Lagi',()=>fork(entry,secret,true),article);
  if(secret)return;
  button('Bagikan',async()=>{const value=await api('/collection/'+entry.id+'/share',{});const url=new URL(location.href);url.searchParams.set('product_id',entry.product_id);url.hash='sanro-share='+entry.id+'.'+value.share_token;
   const output=document.createElement('input');output.type='text';output.readOnly=true;output.value=url.href;output.setAttribute('aria-label','Tautan berbagi desain');article.append(output);output.select();status.textContent='Salin tautan ini. Hanya pemegang tautan dapat melihat preview dan membuat salinan.';},article);
  button('Cabut share',async()=>{await api('/collection/'+entry.id+'/revoke',{});article.querySelectorAll('input').forEach(n=>n.remove());status.textContent='Share dicabut. Salinan yang telah dibuat tetap independen.';},article);
  button('Hapus dari koleksi',async()=>{if(!window.confirm('Hapus desain ini dari koleksi? Pesanan dan salinan lain tetap tersimpan.'))return;await api('/collection/'+entry.id+'/delete',{});article.remove();status.textContent='Desain dihapus dari koleksi.';},article);
 }
 async function load(append=false){if(!append){list.replaceChildren();cursor=null;}const value=await api('/collection'+(cursor?'?cursor='+encodeURIComponent(cursor):''));
  for(const entry of value.entries)await card(entry);cursor=value.cursor;q('[data-more]').hidden=!cursor;if(value.entries.length){status.textContent='Koleksi privat terverifikasi.';}else{status.textContent='Akses terverifikasi. Belum ada desain tersimpan.';showEmpty('Desain yang kamu simpan akan muncul di sini.');}
 }
 q('[data-request]').addEventListener('click',()=>run(async()=>{await api('/collection/access/request',{email:q('[data-email]').value});status.textContent='Kode dikirim ke email. Berlaku 30 menit.';}));
 q('[data-verify]').addEventListener('click',()=>run(async()=>{const value=await api('/collection/access/verify',{email:q('[data-email]').value,code:q('[data-code]').value.trim()});q('[data-code]').value='';remember(value.collection_token);await load();}));
 q('[data-open]').addEventListener('click',()=>run(()=>load()));q('[data-more]').addEventListener('click',()=>run(()=>load(true)));
 q('[data-logout]').addEventListener('click',()=>run(async()=>{await api('/collection/logout',{});remember('');showEmpty('Koleksi privat. Verifikasi email untuk membuka desainmu.');status.textContent='Akses koleksi pada sesi ini telah dicabut.';}));
 q('[data-save]').addEventListener('click',()=>run(async()=>{
  if(typeof root.sanroCollectionReady!=='function')throw new Error('Editor belum siap.');const credentials=await root.sanroCollectionReady();
  const response=await fetch(base+'/drafts/'+credentials.id+'/collection',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-Sanro-Draft-Nonce':config.draft_api.nonce,...(config.draft_api.rest_nonce?{'X-WP-Nonce':config.draft_api.rest_nonce}:{}),'X-Sanro-Owner-Token':credentials.token},body:JSON.stringify({title:q('[data-title]').value||'Desain saya'})});
  const value=await window.SanroStudioTransport.readJson(response);if(!response.ok||!value.valid)throw new Error(value.message||'Verifikasi email pemilik desain dan simpan draft terbaru terlebih dahulu.');remember(value.collection_token);await load();status.textContent='Snapshot tersimpan permanen. Share lama otomatis dicabut.';
 }));
 // Fragment secrets are removed from browser history and never sent as query/referrer parameters.
 const match=location.hash.match(/^#sanro-share=([a-f0-9-]{36})\.([a-f0-9]{64})$/);
 if(session){status.textContent='Akses koleksi tersimpan pada sesi ini. Pilih “Buka koleksi sesi ini” untuk memuat desain privatmu.';}else{showEmpty('Koleksi privat. Verifikasi email atau buka koleksi yang tersimpan pada sesi ini.');status.textContent='Desain Saya menyimpan dan membuka desain privat milikmu.';}
 if(match){shared={id:match[1],secret:match[2]};history.replaceState(null,'',location.pathname+location.search);run(async()=>{const value=await api('/collection/'+shared.id,undefined,shared.secret);list.replaceChildren();await card(value.entry,shared.secret);status.textContent='Preview berbagi. Edit/beli akan membuat salinan milik Anda.';root.dispatchEvent(new CustomEvent('sanro:collection'));panel.scrollIntoView();});}
})();
