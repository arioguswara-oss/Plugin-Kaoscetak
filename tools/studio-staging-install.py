#!/usr/bin/env python3
"""Default plan-only, existing-plugin replacement. Apply requires exact Owner approval; never activates or seeds."""
import argparse,contextlib,fcntl,hashlib,importlib.util,json,os,pathlib,subprocess,sys,tempfile,zipfile
sys.dont_write_bytecode=True
spec=importlib.util.spec_from_file_location('candidate_preflight',pathlib.Path(__file__).with_name('studio-candidate-preflight.py'))
preflight=importlib.util.module_from_spec(spec);spec.loader.exec_module(preflight)
TARGET='https://saddlebrown-peafowl-616656.hostingersite.com'
FLAGS=['STAGING_CHECKOUT_HOOK','DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS','DRIVE_MASTER_EXPORT','DRIVE_AUTO_PROVISION','VERIFIED_DRIVE_ATTACHMENT','DRIVE_PACKAGE_EXPORT','DRIVE_DTF_CANDIDATES']

def canonical(path):
 p=pathlib.Path(path).absolute()
 if any(q.is_symlink() for q in [p,*p.parents]):raise ValueError('symlink_path_refused')
 return p.resolve(strict=True)

def tree_digest(directory):
 root=canonical(directory);items=[]
 if not root.is_dir():raise ValueError('plugin_directory_required')
 for p in sorted(root.rglob('*')):
  if p.is_symlink() or not (p.is_dir() or p.is_file()):raise ValueError('unsupported_target_entry')
  if p.is_file():items.append([p.relative_to(root).as_posix(),hashlib.sha256(p.read_bytes()).hexdigest()])
 return hashlib.sha256(json.dumps(items,separators=(',',':')).encode()).hexdigest()

def probe(wp,wp_root):
 # Fixed PHP source, no request values interpolated. Skip plugin code; MU flags remain loaded.
 code="""$f=[];foreach(['STAGING_CHECKOUT_HOOK','DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS','DRIVE_MASTER_EXPORT','DRIVE_AUTO_PROVISION','VERIFIED_DRIVE_ATTACHMENT','DRIVE_PACKAGE_EXPORT','DRIVE_DTF_CANDIDATES'] as $k){$n='SANRO_STUDIO_ENABLE_'.$k;$f[$k]=defined($n)?constant($n):false;}echo json_encode(['url'=>home_url('/'),'plugin_dir'=>WP_PLUGIN_DIR,'active'=>get_option('active_plugins',[]),'flags'=>$f,'multisite'=>is_multisite()]);"""
 result=subprocess.run([wp,'--path='+str(wp_root),'--skip-plugins','--skip-themes','eval',code],capture_output=True,text=True,timeout=60)
 if result.returncode:raise ValueError('wp_probe_failed') # Never echo stderr, paths or credential-bearing plugin output.
 try:data=json.loads(result.stdout)
 except (ValueError,TypeError):raise ValueError('wp_probe_invalid')
 if not isinstance(data,dict) or data.get('url','').rstrip('/')!=TARGET or data.get('multisite') is not False:raise ValueError('staging_identity_mismatch')
 if not isinstance(data.get('flags'),dict) or any(data['flags'].get(k) is not False for k in FLAGS):raise ValueError('writer_flag_not_off')
 active=data.get('active',[])
 if not isinstance(active,list) or any(p not in active for p in ('sanro-studio/sanro-studio.php','woocommerce/woocommerce.php')):raise ValueError('existing_active_plugins_required')
 return data

def approve(approval,manifest,before):
 required={'approved':True,'action':'replace_existing_staging_plugin','target_url':TARGET,
  'source_commit':manifest['source_commit'],'plugin_digest':manifest['plugin_digest'],'archive_sha256':manifest['archive_sha256'],
  'existing_plugin_digest':before,'php_fpm_verified':True,'generator':'OFF','drive':'OFF','order_database_changes':False}
 if not isinstance(approval,dict) or any(approval.get(k)!=v or type(approval.get(k)) is not type(v) for k,v in required.items()):raise ValueError('exact_owner_approval_required')

@contextlib.contextmanager
def install_lock(parent):
 path=parent/'.sanro-studio-install.lock'
 fd=os.open(str(path),os.O_CREAT|os.O_RDWR|os.O_NOFOLLOW,0o600)
 try:
  try:fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
  except BlockingIOError:raise ValueError('another_installer_running')
  yield
 finally:os.close(fd) # Do not unlink the lock inode: another waiting process may hold it.

