'use strict';
// Interactive native visual print zones; server validation remains authoritative.
// Only existing validated product-meta fields are submitted on Save.
// This script does not perform fetch, REST writes, or WooCommerce order calls.
(function(){
  const sides=['front','back'];
  function field(side,key){
    return document.getElementById('_sanro_studio_zone_'+side+'_'+key);
  }
  function mockupUrl(side){
    const thumb=document.getElementById('sanro-mockup-preview-'+side);
    if(!thumb||thumb.hidden)return '';
    const raw=thumb.getAttribute('src');
    if(!raw)return '';
    try{
      const parsed=new URL(raw,window.location.href);
      return parsed.protocol==='http:'||parsed.protocol==='https:'?parsed.href:'';
    }catch(_error){return ''}
  }
  function render(side){
    if(!sides.includes(side))return;
    const stage=document.querySelector('[data-sanro-printzone-side="'+side+'"]');
    const status=document.querySelector('[data-sanro-zone-status="'+side+'"]');
    if(!stage||!status)return;
    const guide=stage.querySelector('.sanro-printzone-guide');
    const image=stage.querySelector('.sanro-printzone-image');
    if(!guide||!image)return;
    const values={};
    for(const key of ['x','y','width','height']){
      const input=field(side,key);
      if(!input||!input.value.trim()){guide.hidden=true;
        status.textContent='Isi semua posisi dan ukuran visual '+side+'.';return;}
      values[key]=Number(input.value);
    }
    const valid=Object.values(values).every(Number.isFinite)
      &&values.x>=0&&values.y>=0&&values.width>0&&values.height>0
      &&values.x+values.width<=100&&values.y+values.height<=100;
    guide.hidden=!valid;
    if(valid){
      guide.style.left=values.x+'%';
      guide.style.top=values.y+'%';
      guide.style.width=values.width+'%';
      guide.style.height=values.height+'%';
      status.textContent='Area '+(side==='front'?'depan':'belakang')
        +': '+values.width+'% × '+values.height+'% pada mockup. Batas cetak cm tidak berubah.';
    }else{
      status.textContent='Area visual tidak valid: nilai harus dalam 0–100% dan tidak boleh melewati tepi mockup.';
    }
    const url=mockupUrl(side);
    if(url){
      image.src=url;
      image.hidden=false;
    }else{
      image.removeAttribute('src');
      image.hidden=true;
    }
  }
  function enableDrag(side){
    const stage=document.querySelector('[data-sanro-printzone-side="'+side+'"]');if(!stage)return;
    const guide=stage.querySelector('.sanro-printzone-guide');if(!guide||guide.dataset.interactive)return;guide.dataset.interactive='true';guide.removeAttribute('aria-hidden');guide.tabIndex=0;guide.setAttribute('role','group');guide.setAttribute('aria-label','Area cetak '+(side==='front'?'Depan':'Belakang')+'. Geser atau gunakan tombol panah.');guide.style.pointerEvents='auto';guide.style.touchAction='none';
    const handle=document.createElement('button');handle.type='button';handle.className='sanro-zone-resize';handle.setAttribute('aria-label','Ubah ukuran area '+side+'; tombol panah untuk ukuran');guide.append(handle);let start=null;
    function apply(values){for(const [key,value]of Object.entries(values)){const input=field(side,key);input.value=String(Math.round(value*10)/10);input.dispatchEvent(new Event('input',{bubbles:true}));}}
    guide.addEventListener('pointerdown',e=>{if(!e.isPrimary)return;e.preventDefault();start={id:e.pointerId,x:e.clientX,y:e.clientY,resize:e.target===handle,values:Object.fromEntries(['x','y','width','height'].map(k=>[k,Number(field(side,k).value)]))};guide.setPointerCapture(e.pointerId);});
    guide.addEventListener('pointermove',e=>{if(!start||start.id!==e.pointerId)return;const rect=stage.getBoundingClientRect();if(!rect.width||!rect.height)return;const dx=(e.clientX-start.x)/rect.width*100,dy=(e.clientY-start.y)/rect.height*100,v=start.values;if(start.resize)apply({width:Math.max(1,Math.min(100-v.x,v.width+dx)),height:Math.max(1,Math.min(100-v.y,v.height+dy))});else apply({x:Math.max(0,Math.min(100-v.width,v.x+dx)),y:Math.max(0,Math.min(100-v.height,v.y+dy))});});
    for(const event of ['pointerup','pointercancel','lostpointercapture'])guide.addEventListener(event,()=>{start=null;});
    guide.addEventListener('keydown',e=>{const directions={ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]};const d=directions[e.key];if(!d)return;e.preventDefault();const v=Object.fromEntries(['x','y','width','height'].map(k=>[k,Number(field(side,k).value)]));if(e.target===handle)apply({width:Math.max(1,Math.min(100-v.x,v.width+d[0])),height:Math.max(1,Math.min(100-v.y,v.height+d[1]))});else apply({x:Math.max(0,Math.min(100-v.width,v.x+d[0])),y:Math.max(0,Math.min(100-v.height,v.y+d[1]))});});
  }
  function renderAll(){sides.forEach(side=>{enableDrag(side);render(side);});}
  function onEdit(event){
    const id=event.target&&typeof event.target.id==='string'?event.target.id:'';
    const zone=/^_sanro_studio_zone_(front|back)_(x|y|width|height)$/.exec(id);
    if(zone){render(zone[1]);return;}
    const mockup=/^_sanro_studio_mockup_(front|back)$/.exec(id);
    if(mockup)render(mockup[1]);
  }
  document.addEventListener('input',onEdit);
  document.addEventListener('change',onEdit);
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',renderAll,{once:true});
  }else{
    renderAll();
  }
})();
