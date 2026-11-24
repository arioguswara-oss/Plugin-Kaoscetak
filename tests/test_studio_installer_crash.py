"""Actual process exits at rename boundaries; temporary files only, no WP/hosting."""
import sys
sys.dont_write_bytecode=True
import hashlib,importlib.util,json,pathlib,subprocess,tempfile,unittest,zipfile
TOOL=pathlib.Path(__file__).resolve().parents[1]/'tools/studio-staging-install.py'
spec=importlib.util.spec_from_file_location('installer',TOOL);installer=importlib.util.module_from_spec(spec);spec.loader.exec_module(installer)
CHILD="""import sys,os,json,pathlib,importlib.util
sys.dont_write_bytecode=True
spec=importlib.util.spec_from_file_location('installer',sys.argv[1]);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
r=pathlib.Path(sys.argv[2]);manifest=json.loads((r/'manifest.json').read_text());before=(r/'before').read_text()
def stop(event):
 if event==sys.argv[4]:os._exit(91)
if sys.argv[3]=='install':m.replace_existing(r/'candidate.zip',manifest,r/'plugins/sanro-studio',r/'rollback',before,checkpoint=stop)
else:m.recover_existing(r/'plugins/sanro-studio',r/'rollback',manifest,checkpoint=stop)
"""
class CrashTests(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.root=pathlib.Path(self.temp.name);self.target=self.root/'plugins/sanro-studio';self.target.mkdir(parents=True);(self.target/'sanro-studio.php').write_text('original plugin');self.rollback=self.root/'rollback';self.rollback.mkdir(mode=0o700);self.before=installer.tree_digest(self.target);(self.root/'before').write_text(self.before)
  self.m={'schema':1,'generator_default':'OFF','drive_default':'OFF','deployment_authorized':False,'source_commit':'a'*40,'plugin_digest':'b'*64,'archive_sha256':'c'*64,'files':[],'build_marker':{}}
  with zipfile.ZipFile(self.root/'candidate.zip','w') as z:
   for key,name,data in [('files','sanro-studio.php',b'new plugin'),('build_marker','SANRO-STUDIO-BUILD.json',b'{}')]:
    item={'path':name,'bytes':len(data),'sha256':hashlib.sha256(data).hexdigest()}
    if key=='files':self.m[key].append(item)
    else:self.m[key]=item
    z.writestr('sanro-studio/'+name,data)
  (self.root/'manifest.json').write_text(json.dumps(self.m))
 def tearDown(self):self.temp.cleanup()
 def kill(self,mode,event):
  p=subprocess.run([sys.executable,'-c',CHILD,str(TOOL),str(self.root),mode,event],capture_output=True,text=True);self.assertEqual(p.returncode,91,p.stderr)
 def recover(self):return installer.recover_existing(self.target,self.rollback,self.m)
 def test_exit_before_first_rename(self):
  self.kill('install','prepared');self.recover();self.assertEqual(installer.tree_digest(self.target),self.before);self.assertEqual(self.recover()['state'],'rolled_back')
 def test_exit_between_renames(self):
  self.kill('install','old_renamed');self.assertFalse(self.target.exists());self.recover();self.assertEqual(installer.tree_digest(self.target),self.before);self.recover();self.assertEqual(installer.tree_digest(self.target),self.before)
 def test_exit_after_second_rename(self):
  self.kill('install','new_renamed');self.recover();self.assertEqual(installer.tree_digest(self.target),self.before);self.assertTrue(any(p.name.startswith('sanro-studio-interrupted-') for p in self.rollback.iterdir()))
 def test_exit_during_recovery_quarantine(self):
  self.kill('install','new_renamed');self.kill('recover','candidate_quarantined');self.recover();self.assertEqual(installer.tree_digest(self.target),self.before)
 def test_exit_after_original_restore_before_journal(self):
  self.kill('install','old_renamed');self.kill('recover','old_restored');self.recover();self.assertEqual(installer.tree_digest(self.target),self.before)
 def test_committed_recovery_is_noop_and_keeps_old_folder(self):
  self.kill('install','committed');current=installer.tree_digest(self.target);self.assertEqual(self.recover()['state'],'committed');self.assertEqual(installer.tree_digest(self.target),current);self.assertEqual(self.recover()['state'],'committed');self.assertTrue(any(p.is_dir() and installer.tree_digest(p)==self.before for p in self.rollback.iterdir()))
 def test_foreign_change_refused_without_losing_original(self):
  self.kill('install','new_renamed');(self.target/'sanro-studio.php').write_text('another worker');
  with self.assertRaisesRegex(ValueError,'interrupted_target_changed'):self.recover()
  self.assertEqual((self.target/'sanro-studio.php').read_text(),'another worker');self.assertTrue(any(p.is_dir() and installer.tree_digest(p)==self.before for p in self.rollback.iterdir()))
 def test_wrong_source_or_corrupt_journal_never_mutates(self):
  self.kill('install','old_renamed');bad=dict(self.m,source_commit='d'*40)
  with self.assertRaisesRegex(ValueError,'transaction_source_mismatch'):installer.recover_existing(self.target,self.rollback,bad)
  journal=installer.transaction.journal_path(self.rollback);journal.write_text('{broken')
  with self.assertRaisesRegex(ValueError,'invalid_transaction_journal'):self.recover()
  self.assertFalse(self.target.exists());self.assertTrue(any(p.is_dir() and installer.tree_digest(p)==self.before for p in self.rollback.iterdir()))
 def test_next_apply_refuses_pending_transaction(self):
  self.kill('install','old_renamed')
  with self.assertRaisesRegex(ValueError,'interrupted_transaction_requires_recovery'):installer.replace_existing(self.root/'candidate.zip',self.m,self.target,self.rollback,self.before)
 def test_cli_recover_missing_target_with_fake_wp_and_bound_approval(self):
  wp_root=self.root/'public_html';target=wp_root/'wp-content/plugins/sanro-studio';target.parent.mkdir(parents=True);self.target.rename(target)
  kill="import sys,os,json,pathlib,importlib.util; sys.dont_write_bytecode=True; s=importlib.util.spec_from_file_location('i',sys.argv[1]); m=importlib.util.module_from_spec(s); s.loader.exec_module(m); r=pathlib.Path(sys.argv[2]); m.replace_existing(r/'candidate.zip',json.loads((r/'manifest.json').read_text()),r/'public_html/wp-content/plugins/sanro-studio',r/'rollback',(r/'before').read_text(),checkpoint=lambda e:os._exit(91) if e=='old_renamed' else None)"
  result=subprocess.run([sys.executable,'-c',kill,str(TOOL),str(self.root)],capture_output=True,text=True);self.assertEqual(result.returncode,91,result.stderr)
  state={'url':installer.TARGET,'plugin_dir':str(target.parent),'active':['sanro-studio/sanro-studio.php','woocommerce/woocommerce.php'],'flags':dict.fromkeys(installer.FLAGS,False),'multisite':False}
  fake_wp=self.root/'fake-wp';fake_wp.write_text('#!'+sys.executable+'\nprint('+repr(json.dumps(state))+')\n');fake_wp.chmod(0o700)
  approval={'approved':True,'action':'replace_existing_staging_plugin','target_url':installer.TARGET,'source_commit':self.m['source_commit'],'plugin_digest':self.m['plugin_digest'],'archive_sha256':self.m['archive_sha256'],'existing_plugin_digest':self.before,'web_runtime_verified':True,'web_php_sapi':'litespeed','generator':'OFF','drive':'OFF','order_database_changes':False}
  a=self.root/'synthetic-approval.json';a.write_text(json.dumps(approval))
  cmd=[sys.executable,str(TOOL),'--recover','--zip',str(self.root/'candidate.zip'),'--manifest',str(self.root/'manifest.json'),'--php',sys.executable,'--wp',str(fake_wp),'--wp-root',str(wp_root),'--document-root',str(wp_root),'--rollback-root',str(self.rollback),'--approval',str(a)]
  for _ in range(2):
   result=subprocess.run(cmd,capture_output=True,text=True);self.assertEqual(result.returncode,0,result.stderr);self.assertEqual(json.loads(result.stdout)['state'],'rolled_back');self.assertEqual(installer.tree_digest(target),self.before)
if __name__=='__main__':unittest.main()
