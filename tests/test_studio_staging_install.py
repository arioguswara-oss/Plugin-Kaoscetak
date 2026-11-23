"""Filesystem-only tests: never runs WP CLI, reaches a website or deploys."""
import sys
sys.dont_write_bytecode=True
from unittest.mock import patch
from types import SimpleNamespace
import hashlib,importlib.util,json,pathlib,tempfile,unittest,zipfile
spec=importlib.util.spec_from_file_location('installer',pathlib.Path(__file__).resolve().parents[1]/'tools/studio-staging-install.py');installer=importlib.util.module_from_spec(spec);spec.loader.exec_module(installer)
class InstallTest(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.root=pathlib.Path(self.temp.name);self.target=self.root/'plugins/sanro-studio';self.target.mkdir(parents=True);(self.target/'sanro-studio.php').write_text('old plugin');self.back=self.root/'private-rollback';self.back.mkdir(mode=0o700);self.before=installer.tree_digest(self.target)
  data=b'<?php // candidate';self.m={'source_commit':'a'*40,'plugin_digest':'b'*64,'archive_sha256':'c'*64,'files':[{'path':'sanro-studio.php','bytes':len(data),'sha256':hashlib.sha256(data).hexdigest()}],'build_marker':{'path':'SANRO-STUDIO-BUILD.json','bytes':2,'sha256':hashlib.sha256(b'{}').hexdigest()}};self.z=self.root/'candidate.zip'
  with zipfile.ZipFile(self.z,'w') as z:z.writestr('sanro-studio/sanro-studio.php',data);z.writestr('sanro-studio/SANRO-STUDIO-BUILD.json',b'{}')
 def tearDown(self):self.temp.cleanup()
 def test_replacement_preserves_rollback_and_other_plugins(self):
  other=self.root/'plugins/woocommerce';other.mkdir();(other/'keep').write_text('untouched')
  r=installer.replace_existing(self.z,self.m,self.target,self.back,self.before);self.assertEqual(r['state'],'files_replaced');self.assertEqual((other/'keep').read_text(),'untouched');old=[p for p in self.back.iterdir() if p.is_dir()];self.assertEqual(len(old),1);self.assertEqual((old[0]/'sanro-studio.php').read_text(),'old plugin');self.assertFalse(r['activation_changed']);self.assertFalse(r['order_writes'])
 def test_failed_second_rename_restores_exact_old_tree(self):
  def fail(src,dest):
   if pathlib.Path(src).name=='sanro-studio' and pathlib.Path(src).parent.name.startswith('.sanro-studio-incoming-'):raise OSError('synthetic failure')
   return installer.os.rename(src,dest)
  with self.assertRaises(OSError):installer.replace_existing(self.z,self.m,self.target,self.back,self.before,rename=fail)
  self.assertEqual(installer.tree_digest(self.target),self.before)
 def test_changed_target_refused(self):
  (self.target/'sanro-studio.php').write_text('another worker')
  with self.assertRaisesRegex(ValueError,'existing_plugin_changed'):installer.replace_existing(self.z,self.m,self.target,self.back,self.before)
  self.assertEqual((self.target/'sanro-studio.php').read_text(),'another worker')
 def test_tampered_candidate_does_not_move_target(self):
  self.m['files'][0]['sha256']='0'*64
  with self.assertRaisesRegex(ValueError,'candidate_changed'):installer.replace_existing(self.z,self.m,self.target,self.back,self.before)
  self.assertEqual(installer.tree_digest(self.target),self.before)
 def test_symlink_refused(self):
  (self.target/'link').symlink_to(self.root)
  with self.assertRaisesRegex(ValueError,'unsupported_target_entry'):installer.tree_digest(self.target)
 def test_lock_contention_refused(self):
  with installer.install_lock(self.target.parent):
   with self.assertRaisesRegex(ValueError,'another_installer_running'):installer.replace_existing(self.z,self.m,self.target,self.back,self.before)
 def test_approval_cannot_use_wrong_source_target_or_flags(self):
  a={'approved':True,'action':'replace_existing_staging_plugin','target_url':installer.TARGET,'source_commit':self.m['source_commit'],'plugin_digest':self.m['plugin_digest'],'archive_sha256':self.m['archive_sha256'],'existing_plugin_digest':self.before,'php_fpm_verified':True,'generator':'OFF','drive':'OFF','order_database_changes':False}
  installer.approve(a,self.m,self.before)
  for key,bad in [('approved',False),('target_url','https://kaoscetak.id'),('source_commit','d'*40),('existing_plugin_digest','d'*64),('drive','ON'),('order_database_changes',0)]:
   with self.subTest(key=key),self.assertRaisesRegex(ValueError,'exact_owner_approval_required'):installer.approve(dict(a,**{key:bad}),self.m,self.before)
 def test_failed_installed_verification_restores_old_tree(self):
  def corrupt(src,dest):
   installer.os.rename(src,dest)
   if pathlib.Path(src).parent.name.startswith('.sanro-studio-incoming-'):(pathlib.Path(dest)/'sanro-studio.php').write_text('synthetic corruption')
  with self.assertRaisesRegex(ValueError,'installed_member_mismatch'):installer.replace_existing(self.z,self.m,self.target,self.back,self.before,rename=corrupt)
  self.assertEqual(installer.tree_digest(self.target),self.before)
 def test_probe_refuses_production_writer_and_missing_activation(self):
  good={'url':installer.TARGET+'/','plugin_dir':'/not-used','active':['sanro-studio/sanro-studio.php','woocommerce/woocommerce.php'],'flags':dict.fromkeys(installer.FLAGS,False),'multisite':False}
  for bad in [dict(good,url='https://kaoscetak.id'),dict(good,flags=dict.fromkeys(installer.FLAGS,True)),dict(good,active=[]),dict(good,multisite=True)]:
   with patch.object(installer.subprocess,'run',return_value=SimpleNamespace(returncode=0,stdout=json.dumps(bad))),self.assertRaises(ValueError):installer.probe('never-executed',self.root)
  with patch.object(installer.subprocess,'run',return_value=SimpleNamespace(returncode=0,stdout=json.dumps(good))):self.assertEqual(installer.probe('never-executed',self.root),good)
if __name__=='__main__':unittest.main()
