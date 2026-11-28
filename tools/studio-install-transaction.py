"""Durable intent journal for existing Studio plugin replacement; no WordPress calls."""
import contextlib,fcntl,hashlib,json,os,pathlib,re,tempfile,zipfile

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

def sync_dir(path):
 fd=os.open(str(path),os.O_RDONLY|os.O_DIRECTORY)
 try:os.fsync(fd)
 finally:os.close(fd)

def journal_path(root):return root/'.sanro-studio-transaction.json'
def write_journal(root,record):
 # Write+fsync+rename+directory fsync: a truncated JSON file is never accepted.
 with tempfile.NamedTemporaryFile(mode='w',prefix='.journal-',dir=root,delete=False) as f:
  temporary=pathlib.Path(f.name);os.chmod(f.name,0o600);json.dump(record,f,sort_keys=True);f.flush();os.fsync(f.fileno())
 try:os.replace(temporary,journal_path(root));sync_dir(root)
 finally:
  if temporary.exists():temporary.unlink()

def read_journal(root):
 path=journal_path(root)
 if not path.exists() and not path.is_symlink():return None
 if path.is_symlink() or not path.is_file() or path.stat().st_size>8192 or path.stat().st_mode&0o077:raise ValueError('unsafe_transaction_journal')
 try:record=json.loads(path.read_text())
 except (ValueError,UnicodeError):raise ValueError('invalid_transaction_journal')
 if not isinstance(record,dict) or record.get('schema')!=1 or record.get('phase') not in ('prepared','old_moved','new_moved','committed','rolled_back'):raise ValueError('invalid_transaction_journal')
 for key,size in [('source_commit',40),('archive_sha256',64),('plugin_digest',64),('before_digest',64),('candidate_digest',64),('transaction',32)]:
  if not isinstance(record.get(key),str) or not re.fullmatch('[a-f0-9]{'+str(size)+'}',record[key]):raise ValueError('invalid_transaction_journal')
 if record.get('backup')!='sanro-studio-previous-'+record['transaction'] or record.get('quarantine')!='sanro-studio-interrupted-'+record['transaction']:raise ValueError('invalid_transaction_journal')
 return record

@contextlib.contextmanager
def install_lock(parent):
 fd=os.open(str(parent/'.sanro-studio-install.lock'),os.O_CREAT|os.O_RDWR|os.O_NOFOLLOW,0o600)
 try:
  try:fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
  except BlockingIOError:raise ValueError('another_installer_running')
  yield
 finally:os.close(fd)

def roots(target,rollback):
 target=pathlib.Path(target).absolute();parent=canonical(target.parent);root=canonical(rollback)
 if target.name!='sanro-studio' or target.is_symlink():raise ValueError('existing_plugin_required')
 target=parent/'sanro-studio'
 if root==target or target in root.parents or root in target.parents:raise ValueError('rollback_overlap')
 if parent.stat().st_dev!=root.stat().st_dev:raise ValueError('rollback_must_share_filesystem')
 if root.stat().st_mode&0o077:raise ValueError('rollback_directory_must_be_private')
 return target,root

def bound(record,target,manifest=None):
 if record['target']!=str(target):raise ValueError('transaction_target_mismatch')
 if manifest is not None and any(record[k]!=manifest[k] for k in ('source_commit','archive_sha256','plugin_digest')):raise ValueError('transaction_source_mismatch')

def restore_locked(target,root,record,checkpoint=lambda event:None):
 bound(record,target);backup=root/record['backup'];quarantine=root/record['quarantine']
 current=tree_digest(target) if target.exists() else None
 if record['phase']=='committed':
  if current!=record['candidate_digest'] or not backup.exists() or tree_digest(backup)!=record['before_digest']:raise ValueError('committed_transaction_changed')
  return {'state':'committed','recovered':False,'previous_retained':True}
 if current==record['before_digest']:
  if backup.exists():raise ValueError('ambiguous_original_directories')
  record['phase']='rolled_back';write_journal(root,record);return {'state':'rolled_back','recovered':True,'previous_retained':True}
 # Never move an unknown directory or restore unverified/missing old bytes.
 if not backup.exists() or tree_digest(backup)!=record['before_digest']:raise ValueError('previous_plugin_unavailable')
 if current is not None:
  if current!=record['candidate_digest'] or quarantine.exists():raise ValueError('interrupted_target_changed')
  os.rename(target,quarantine);sync_dir(target.parent);sync_dir(root);checkpoint('candidate_quarantined')
 os.rename(backup,target);sync_dir(target.parent);sync_dir(root);checkpoint('old_restored')
 if tree_digest(target)!=record['before_digest']:raise ValueError('restored_plugin_mismatch')
 record['phase']='rolled_back';write_journal(root,record)
 return {'state':'rolled_back','recovered':True,'previous_retained':True}

