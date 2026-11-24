#!/usr/bin/env python3
"""Default plan-only, existing-plugin replacement. Apply requires exact Owner approval; never activates or seeds."""
import argparse,contextlib,fcntl,hashlib,importlib.util,json,os,pathlib,subprocess,sys,tempfile,zipfile
sys.dont_write_bytecode=True
spec=importlib.util.spec_from_file_location('candidate_preflight',pathlib.Path(__file__).with_name('studio-candidate-preflight.py'))
preflight=importlib.util.module_from_spec(spec);spec.loader.exec_module(preflight)
TARGET='https://saddlebrown-peafowl-616656.hostingersite.com'
FLAGS=['STORAGE_GC','STAGING_CHECKOUT_HOOK','DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS','DRIVE_MASTER_EXPORT','DRIVE_AUTO_PROVISION','VERIFIED_DRIVE_ATTACHMENT','DRIVE_PACKAGE_EXPORT','DRIVE_DTF_CANDIDATES']

transaction_spec=importlib.util.spec_from_file_location('studio_install_transaction',pathlib.Path(__file__).with_name('studio-install-transaction.py'))
transaction=importlib.util.module_from_spec(transaction_spec);transaction_spec.loader.exec_module(transaction)
canonical=transaction.canonical;tree_digest=transaction.tree_digest;install_lock=transaction.install_lock
replace_existing=transaction.replace_existing;recover_existing=transaction.recover_existing

def probe(wp,wp_root):
 # Fixed PHP source, no request values interpolated. Skip plugin code; MU flags remain loaded.
 code="""$f=[];foreach(['STORAGE_GC','STAGING_CHECKOUT_HOOK','DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS','DRIVE_MASTER_EXPORT','DRIVE_AUTO_PROVISION','VERIFIED_DRIVE_ATTACHMENT','DRIVE_PACKAGE_EXPORT','DRIVE_DTF_CANDIDATES'] as $k){$n='SANRO_STUDIO_ENABLE_'.$k;$f[$k]=defined($n)?constant($n):false;}echo json_encode(['url'=>home_url('/'),'plugin_dir'=>WP_PLUGIN_DIR,'active'=>get_option('active_plugins',[]),'flags'=>$f,'multisite'=>is_multisite()]);"""
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
  'existing_plugin_digest':before,'web_runtime_verified':True,'web_php_sapi':'litespeed',
  'generator':'OFF','drive':'OFF','order_database_changes':False}
 if not isinstance(approval,dict) or any(approval.get(k)!=v or type(approval.get(k)) is not type(v) for k,v in required.items()):raise ValueError('exact_owner_approval_required')

def main():
 parser=argparse.ArgumentParser(description=__doc__)
 for name in ('zip','manifest','php','wp','wp-root','document-root','rollback-root'):parser.add_argument('--'+name,required=True)
 mode=parser.add_mutually_exclusive_group();mode.add_argument('--apply',action='store_true');mode.add_argument('--recover',action='store_true');parser.add_argument('--approval');args=parser.parse_args()
 manifest=preflight.load_manifest(args.manifest);candidate=None if args.recover else preflight.validate(args.zip,args.manifest,TARGET,args.php)
 wp_root=canonical(args.wp_root);rollback=canonical(args.rollback_root);state=probe(args.wp,wp_root);plugin_parent=canonical(state['plugin_dir']);target=plugin_parent/'sanro-studio'
 # Parent path is derived from the probed WordPress installation, never a caller-supplied target plugin.
 if plugin_parent!=wp_root/'wp-content/plugins':raise ValueError('custom_plugin_layout_not_supported')
 # Hostinger normally serves public_html as ABSPATH; retain rollbacks strictly outside its parent document-root boundary.
 web_root=canonical(args.document_root)
 if web_root!=wp_root:raise ValueError('subdirectory_wordpress_layout_not_supported')
 if rollback==web_root or web_root in rollback.parents:raise ValueError('rollback_must_be_outside_webroot')
 if rollback.stat().st_mode&0o077:raise ValueError('rollback_directory_must_be_private')
 journal=transaction.read_journal(rollback)
 if journal:transaction.bound(journal,target,manifest if args.recover else None)
 pending=journal is not None and journal['phase'] not in ('committed','rolled_back')
 before=journal['before_digest'] if journal and (args.recover or pending) else (tree_digest(target) if target.exists() else None)
 if not args.apply and not args.recover:
  print(json.dumps({'mode':'PLAN_ONLY','source_commit':manifest['source_commit'],'archive_sha256':manifest['archive_sha256'],'existing_plugin_digest':before,'writer_flags':'OFF','php_lint':candidate['lint'],'recovery_required':pending,'apply_authorized':False,'web_php_sapi':'NOT_TESTED_PLAN_ONLY'},indent=2));return
 if not args.approval:raise ValueError('exact_owner_approval_required')
 approve(json.loads(pathlib.Path(args.approval).read_text()),manifest,before)
 # Re-probe after lint and approval validation; do not replace if site/gates/activation changed.
 second=probe(args.wp,wp_root)
 if second!=state:raise ValueError('staging_state_changed')
 if pending and not args.recover:raise ValueError('interrupted_transaction_requires_recovery')
 result=recover_existing(target,rollback,manifest) if args.recover else replace_existing(args.zip,manifest,target,rollback,before)
 print(json.dumps(result,indent=2))
if __name__=='__main__':
 try:main()
 except (ValueError,KeyError,TypeError,OSError,subprocess.SubprocessError,zipfile.BadZipFile):raise SystemExit('REFUSED: candidate, target, approval, lock or replacement validation failed; no activation/seed/order operation was requested')