def replace_existing(archive,manifest,target,rollback_root,before,rename=os.rename):
 """Filesystem transaction only; caller must validate candidate, live identity and approval first."""
 target=canonical(target);rollback_root=canonical(rollback_root)
 if target.name!='sanro-studio' or not (target/'sanro-studio.php').is_file():raise ValueError('existing_plugin_required')
 if rollback_root==target or target in rollback_root.parents or rollback_root in target.parents:raise ValueError('rollback_overlap')
 if target.stat().st_dev!=rollback_root.stat().st_dev:raise ValueError('rollback_must_share_filesystem')
 with install_lock(target.parent):
  if tree_digest(target)!=before:raise ValueError('existing_plugin_changed')
  # Candidate extraction remains unactivated; all members were validated by preflight.
  with tempfile.TemporaryDirectory(prefix='.sanro-studio-incoming-',dir=target.parent) as temporary:
   incoming=pathlib.Path(temporary)/'sanro-studio';incoming.mkdir()
   with zipfile.ZipFile(archive) as z:
    for item in manifest['files']+[manifest['build_marker']]:
     relative=pathlib.PurePosixPath(item['path'])
     if relative.is_absolute() or '..' in relative.parts or '\\' in item['path']:raise ValueError('unsafe_member')
     data=z.read('sanro-studio/'+item['path'])
     if len(data)!=item['bytes'] or hashlib.sha256(data).hexdigest()!=item['sha256']:raise ValueError('candidate_changed')
     p=incoming/item['path'];p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data);p.chmod(0o644)
   # Never touch private masters, uploads, MU config, activation options or product/order metadata.
   backup=rollback_root/('sanro-studio-'+manifest['source_commit'][:12]+'-'+os.urandom(8).hex())
   if tree_digest(target)!=before:raise ValueError('existing_plugin_changed')
   rename(target,backup)
   try:
    rename(incoming,target)
    for item in manifest['files']+[manifest['build_marker']]:
     if hashlib.sha256((target/item['path']).read_bytes()).hexdigest()!=item['sha256']:raise ValueError('installed_member_mismatch')
   except BaseException:
    if target.exists():
     failed=rollback_root/('failed-candidate-'+os.urandom(8).hex());os.rename(target,failed)
    os.rename(backup,target)
    raise
   return {'state':'files_replaced','source_commit':manifest['source_commit'],'rollback_retained':True,'runtime_e2e':'NOT_TESTED','flags_changed':False,'activation_changed':False,'order_writes':False}

def main():
 parser=argparse.ArgumentParser(description=__doc__)
 for name in ('zip','manifest','php','wp','wp-root','document-root','rollback-root'):parser.add_argument('--'+name,required=True)
 parser.add_argument('--apply',action='store_true');parser.add_argument('--approval');args=parser.parse_args()
 manifest=json.loads(pathlib.Path(args.manifest).read_text());candidate=preflight.validate(args.zip,args.manifest,TARGET,args.php)
 wp_root=canonical(args.wp_root);rollback=canonical(args.rollback_root);state=probe(args.wp,wp_root);plugin_parent=canonical(state['plugin_dir']);target=canonical(plugin_parent/'sanro-studio')
 # Parent path is derived from the probed WordPress installation, never a caller-supplied target plugin.
 if plugin_parent!=wp_root/'wp-content/plugins':raise ValueError('custom_plugin_layout_not_supported')
 # Hostinger normally serves public_html as ABSPATH; retain rollbacks strictly outside its parent document-root boundary.
 web_root=canonical(args.document_root)
 if web_root!=wp_root:raise ValueError('subdirectory_wordpress_layout_not_supported')
 if rollback==web_root or web_root in rollback.parents:raise ValueError('rollback_must_be_outside_webroot')
 if rollback.stat().st_mode&0o077:raise ValueError('rollback_directory_must_be_private')
 before=tree_digest(target)
 if not args.apply:
  print(json.dumps({'mode':'PLAN_ONLY','source_commit':manifest['source_commit'],'archive_sha256':manifest['archive_sha256'],'existing_plugin_digest':before,'writer_flags':'OFF','php_lint':candidate['lint'],'apply_authorized':False,'php_fpm':'NOT_TESTED'},indent=2));return
 if not args.approval:raise ValueError('exact_owner_approval_required')
 approve(json.loads(pathlib.Path(args.approval).read_text()),manifest,before)
 # Re-probe after lint and approval validation; do not replace if site/gates/activation changed.
 second=probe(args.wp,wp_root)
 if second!=state:raise ValueError('staging_state_changed')
 print(json.dumps(replace_existing(args.zip,manifest,target,rollback,before),indent=2))
if __name__=='__main__':
 try:main()
 except (ValueError,KeyError,TypeError,OSError,subprocess.SubprocessError,zipfile.BadZipFile):raise SystemExit('REFUSED: candidate, target, approval, lock or replacement validation failed; no activation/seed/order operation was requested')
