#!/usr/bin/env python3
"""Read-only candidate installer preflight. No remote access, apply, activation or DB writes."""
import argparse,hashlib,json,pathlib,re,subprocess,tempfile,zipfile
from urllib.parse import urlparse

def validate(archive,manifest,target,php=None):
    u=urlparse(target)
    if u.scheme!='https' or u.netloc!='saddlebrown-peafowl-616656.hostingersite.com' or u.path not in ('','/') or u.query or u.fragment:raise ValueError('staging_target_only')
    m=json.loads(pathlib.Path(manifest).read_text())
    if m.get('schema')!=1 or not re.fullmatch('[a-f0-9]{40}',m.get('source_commit','')):raise ValueError('invalid_source')
    if m.get('generator_default')!='OFF' or m.get('drive_default')!='OFF' or m.get('deployment_authorized') is not False:raise ValueError('unsafe_manifest_flags')
    raw=pathlib.Path(archive).read_bytes()
    if len(raw)>50_000_000 or hashlib.sha256(raw).hexdigest()!=m.get('archive_sha256'):raise ValueError('archive_digest_mismatch')
    files=m['files'];digest=hashlib.sha256(json.dumps(files,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if digest!=m.get('plugin_digest'):raise ValueError('plugin_digest_mismatch')
    expected={}
    for item in [*files,m['build_marker']]:
        name=item['path'];path=pathlib.PurePosixPath(name)
        if not name or path.is_absolute() or '..' in path.parts or '\\' in name or 'sanro-studio/'+name in expected:raise ValueError('unsafe_manifest_member')
        expected['sanro-studio/'+name]=item
    with tempfile.TemporaryDirectory(prefix='sanro-preflight-') as directory,zipfile.ZipFile(archive) as z:
        names=z.namelist()
        if len(names)!=len(set(names)) or set(names)!=set(expected):raise ValueError('archive_members_mismatch')
        if sum(i.file_size for i in z.infolist())>100_000_000:raise ValueError('archive_too_large')
        for info in z.infolist():
            if info.external_attr>>16!=0o100644 or info.flag_bits&1:raise ValueError('unsupported_member_mode')
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
