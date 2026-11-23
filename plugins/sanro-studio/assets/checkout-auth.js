'use strict';
(function(){
 function authorize(){
  var field=document.getElementById('sanro_studio_checkout_token');
  if(!field)return true;
  field.value='';
  var product=field.getAttribute('data-product-id');
  var design=field.getAttribute('data-design-id');
  if(!/^[1-9][0-9]*$/.test(product||'')||!/^[a-f0-9-]{36}$/i.test(design||''))return false;
  try{
   var stored=JSON.parse(sessionStorage.getItem('sanro-studio-draft:'+product)||'null');
   if(stored&&stored.id===design&&typeof stored.token==='string'&&/^[a-f0-9]{64}$/.test(stored.token)){
    field.value=stored.token;
    return true;
   }
  }catch(_error){}
  return false;
 }
 function warn(){
  var message=document.querySelector('.sanro-studio-checkout-help');
  if(message)message.textContent='Otorisasi desain tidak ditemukan di tab ini. Kembali ke editor, simpan desain, lalu ulangi checkout.';
 }
 document.addEventListener('DOMContentLoaded',function(){
  var form=document.querySelector('form.checkout');
  if(!form)return;
  form.addEventListener('submit',function(){if(!authorize())warn();},true);
  if(window.jQuery){
   window.jQuery(document.body).on('checkout_place_order',function(){
    if(!authorize()){warn();return false;}
    return true;
   });
   window.jQuery(document.body).on('updated_checkout',function(){authorize();});
  }
  authorize();
 });
})();
