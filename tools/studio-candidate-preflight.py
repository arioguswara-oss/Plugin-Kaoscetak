#!/usr/bin/env python3
"""Read-only candidate installer preflight. No remote access, apply, activation or DB writes."""
import argparse,hashlib,io,json,os,pathlib,re,stat,subprocess,tempfile,zipfile
from urllib.parse import urlparse

def bounded_read(path,limit):
    with os.fdopen(os.open(str(path),os.O_RDONLY|os.O_NOFOLLOW),'rb') as handle:
        info=os.fstat(handle.fileno())
        if not stat.S_ISREG(info.st_mode) or info.st_size>limit:raise ValueError('input_size_or_type_refused')
        data=handle.read(limit+1)
        if len(data)>limit:raise ValueError('input_size_or_type_refused')
        return data

def load_manifest(path):
    m=json.loads(bounded_read(path,2_000_000))
    if not isinstance(m,dict) or type(m.get('schema')) is not int or m['schema']!=1:raise ValueError('invalid_source')
    for key,length in [('source_commit',40),('plugin_digest',64),('archive_sha256',64)]:
        if not isinstance(m.get(key),str) or not re.fullmatch('[a-f0-9]{'+str(length)+'}',m[key]):raise ValueError('invalid_source')
    if m.get('generator_default')!='OFF' or m.get('drive_default')!='OFF' or m.get('deployment_authorized') is not False:raise ValueError('unsafe_manifest_flags')
    if not isinstance(m.get('files'),list) or not m['files'] or len(m['files'])>512 or not isinstance(m.get('build_marker'),dict):raise ValueError('invalid_manifest_members')
    return m

def validate(archive,manifest,target,php=None):
    u=urlparse(target)
    if u.scheme!='https' or u.netloc!='saddlebrown-peafowl-616656.hostingersite.com' or u.path not in ('','/') or u.query or u.fragment:raise ValueError('staging_target_only')
    m=load_manifest(manifest)
    raw=bounded_read(archive,50_000_000)
    if hashlib.sha256(raw).hexdigest()!=m['archive_sha256']:raise ValueError('archive_digest_mismatch')
    files=m['files'];digest=hashlib.sha256(json.dumps(files,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if digest!=m.get('plugin_digest'):raise ValueError('plugin_digest_mismatch')
    expected={}
    for item in [*files,m['build_marker']]:
        if not isinstance(item,dict) or not isinstance(item.get('path'),str):raise ValueError('unsafe_manifest_member')
        name=item['path'];path=pathlib.PurePosixPath(name)
        if not name or name!=path.as_posix() or path.is_absolute() or '..' in path.parts or any(p.startswith('.') for p in path.parts) or '\\' in name or any(ord(c)<32 for c in name) or 'sanro-studio/'+name in expected:raise ValueError('unsafe_manifest_member')
        if type(item.get('bytes')) is not int or not 0<=item['bytes']<=33_554_432 or not isinstance(item.get('sha256'),str) or not re.fullmatch('[a-f0-9]{64}',item['sha256']):raise ValueError('invalid_member_metadata')
        expected['sanro-studio/'+name]=item
    with tempfile.TemporaryDirectory(prefix='sanro-preflight-') as directory,zipfile.ZipFile(io.BytesIO(raw)) as z:
        names=z.namelist()
        if len(names)!=len(set(names)) or set(names)!=set(expected):raise ValueError('archive_members_mismatch')
        if sum(i.file_size for i in z.infolist())>100_000_000:raise ValueError('archive_too_large')
        for info in z.infolist():
            if info.external_attr>>16!=0o100644 or info.flag_bits&1 or info.compress_type not in (zipfile.ZIP_STORED,zipfile.ZIP_DEFLATED):raise ValueError('unsupported_member_mode')
            data=z.read(info);item=expected[info.filename]
            if len(data)!=item['bytes'] or hashlib.sha256(data).hexdigest()!=item['sha256']:raise ValueError('member_digest_mismatch')
            out=pathlib.Path(directory)/info.filename;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(data)
        identity=json.loads((pathlib.Path(directory)/'sanro-studio/SANRO-STUDIO-BUILD.json').read_text())
        if identity!={k:m[k] for k in ('schema','source_commit','plugin_digest')}:raise ValueError('build_identity_mismatch')
        lint='NOT_TESTED';version=None
        if php:
            version=subprocess.check_output([php,'-r','echo PHP_VERSION;'],text=True).strip()
            if tuple(map(int,version.split('.')[:2]))<(7,4):raise ValueError('php_too_old')
            for name in names:
                if name.endswith('.php'):
                    result=subprocess.run([php,'-l',str(pathlib.Path(directory)/name)],capture_output=True,text=True)
                    if result.returncode:raise ValueError('php_lint_failed:'+name)
            lint='PASS'
    return {'source_commit':m['source_commit'],'archive_sha256':m['archive_sha256'],'target':target,'mode':'PLAN_ONLY','php_cli':version,'lint':lint,
        'remote_php_fpm':'NOT_TESTED','remote_flags':'NOT_TESTED','runtime_e2e':'NOT_TESTED','deployment_authorized':False,
        'required_apply_contract':['Owner approval bound to source/archive SHA and staging URL','read PHP-FPM/feature flags and verify same build identity','preserve existing activation, private paths and product metadata','refuse generator/Drive enabled; do not overwrite flags','atomic existing plugin replacement, rollback on failure; no reinstall/seed/order writes']}
if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--zip',required=True);parser.add_argument('--manifest',required=True);parser.add_argument('--target-url',required=True);parser.add_argument('--php');args=parser.parse_args()
    try:print(json.dumps(validate(args.zip,args.manifest,args.target_url,args.php),indent=2))
    except (ValueError,KeyError,TypeError,OSError,zipfile.BadZipFile) as error:raise SystemExit('REFUSED: '+str(error))
