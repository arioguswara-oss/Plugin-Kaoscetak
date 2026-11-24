#!/usr/bin/env python3
"""Bundle a pinned candidate with the exact-staging PHP installer, never deploy."""
import argparse,hashlib,io,json,pathlib,re,subprocess,zipfile
APPROVED_V1_MANIFEST_SHA256='556ecd490dcad05a67c55b34e02d04bba6b3e1e25a517f073196f94649b242cd'
p=argparse.ArgumentParser();p.add_argument('--candidate',required=True);p.add_argument('--out',required=True);p.add_argument('--existing-manifest',required=True);p.add_argument('--existing-archive',required=True);p.add_argument('--verified-existing-tree-digest',required=True);a=p.parse_args()
repo=pathlib.Path(__file__).resolve().parents[1];candidate=pathlib.Path(a.candidate);out=pathlib.Path(a.out);out.mkdir(parents=True,exist_ok=True)
installer_source_commit=subprocess.check_output(['git','-C',str(repo),'rev-parse','HEAD'],text=True).strip()
manifest=(candidate/('release-manifest.json' if (candidate/'release-manifest.json').exists() else 'candidate-manifest.json')).read_bytes();m=json.loads(manifest);archive=(candidate/m.get('archive_name','sanro-studio-staging-candidate.zip')).read_bytes()
if hashlib.sha256(archive).hexdigest()!=m['archive_sha256']:raise SystemExit('candidate checksum mismatch')
if not re.fullmatch('[a-f0-9]{64}',a.verified_existing_tree_digest):raise SystemExit('verified existing digest format invalid')
old_manifest_path=pathlib.Path(a.existing_manifest);old_archive_path=pathlib.Path(a.existing_archive);old_manifest=old_manifest_path.read_bytes();old=json.loads(old_manifest);old_archive=old_archive_path.read_bytes()
if old.get('version')!='1.0.0' or old.get('channel')!='final' or old.get('deployment_authorized') is not False:raise SystemExit('existing release is not the approved final 1.0.0 manifest')
if hashlib.sha256(old_manifest).hexdigest()!=APPROVED_V1_MANIFEST_SHA256:raise SystemExit('existing release manifest is not the previously approved Final Release manifest')
if hashlib.sha256(old_archive).hexdigest()!=old.get('archive_sha256'):raise SystemExit('existing release archive checksum mismatch')
if hashlib.sha256(json.dumps(old.get('files',[]),sort_keys=True,separators=(',',':')).encode()).hexdigest()!=old.get('plugin_digest'):raise SystemExit('existing release manifest plugin digest mismatch')
old_files=[*old.get('files',[]),old.get('build_marker',{})]
rows=[]
with zipfile.ZipFile(io.BytesIO(old_archive)) as z:
 if set(z.namelist())!={'sanro-studio/'+x['path'] for x in old_files}:raise SystemExit('existing release archive inventory mismatch')
 for item in sorted(old_files,key=lambda x:x['path']):
  data=z.read('sanro-studio/'+item['path']);digest=hashlib.sha256(data).hexdigest()
  if len(data)!=item['bytes'] or digest!=item['sha256']:raise SystemExit('existing release member checksum mismatch: '+item['path'])
  rows.append([item['path'],digest])
 marker=json.loads(z.read('sanro-studio/'+old['build_marker']['path']))
 if marker!={'schema':old['schema'],'source_commit':old['source_commit'],'plugin_digest':old['plugin_digest']}:raise SystemExit('existing release build marker mismatch')
derived=hashlib.sha256(json.dumps(rows,separators=(',',':')).encode()).hexdigest()
if derived!=a.verified_existing_tree_digest:raise SystemExit('fresh staging tree digest does not match approved Final Release 1.0.0 manifest')
binding='<?php\n'+''.join("const SSWR_%s='%s';\n"%(key,value) for key,value in [('SOURCE',m['source_commit']),('ARCHIVE',m['archive_sha256']),('MANIFEST',hashlib.sha256(manifest).hexdigest())])
source=(repo/'tools/staging-web-release/sanro-studio-staging-release.php').read_text()
placeholder="const SSWR_BEFORE='@VERIFIED_EXISTING_TREE_DIGEST@';"
if source.count(placeholder)!=1:raise SystemExit('transport before-digest placeholder mismatch')
source=source.replace(placeholder,"const SSWR_BEFORE='%s';"%derived)
data={'candidate.zip':archive,'candidate-manifest.json':manifest,'binding.php':binding.encode(),'sanro-studio-staging-release.php':source.encode()}
for name in ['transaction.php','acceptance.php','config-lease.php','checkout-acceptance.php']:data[name]=(repo/'tools/staging-web-release'/name).read_bytes()
with zipfile.ZipFile(out/'sanro-studio-staging-release.zip','w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
 for name,content in sorted(data.items()):
  info=zipfile.ZipInfo('sanro-studio-staging-release/'+name,(1980,1,1,0,0,0));info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,content)
(out/'transport-binding.json').write_text(json.dumps({'schema':1,'candidate_source':m['source_commit'],'candidate_zip_sha256':m['archive_sha256'],'candidate_manifest_sha256':hashlib.sha256(manifest).hexdigest(),'installer_source_commit':installer_source_commit,'existing_release_version':old['version'],'existing_release_source':old['source_commit'],'existing_release_archive_sha256':old['archive_sha256'],'existing_release_manifest_sha256':hashlib.sha256(old_manifest).hexdigest(),'verified_existing_tree_digest':derived,'transport_sha256':hashlib.sha256((out/'sanro-studio-staging-release.zip').read_bytes()).hexdigest(),'staging_url':'https://saddlebrown-peafowl-616656.hostingersite.com','apply_automatic':False,'writers_changed':False},indent=2)+'\n')
print((out/'transport-binding.json').read_text())
