#!/usr/bin/env python3
"""Bundle a pinned candidate with the exact-staging PHP installer, never deploy."""
import argparse,hashlib,io,json,pathlib,re,subprocess,zipfile
APPROVED_V1_MANIFEST_SHA256='556ecd490dcad05a67c55b34e02d04bba6b3e1e25a517f073196f94649b242cd'
# The only non-Final predecessor accepted here is the previously installed
# staging candidate, bound to its immutable manifest and archive hashes.
INSTALLED_UI_110={
 'manifest_sha256':'a822f0312b8183b98ac37e35c2189e12149e6d55a4e7a5b9bf7950fd15a91d0f',
 'archive_sha256':'991f53c93ad6ae7c8c73bad1af4bd1b5406beac93ca7c64d6b0365a289e7896b',
 'version':'1.1.0','channel':'candidate','source_commit':'12b2c043cc6ccbb3e7c828949bf524ff6f2e84b1',
}
# Exact P1-corrected 1.1.0 candidate most recently verified on staging.
INSTALLED_UI_P1={
 'manifest_sha256':'bae045a6d8255a482bba191c2a7a5983c83234f7cee887d9302d02231bfabd15',
 'archive_sha256':'cd4c86ec548de7d109b751734c0a30dff55f67bf83594ec4430b276633cf0b9a',
 'version':'1.1.0','channel':'candidate','source_commit':'826c152c3dbf2e35fff74d276e9c2fd00aed2905',
}
# Exact catalog P1 candidate currently installed on staging; the builder
# accepts it only after its immutable manifest/archive and live tree digest
# are revalidated by the source-bound transport.
INSTALLED_CATALOG_P1={
 'manifest_sha256':'ef4926df914907f7b7d1b5e42c49f2038dc0d54e52c4c0236bde187473903e4e',
 'archive_sha256':'5a0b02537e8e50a93d975312457140c7b3d6a129a134ad3003cd0a93ff52397d',
 'version':'1.1.0','channel':'candidate','source_commit':'3a3a3ea6606608143658a55e998f2bbbdb9be8ee',
}
p=argparse.ArgumentParser();p.add_argument('--candidate',required=True);p.add_argument('--out',required=True);p.add_argument('--existing-manifest',required=True);p.add_argument('--existing-archive',required=True);p.add_argument('--verified-existing-tree-digest',required=True);a=p.parse_args()
repo=pathlib.Path(__file__).resolve().parents[1];candidate=pathlib.Path(a.candidate);out=pathlib.Path(a.out);out.mkdir(parents=True,exist_ok=True)
installer_source_commit=subprocess.check_output(['git','-C',str(repo),'rev-parse','HEAD'],text=True).strip()
manifest=(candidate/('release-manifest.json' if (candidate/'release-manifest.json').exists() else 'candidate-manifest.json')).read_bytes();m=json.loads(manifest);archive=(candidate/m.get('archive_name','sanro-studio-staging-candidate.zip')).read_bytes()
if hashlib.sha256(archive).hexdigest()!=m['archive_sha256']:raise SystemExit('candidate checksum mismatch')
if not re.fullmatch('[a-f0-9]{64}',a.verified_existing_tree_digest):raise SystemExit('verified existing digest format invalid')
old_manifest_path=pathlib.Path(a.existing_manifest);old_archive_path=pathlib.Path(a.existing_archive);old_manifest=old_manifest_path.read_bytes();old=json.loads(old_manifest);old_archive=old_archive_path.read_bytes()
old_manifest_sha=hashlib.sha256(old_manifest).hexdigest()
is_final=(old.get('version')=='1.0.0' and old.get('channel')=='final' and old.get('deployment_authorized') is False and old_manifest_sha==APPROVED_V1_MANIFEST_SHA256)
is_installed_ui=(old_manifest_sha==INSTALLED_UI_110['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_UI_110.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_ui_p1=(old_manifest_sha==INSTALLED_UI_P1['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_UI_P1.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_catalog=(old_manifest_sha==INSTALLED_CATALOG_P1['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_CATALOG_P1.items() if k not in ('manifest_sha256','archive_sha256')))
if not (is_final or is_installed_ui or is_installed_ui_p1 or is_installed_catalog):raise SystemExit('existing manifest is not an exact approved Final Release or previously installed 1.1.0 staging candidate')
if hashlib.sha256(old_archive).hexdigest()!=old.get('archive_sha256'):raise SystemExit('existing release archive checksum mismatch')
if is_installed_ui and old.get('archive_sha256')!=INSTALLED_UI_110['archive_sha256']:raise SystemExit('installed UI archive is not the previously verified staging archive')
if is_installed_ui_p1 and old.get('archive_sha256')!=INSTALLED_UI_P1['archive_sha256']:raise SystemExit('installed P1 UI archive is not the previously verified staging archive')
if is_installed_catalog and old.get('archive_sha256')!=INSTALLED_CATALOG_P1['archive_sha256']:raise SystemExit('installed catalog archive is not the previously verified staging archive')
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
(out/'transport-binding.json').write_text(json.dumps({'schema':1,'candidate_source':m['source_commit'],'candidate_zip_sha256':m['archive_sha256'],'candidate_manifest_sha256':hashlib.sha256(manifest).hexdigest(),'installer_source_commit':installer_source_commit,'existing_release_version':old['version'],'existing_release_channel':old.get('channel'),'existing_release_source':old['source_commit'],'existing_release_archive_sha256':old['archive_sha256'],'existing_release_manifest_sha256':old_manifest_sha,'verified_existing_tree_digest':derived,'transport_sha256':hashlib.sha256((out/'sanro-studio-staging-release.zip').read_bytes()).hexdigest(),'staging_url':'https://saddlebrown-peafowl-616656.hostingersite.com','apply_automatic':False,'writers_changed':False},indent=2)+'\n')
print((out/'transport-binding.json').read_text())
