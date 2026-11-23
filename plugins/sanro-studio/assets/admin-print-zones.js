'use strict';
// Live, read-only WooCommerce admin guide for front/back visual print zones.
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
  function renderAll(){sides.forEach(render);}
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
