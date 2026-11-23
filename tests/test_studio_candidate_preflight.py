"""Synthetic candidate validation; never contacts a server or writes a plugin tree."""
import sys
sys.dont_write_bytecode=True
import hashlib,importlib.util,json,pathlib,tempfile,unittest,zipfile
p=pathlib.Path(__file__).resolve().parents[1]/'tools/studio-candidate-preflight.py'
spec=importlib.util.spec_from_file_location('preflight',p);module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class CandidateTest(unittest.TestCase):
 def test_candidate_and_refusals(self):
  with tempfile.TemporaryDirectory() as d:
   root=pathlib.Path(d);data=b'<?php // no activation\n';files=[{'path':'sanro-studio.php','sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data)}]
   m={'schema':1,'source_commit':'a'*40,'files':files,'plugin_digest':hashlib.sha256(json.dumps(files,sort_keys=True,separators=(',',':')).encode()).hexdigest(),'generator_default':'OFF','drive_default':'OFF','deployment_authorized':False}
   marker=(json.dumps({k:m[k] for k in ('schema','source_commit','plugin_digest')})+'\n').encode();m['build_marker']={'path':'SANRO-STUDIO-BUILD.json','sha256':hashlib.sha256(marker).hexdigest(),'bytes':len(marker)}
   z=root/'candidate.zip';mf=root/'manifest.json'
   with zipfile.ZipFile(z,'w') as f:
    for name,b in [('sanro-studio.php',data),('SANRO-STUDIO-BUILD.json',marker)]:
     info=zipfile.ZipInfo('sanro-studio/'+name);info.external_attr=0o100644<<16;f.writestr(info,b)
   m['archive_sha256']=hashlib.sha256(z.read_bytes()).hexdigest();mf.write_text(json.dumps(m));url='https://saddlebrown-peafowl-616656.hostingersite.com'
   result=module.validate(z,mf,url);self.assertEqual(result['mode'],'PLAN_ONLY');self.assertFalse(result['deployment_authorized'])
   with self.assertRaisesRegex(ValueError,'staging_target_only'):module.validate(z,mf,'https://kaoscetak.id')
   with self.assertRaisesRegex(ValueError,'staging_target_only'):module.validate(z,mf,url+'@evil.test')
   m['drive_default']='ON';mf.write_text(json.dumps(m))
   with self.assertRaisesRegex(ValueError,'unsafe_manifest_flags'):module.validate(z,mf,url)
   m['drive_default']='OFF';m['archive_sha256']='0'*64;mf.write_text(json.dumps(m))
   with self.assertRaisesRegex(ValueError,'archive_digest_mismatch'):module.validate(z,mf,url)
if __name__=='__main__':unittest.main()
