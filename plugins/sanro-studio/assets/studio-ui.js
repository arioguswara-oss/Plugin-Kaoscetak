/* SANRO visual workspace. Moves existing controls; native API, ownership and prices remain authoritative. */
(function(){'use strict';
 const cartToggle=document.querySelector('[data-sanro-mini-cart-toggle]'),cartPanel=document.querySelector('[data-sanro-mini-cart]');
 if(cartToggle&&cartPanel){const setCartOpen=open=>{cartPanel.hidden=!open;cartToggle.setAttribute('aria-expanded',String(open));};cartToggle.addEventListener('click',event=>{if(cartPanel.hidden){event.preventDefault();setCartOpen(true);const target=cartPanel.querySelector('.sanro-mini-cart-view')||cartPanel.querySelector('a,button');if(target)target.focus();}});document.addEventListener('click',event=>{if(!cartPanel.hidden&&!cartPanel.contains(event.target)&&!cartToggle.contains(event.target))setCartOpen(false);});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!cartPanel.hidden){setCartOpen(false);cartToggle.focus();}});}
 const root=document.getElementById('sanro-studio-buyer');if(!root||!root.querySelector('.sanro-controls'))return;
 const q=s=>root.querySelector(s),controls=q('.sanro-controls'),preview=q('.sanro-preview'),layout=q('.sanro-layout');
 const icon=name=>{const paths={image:'M3 3h18v18H3zM3 16l6-6 4 4 3-3 5 5M16 7h.01',text:'M4 4h16M12 4v16M8 20h8',shirt:'M8 3l4 2 4-2 6 5-4 4-2-2v11H8V10l-2 2-4-4z',folder:'M3 6h7l2 2h9v13H3z',center:'M12 2v4m0 12v4M2 12h4m12 0h4M6 6h12v12H6z',trash:'M4 6h16M6 6l1 15h10l1-15M9 6V3h6v3',resize:'M4 10V4h6m4 0h6v6M4 14v6h6m4 0h6v-6',rotate:'M20 11a8 8 0 1 0 1 4M20 4v7h-7'};const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('aria-hidden','true');const p=document.createElementNS(svg.namespaceURI,'path');p.setAttribute('d',paths[name]||paths.shirt);svg.append(p);return svg;};
 function button(label,action,ico){const b=document.createElement('button');b.type='button';if(ico)b.append(icon(ico));b.append(document.createTextNode(label));b.addEventListener('click',action);return b;}
 const panels={};
 for(const [key,title] of [['product','Warna, ukuran & jumlah'],['image','Gambar'],['text','Teks'],['advanced','Pilihan lanjutan']]){const el=document.createElement('section');el.className='sanro-tool-panel';el.dataset.toolPanel=key;const h=document.createElement('h2');h.textContent=title;el.append(h);controls.append(el);panels[key]=el;}
 const moving=new Set();
 function move(selector,to){const el=q(selector);if(!el)return;const node=el.closest('label')||el;moving.add(node);to.append(node);}
 ['.sanro-color-picker','.sanro-size-qty','#sanro-qty-total'].forEach(s=>move(s,panels.product));
 ['#sanro-image','#sanro-background-tolerance-wrap','#sanro-remove-background','#sanro-apply-background','#sanro-restore-background','#sanro-background-status'].forEach(s=>move(s,panels.image));
 ['#sanro-text-input','#sanro-font-size','#sanro-text-rotation','#sanro-text-color','#sanro-center-text','#sanro-remove-text','#sanro-copy-text','#sanro-copy-status'].forEach(s=>move(s,panels.text));
 ['#sanro-reopen-draft','#sanro-reopen-status','#sanro-upload-previews','#sanro-preview-upload-status','#sanro-server-quote','#sanro-server-quote-status','#sanro-reset-side','#sanro-validation'].forEach(s=>move(s,panels.advanced));
 const collection=q('#sanro-collection'),collectionView=document.createElement('section');collectionView.className='sanro-collection-view';collectionView.hidden=true;
 const heading=document.createElement('h2');heading.textContent='Desain Saya';const intro=document.createElement('p');intro.textContent='Buka kembali atau pesan ulang desainmu';collectionView.append(heading,intro);if(collection)collectionView.append(collection);const privacy=document.createElement('p');privacy.className='sanro-private-note';privacy.textContent='Desain privat milikmu';collectionView.append(privacy);root.append(collectionView);
 for(const child of Array.from(controls.children)){if(child.classList.contains('sanro-tool-panel'))continue;if(child.tagName==='H2'){child.remove();continue;}if(child.tagName==='FIELDSET')panels.advanced.append(child);}
 const estimate=q('#sanro-price-estimate'),actions=document.createElement('section');actions.className='sanro-purchase-bar';actions.setAttribute('aria-label','Harga dan pemesanan');
 const price=document.createElement('div');price.className='sanro-price-summary';if(estimate)price.append(estimate);actions.append(price);
 const buttons=document.createElement('div');buttons.className='sanro-primary-actions';for(const [id,label]of [['#sanro-save-draft','Simpan Desain'],['#sanro-add-to-cart','Ke Keranjang']]){const b=q(id);if(b){b.textContent=label;buttons.append(b);}}
 actions.append(buttons);for(const id of ['#sanro-save-status','#sanro-upload-master','#sanro-master-upload-status','#sanro-cart-status']){const el=q(id);if(el)actions.append(el);}root.append(actions);
 const advanced=document.createElement('details');advanced.className='sanro-advanced';const summary=document.createElement('summary');summary.textContent='Opsi lanjutan & verifikasi';advanced.append(summary,panels.advanced);controls.append(advanced);
 function showPanel(name,scroll=true){const mine=name==='collection';root.classList.toggle('sanro-show-collection',mine);layout.hidden=mine;actions.hidden=mine;collectionView.hidden=!mine;for(const [key,panel]of Object.entries(panels)){if(key!=='advanced')panel.hidden=mine||key!==name;}root.querySelectorAll('[data-tool]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.tool===name)));if(scroll&&!mine&&window.matchMedia('(max-width:750px)').matches)controls.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion:reduce)').matches?'auto':'smooth',block:'nearest'});}
 const nav=document.createElement('nav');nav.className='sanro-bottom-nav';nav.setAttribute('aria-label','Alat desain');
 for(const [name,label,ico]of [['image','Gambar','image'],['text','Teks','text'],['product','Produk','shirt'],['collection','Desain Saya','folder']]){const b=button(label,()=>showPanel(name),ico);b.dataset.tool=name;nav.append(b);}root.append(nav);
 const toolbar=document.createElement('div');toolbar.className='sanro-object-toolbar';toolbar.hidden=true;preview.append(toolbar);
 const safe=q('.sanro-safe'),text=safe.querySelector('.sanro-text'),image=safe.querySelector('img'),shirt=q('.sanro-shirt');
 let selected=null,selectedSide=null;
 const box=document.createElement('div');box.className='sanro-selection-box';box.hidden=true;safe.append(box);
 const handles=['nw','ne','sw','se'].map(pos=>{const h=button('',()=>{});h.className='sanro-resize-handle sanro-resize-'+pos;h.setAttribute('aria-label','Tarik sudut untuk mengubah ukuran proporsional');box.append(h);return h;});
 const rotateHandle=button('',()=>{},'rotate');rotateHandle.className='sanro-rotate-handle';rotateHandle.setAttribute('aria-label','Tarik untuk memutar objek');box.append(rotateHandle);
 const objects={image,text};
 const target=()=>objects[selected];
 function select(name){selected=name;selectedSide=shirt.dataset.side;updateSelection();}
 function updateSelection(){
  const el=target();
  if(!el||el.hidden||selectedSide!==shirt.dataset.side)selected=null;
  box.hidden=toolbar.hidden=!selected;
  if(!selected)return;
  const r=el.getBoundingClientRect(),s=safe.getBoundingClientRect();
  box.style.left=(r.left-s.left-safe.clientLeft)+'px';box.style.top=(r.top-s.top-safe.clientTop)+'px';
  box.style.width=r.width+'px';box.style.height=r.height+'px';
 }
 const arrows={ArrowUp:[0,-1],ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowDown:[0,1]};
 for(const [kind,el] of Object.entries(objects)){
  el.addEventListener('pointerdown',()=>select(kind));
  el.addEventListener('focus',()=>select(kind));
  el.addEventListener('keydown',event=>{
   if(event.key==='Escape'){select(null);return;}
   const direction=arrows[event.key];
   if(direction){event.preventDefault();root.sanroMoveObject(kind,...direction);}
  });
 }
 shirt.addEventListener('pointerdown',event=>{
  if(!image.contains(event.target)&&!text.contains(event.target)&&!box.contains(event.target))select(null);
 });
 toolbar.append(
  button('Tengah',()=>q(selected==='image'?'#sanro-center-image':'#sanro-center-text').click(),'center'),
  button('Hapus',()=>q(selected==='image'?'#sanro-remove-image':'#sanro-remove-text').click(),'trash')
 );
 let transform=null;
 function field(kind,type){return q(kind==='image'?(type==='size'?'#sanro-image-size':'#sanro-image-rotation'):(type==='size'?'#sanro-font-size':'#sanro-text-rotation'));}
 function applyTransform(e){if(!transform||transform.pointer!==e.pointerId)return;const dx=e.clientX-transform.cx,dy=e.clientY-transform.cy;
  if(transform.type==='rotate'){const angle=Math.atan2(dy,dx)*180/Math.PI;let value=transform.rotation+angle-transform.startAngle;value=Math.max(-180,Math.min(180,value));const f=field(transform.kind,'rotation');f.value=String(value);f.dispatchEvent(new Event('input',{bubbles:true}));}
  else{const distance=Math.hypot(dx,dy),ratio=Math.max(.15,Math.min(3,distance/transform.distance)),f=field(transform.kind,'size');f.value=String(Math.max(Number(f.min),Math.min(Number(f.max),transform.value*ratio)));f.dispatchEvent(new Event('input',{bubbles:true}));}
  updateSelection();
 }
 function startTransform(e,type){if(!e.isPrimary)return;e.preventDefault();e.stopPropagation();const r=target().getBoundingClientRect(),cx=r.left+r.width/2,cy=r.top+r.height/2,f=field(selected,type==='rotate'?'rotation':'size');transform={pointer:e.pointerId,type,kind:selected,cx,cy,distance:Math.max(1,Math.hypot(e.clientX-cx,e.clientY-cy)),value:Number(f.value),rotation:Number(field(selected,'rotation').value)||0,startAngle:Math.atan2(e.clientY-cy,e.clientX-cx)*180/Math.PI};e.currentTarget.setPointerCapture(e.pointerId);}
 for(const h of handles){h.addEventListener('pointerdown',e=>startTransform(e,'resize'));h.addEventListener('pointermove',applyTransform);for(const event of ['pointerup','pointercancel','lostpointercapture'])h.addEventListener(event,()=>{transform=null;});}
 rotateHandle.addEventListener('pointerdown',e=>startTransform(e,'rotate'));rotateHandle.addEventListener('pointermove',applyTransform);for(const event of ['pointerup','pointercancel','lostpointercapture'])rotateHandle.addEventListener(event,()=>{transform=null;});
 root.addEventListener('sanro:render',updateSelection);window.addEventListener('resize',updateSelection);root.addEventListener('input',()=>requestAnimationFrame(updateSelection));
 // Keep native preview/upload/collection functionality reachable, without a long default form.
 showPanel('product',false);if(location.hash==='#sanro-reorder')showPanel('product');
 const collectionLink=root.querySelector('[data-show-collection]');if(collectionLink)collectionLink.addEventListener('click',()=>showPanel('collection'));
 root.addEventListener('sanro:collection',()=>showPanel('collection'));
 root.addEventListener('sanro:show-panel',event=>{const name=event.detail&&event.detail.name;if(Object.prototype.hasOwnProperty.call(panels,name))showPanel(name);});
 updateSelection();
})();