def recover_existing(target,rollback,manifest,checkpoint=lambda event:None):
 target,root=roots(target,rollback)
 with install_lock(target.parent):
  record=read_journal(root)
  if not record:raise ValueError('transaction_journal_missing')
  bound(record,target,manifest);return restore_locked(target,root,record,checkpoint)

def replace_existing(archive,manifest,target,rollback,before,rename=os.rename,checkpoint=lambda event:None):
 target,root=roots(target,rollback)
 with install_lock(target.parent):
  previous=read_journal(root)
  if previous:
   bound(previous,target)
   if previous['phase'] not in ('committed','rolled_back'):raise ValueError('interrupted_transaction_requires_recovery')
   # Refuse a stale terminal journal whose actual target/retained original changed.
   restore_locked(target,root,previous)
  if not (target/'sanro-studio.php').is_file() or tree_digest(target)!=before:raise ValueError('existing_plugin_changed')
  with tempfile.TemporaryDirectory(prefix='.sanro-studio-incoming-',dir=target.parent) as temp:
   incoming=pathlib.Path(temp)/'sanro-studio';incoming.mkdir()
   with zipfile.ZipFile(archive) as z:
    for item in manifest['files']+[manifest['build_marker']]:
     name=pathlib.PurePosixPath(item['path'])
     if name.is_absolute() or '..' in name.parts or '\\' in item['path']:raise ValueError('unsafe_member')
     data=z.read('sanro-studio/'+item['path'])
     if len(data)!=item['bytes'] or hashlib.sha256(data).hexdigest()!=item['sha256']:raise ValueError('candidate_changed')
     file=incoming/item['path'];file.parent.mkdir(parents=True,exist_ok=True)
     with file.open('wb') as f:f.write(data);f.flush();os.fsync(f.fileno())
     file.chmod(0o644)
   for directory in sorted([p for p in incoming.rglob('*') if p.is_dir()],key=lambda p:len(p.parts),reverse=True):sync_dir(directory)
   sync_dir(incoming);sync_dir(incoming.parent)
   if tree_digest(target)!=before:raise ValueError('existing_plugin_changed')
   ident=os.urandom(16).hex();record={'schema':1,'transaction':ident,'phase':'prepared','target':str(target),'before_digest':before,'candidate_digest':tree_digest(incoming),'backup':'sanro-studio-previous-'+ident,'quarantine':'sanro-studio-interrupted-'+ident,**{k:manifest[k] for k in ('source_commit','archive_sha256','plugin_digest')}}
   if previous:
    # Preserve terminal transaction evidence along with retained old folders.
    archive_record=root/('transaction-'+previous['transaction']+'.json')
    if not archive_record.exists():
     with archive_record.open('x') as f:os.chmod(archive_record,0o600);json.dump(previous,f);f.flush();os.fsync(f.fileno())
   write_journal(root,record);checkpoint('prepared')
   try:
    rename(target,root/record['backup']);sync_dir(target.parent);sync_dir(root);checkpoint('old_renamed')
    record['phase']='old_moved';write_journal(root,record)
    rename(incoming,target);sync_dir(target.parent);sync_dir(incoming.parent);checkpoint('new_renamed')
    record['phase']='new_moved';write_journal(root,record)
    if tree_digest(target)!=record['candidate_digest']:raise ValueError('installed_member_mismatch')
    record['phase']='committed';write_journal(root,record);checkpoint('committed')
   except BaseException:
    # Unknown altered candidate bytes are retained and never overwrite the old plugin.
    if target.exists() and tree_digest(target)!=before and tree_digest(target)!=record['candidate_digest']:
     quarantine=root/record['quarantine']
     if quarantine.exists():raise ValueError('ambiguous_failed_candidate')
     os.rename(target,quarantine);sync_dir(target.parent);sync_dir(root)
    restore_locked(target,root,record)
    raise
   return {'state':'files_replaced','source_commit':manifest['source_commit'],'rollback_retained':True,'runtime_e2e':'NOT_TESTED','flags_changed':False,'activation_changed':False,'order_writes':False}
