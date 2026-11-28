/* Safe diagnostics: never echo HTML, owner tokens, URLs, or server bodies. */
(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.SanroStudioTransport=api;})(typeof globalThis!=='undefined'?globalThis:this,function(){
 'use strict';
 async function readJson(response){
  let body;try{body=await response.json();}catch(error){throw new Error('Respons preview bukan JSON (HTTP '+response.status+'). Hubungi toko; pemeriksaan transport diperlukan.');}
  if(!response.ok){const code=body&&typeof body.code==='string'&&/^sanro_[a-z0-9_]{1,64}$/.test(body.code)?body.code:'response_rejected';
   throw new Error('Verifikasi preview ditolak (HTTP '+response.status+'; '+code+').');}
  if(!body||typeof body!=='object'||Array.isArray(body))throw new Error('Format respons preview tidak valid.');return body;
 }
 return Object.freeze({readJson});
});
