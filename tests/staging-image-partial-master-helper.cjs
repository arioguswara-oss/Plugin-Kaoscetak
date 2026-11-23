'use strict';
/**
 * Test-only recovery of an ALREADY persisted front master.
 * Never authorizes uploads, mutates server state, or weakens quotas.
 * The owner-authenticated GET must provide matching bytes for the exact
 * synthetic QA PNG before any network response may be reused in the runner.
 */
const assert=require('node:assert/strict');
const crypto=require('node:crypto');

function verifiedExistingFrontUpload(readback,designId,expectedBase64){
  assert(typeof designId==='string'&&
    /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(designId),
    'Invalid QA draft ID');
  assert(typeof expectedBase64==='string'&&
    /^[A-Za-z0-9+/]+={0,2}$/.test(expectedBase64),'Invalid expected synthetic PNG');
  assert(readback&&typeof readback==='object'&&readback.valid===true&&
    readback.side==='front'&&readback.mime==='image/png',
    'Owned front master readback unavailable or wrong MIME');
  const bytes=Buffer.from(expectedBase64,'base64');
  assert(bytes.length>100&&bytes.length<=10485760,
    'Unexpected synthetic PNG byte size');
  assert(bytes.subarray(0,8).equals(Buffer.from('89504e470d0a1a0a','hex')),
    'Expected QA image is not PNG');
  const sha256=crypto.createHash('sha256').update(bytes).digest('hex');
  assert(readback.sha256===sha256,'Stored master differs from synthetic front image');
  assert(readback.image_base64===expectedBase64,'Stored front bytes differ from QA fixture');
  assert(typeof readback.file_id==='string'&&
    new RegExp('^'+designId.toLowerCase()+'-front-[a-f0-9]{64}\\.png$','i')
      .test(readback.file_id),'Master filename is not bound to the expected draft');
  return {
    valid:true,design_id:designId,side:'front',
    file_id:readback.file_id,mime:'image/png',
    bytes:bytes.length,sha256
  };
}
module.exports={verifiedExistingFrontUpload};
