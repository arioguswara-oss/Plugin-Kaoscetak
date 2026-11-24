#!/usr/bin/env python3
"""Build a reproducible, default-OFF Studio staging candidate from a clean Git HEAD. Never deploy."""
import argparse, hashlib, json, pathlib, re, subprocess, zipfile
parser=argparse.ArgumentParser();parser.add_argument('--out',required=True);parser.add_argument('--release',action='store_true');args=parser.parse_args()
repo=pathlib.Path(__file__).resolve().parents[1]
def git(*parts):return subprocess.check_output(['git',*parts],cwd=repo,text=True).strip()
if git('status','--porcelain'):raise SystemExit('Refused: candidate requires a clean committed tree')
commit=git('rev-parse','HEAD');root='plugins/sanro-studio/'
entries=[];contents={}
for line in git('ls-tree','-r','HEAD','--',root).splitlines():
    meta,path=line.split('\t',1);mode,kind,blob=meta.split();relative=path[len(root):]
    if relative.startswith('tests/'):continue
    if mode!='100644' or kind!='blob' or pathlib.PurePosixPath(relative).is_absolute() or '..' in pathlib.PurePosixPath(relative).parts:raise SystemExit('Refused unsupported source entry')
    if any(part.startswith('.') for part in pathlib.PurePosixPath(relative).parts) or relative.endswith(('.log','.zip')):raise SystemExit('Refused non-source artifact')
    data=(repo/path).read_bytes()
    if hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()!=blob:raise SystemExit('Source changed during build')
    entries.append({'path':relative,'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data)});contents[relative]=data
bootstrap=contents['sanro-studio.php'].decode()
if re.search(r"define\s*\(\s*['\"]SANRO_STUDIO_ENABLE_",bootstrap):raise SystemExit('Refused: bootstrap defines feature enable flags')
version=re.search(r'\* Version: ([^\n]+)',bootstrap).group(1).strip()
if args.release and not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+',version):raise SystemExit('Release requires a stable version')
archive_name='sanro-studio-'+version+'.zip' if args.release else 'sanro-studio-staging-candidate.zip'
manifest={'version':version,'channel':'final' if args.release else 'candidate','archive_name':archive_name,'schema':1,'repository':'arioguswara-oss/Plugin-Kaoscetak','source_commit':commit,'files':entries,
          'plugin_digest':hashlib.sha256(json.dumps(entries,sort_keys=True,separators=(',',':')).encode()).hexdigest(),
          'generator_default':'OFF','drive_default':'OFF','runtime_e2e':'SEE_SEPARATE_SCOPE_ACCEPTANCE_AND_RUNTIME_EVIDENCE' if args.release else 'NOT_TESTED','deployment_authorized':False}
out=pathlib.Path(args.out).resolve();out.mkdir(parents=True,exist_ok=True)
identity={k:manifest[k] for k in ('schema','source_commit','plugin_digest')}
identity_data=(json.dumps(identity,sort_keys=True,separators=(',',':'))+'\n').encode()
contents['SANRO-STUDIO-BUILD.json']=identity_data
manifest['build_marker']={'path':'SANRO-STUDIO-BUILD.json','sha256':hashlib.sha256(identity_data).hexdigest(),'bytes':len(identity_data)}
archive=out/archive_name
with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    for name,data in sorted(contents.items()):
        info=zipfile.ZipInfo('sanro-studio/'+name,(1980,1,1,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,data)
manifest['archive_sha256']=hashlib.sha256(archive.read_bytes()).hexdigest()
(out/('release-manifest.json' if args.release else 'candidate-manifest.json')).write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'commit':commit,'files':len(entries),'archive':str(archive),'sha256':manifest['archive_sha256'],'manifest':str(out/('release-manifest.json' if args.release else 'candidate-manifest.json'))}))
