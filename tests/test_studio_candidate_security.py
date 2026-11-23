"""Audit regression: canonical members and bounded inputs, no network/deployment."""
import sys
sys.dont_write_bytecode=True
import hashlib,importlib.util,json,pathlib,tempfile,unittest,zipfile
spec=importlib.util.spec_from_file_location('preflight',pathlib.Path(__file__).resolve().parents[1]/'tools/studio-candidate-preflight.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class CandidateSecurity(unittest.TestCase):
 def setUp(self):self.tmp=tempfile.TemporaryDirectory();self.root=pathlib.Path(self.tmp.name)
 def tearDown(self):self.tmp.cleanup()
 def candidate(self,extra='includes/a.php',bad_bytes=None):
  contents={'sanro-studio.php':b'<?php // safe',extra:b'X'};files=[{'path':p,'bytes':len(b),'sha256':hashlib.sha256(b).hexdigest()} for p,b in contents.items()]
  if bad_bytes is not None:files[-1]['bytes']=bad_bytes
  manifest={'schema':1,'source_commit':'a'*40,'plugin_digest':hashlib.sha256(json.dumps(files,sort_keys=True,separators=(',',':')).encode()).hexdigest(),'files':files,'generator_default':'OFF','drive_default':'OFF','deployment_authorized':False}
  marker=json.dumps({k:manifest[k] for k in ('schema','source_commit','plugin_digest')}).encode();manifest['build_marker']={'path':'SANRO-STUDIO-BUILD.json','bytes':len(marker),'sha256':hashlib.sha256(marker).hexdigest()};contents['SANRO-STUDIO-BUILD.json']=marker
  z=self.root/'candidate.zip'
  with zipfile.ZipFile(z,'w') as out:
   for name,data in contents.items():
    info=zipfile.ZipInfo('sanro-studio/'+name);info.external_attr=0o100644<<16;out.writestr(info,data)
  manifest['archive_sha256']=hashlib.sha256(z.read_bytes()).hexdigest();mf=self.root/'manifest.json';mf.write_text(json.dumps(manifest));return z,mf
 def validate(self,z,mf):return m.validate(z,mf,'https://saddlebrown-peafowl-616656.hostingersite.com')
 def test_normal_candidate(self):z,mf=self.candidate();self.assertEqual(self.validate(z,mf)['mode'],'PLAN_ONLY')
 def test_normalized_aliases_refused(self):
  for name in ['includes//a.php','includes/./a.php','includes/a.php/','./a.php','.hidden/file.php','includes/../a.php']:
   with self.subTest(name=name):
    z,mf=self.candidate(name)
    with self.assertRaisesRegex(ValueError,'unsafe_manifest_member'):self.validate(z,mf)
 def test_boolean_length_not_integer(self):
  z,mf=self.candidate(bad_bytes=True)
  with self.assertRaisesRegex(ValueError,'invalid_member_metadata'):self.validate(z,mf)
 def test_large_sparse_archive_refused_before_read(self):
  z,mf=self.candidate()
  with z.open('wb') as f:f.truncate(50_000_001)
  with self.assertRaisesRegex(ValueError,'input_size_or_type_refused'):self.validate(z,mf)
 def test_large_manifest_refused_before_json_parse(self):
  z,mf=self.candidate()
  with mf.open('wb') as f:f.truncate(2_000_001)
  with self.assertRaisesRegex(ValueError,'input_size_or_type_refused'):self.validate(z,mf)
 def test_archive_symlink_refused(self):
  z,mf=self.candidate();alias=self.root/'alias.zip';alias.symlink_to(z)
  with self.assertRaises(OSError):self.validate(alias,mf)
if __name__=='__main__':unittest.main()
