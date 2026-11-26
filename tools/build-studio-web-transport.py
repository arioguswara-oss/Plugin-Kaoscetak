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
# Exact guest-cart P0 candidate currently installed on staging; this immutable
# predecessor is bound to its published manifest/archive and freshly measured tree.
INSTALLED_GUEST_CART_P0={
 'manifest_sha256':'cf562494a0e0a5def4d2393bc3adffb18e9dc62e77440aefaa7c2fd812bbf6b7',
 'archive_sha256':'e4b6abb2529f620ba4d71ff69fc210f672c2b27af3bb50bd4d6d405fb3c0409d',
 'version':'1.1.0','channel':'candidate','source_commit':'e36af5addcd16eacab9c8463c6a50bab73b94d89',
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
# Exact variable-product candidate currently installed on staging, revalidated
# from its immutable archive/manifest and the freshly measured live tree digest.
INSTALLED_VARIABLE_E2E={
 'manifest_sha256':'63b0eb377810fa68b120ba151ffd997efe356717e0bae50a0da231374db2fa48',
 'archive_sha256':'a8503ff5520164ea4d617b484031ca8e778cc1e8a42b6e6d9d1d879b8bda82c0',
 'version':'1.1.0','channel':'candidate','source_commit':'690fb0be568713328a0c6e2fd5f81b3fb457a655',
}
# Installed and journal-verified 1.1.0 predecessor in the release evidence.
INSTALLED_LUMISE_CORRECTION={
 'manifest_sha256':'fd9708b0d603bc338a1eeb63d673fb493a2e7be6d751f6548935f55167b33d77',
 'archive_sha256':'60c9bd4096765c854f70aa46dbe1bbfa606052f01aec0d13d01b1d184b967aed',
 'version':'1.1.0','channel':'candidate','source_commit':'964e428442e21cbae05bd336b81f8aef82cac424',
}
# Standard-mockup predecessor recorded by the locked staging install.
INSTALLED_STANDARD_MOCKUP={
 'manifest_sha256':'2fd4d92199c3c724019d9544336c99b18f919797af6b090f420888dfff4b821c',
 'archive_sha256':'ce74fc6f3140b5c9b0e6f30cf1319af12996014a2ca881e5fc532729295b63b7',
 'version':'1.1.0','channel':'candidate','source_commit':'094a511b7b315358dd6641bbca7ee9c4664619a9',
}
# Automatic-sizing candidate verified on staging before the navigation correction.
INSTALLED_AUTO_PRINT={
 'manifest_sha256':'42a2a7aa6e0614dc1fbd688ad1d42ea30af8e2952c1b325c2fcddb9d35fad196',
 'archive_sha256':'6c80d5f5cadb55998a7d6bb768aee2c0077f5f65329103e2411ac5caa3ca75aa',
 'version':'1.1.0','channel':'candidate','source_commit':'c5d1184ee968ae2f2fcafb8646c22670f231fd0e',
}
# Installed navigation candidate, bound to the exact staging rollback predecessor.
INSTALLED_CHECKOUT_NAV={
 'manifest_sha256':'1831073a0750f8f28b279e566806ab08fdc74cfce08de063551e11ee0db6bdfd',
 'archive_sha256':'754e32773f596c25de5156ac8524bd69e4641dc9132334fe6bbb42ae701b0f2f',
 'version':'1.1.0','channel':'candidate','source_commit':'782271659669581abfe5588294428c564af7c049',
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
is_installed_guest_cart_p0=(old_manifest_sha==INSTALLED_GUEST_CART_P0['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_GUEST_CART_P0.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_ui_p1=(old_manifest_sha==INSTALLED_UI_P1['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_UI_P1.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_catalog=(old_manifest_sha==INSTALLED_CATALOG_P1['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_CATALOG_P1.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_variable=(old_manifest_sha==INSTALLED_VARIABLE_E2E['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_VARIABLE_E2E.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_correction=(old_manifest_sha==INSTALLED_LUMISE_CORRECTION['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_LUMISE_CORRECTION.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_standard=(old_manifest_sha==INSTALLED_STANDARD_MOCKUP['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_STANDARD_MOCKUP.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_auto=(old_manifest_sha==INSTALLED_AUTO_PRINT['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_AUTO_PRINT.items() if k not in ('manifest_sha256','archive_sha256')))
is_installed_nav=(old_manifest_sha==INSTALLED_CHECKOUT_NAV['manifest_sha256'] and all(old.get(k)==v for k,v in INSTALLED_CHECKOUT_NAV.items() if k not in ('manifest_sha256','archive_sha256')))
if not (is_final or is_installed_ui or is_installed_guest_cart_p0 or is_installed_ui_p1 or is_installed_catalog or is_installed_variable or is_installed_correction or is_installed_standard or is_installed_auto or is_installed_nav):raise SystemExit('existing manifest is not an exact approved Final Release or previously installed 1.1.0 staging candidate')
if hashlib.sha256(old_archive).hexdigest()!=old.get('archive_sha256'):raise SystemExit('existing release archive checksum mismatch')
if is_installed_ui and old.get('archive_sha256')!=INSTALLED_UI_110['archive_sha256']:raise SystemExit('installed UI archive is not the previously verified staging archive')
if is_installed_guest_cart_p0 and old.get('archive_sha256')!=INSTALLED_GUEST_CART_P0['archive_sha256']:raise SystemExit('installed guest-cart P0 archive is not the previously verified staging archive')
if is_installed_ui_p1 and old.get('archive_sha256')!=INSTALLED_UI_P1['archive_sha256']:raise SystemExit('installed P1 UI archive is not the previously verified staging archive')
if is_installed_catalog and old.get('archive_sha256')!=INSTALLED_CATALOG_P1['archive_sha256']:raise SystemExit('installed catalog archive is not the previously verified staging archive')
if is_installed_variable and old.get('archive_sha256')!=INSTALLED_VARIABLE_E2E['archive_sha256']:raise SystemExit('installed variable-product archive is not the previously verified staging archive')
if is_installed_correction and old.get('archive_sha256')!=INSTALLED_LUMISE_CORRECTION['archive_sha256']:raise SystemExit('installed correction archive is not the previously verified staging archive')
if is_installed_standard and old.get('archive_sha256')!=INSTALLED_STANDARD_MOCKUP['archive_sha256']:raise SystemExit('installed standard-mockup archive mismatch')
if is_installed_auto and old.get('archive_sha256')!=INSTALLED_AUTO_PRINT['archive_sha256']:raise SystemExit('installed automatic-sizing archive mismatch')
if is_installed_nav and old.get('archive_sha256')!=INSTALLED_CHECKOUT_NAV['archive_sha256']:raise SystemExit('installed checkout-navigation archive mismatch')
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
if derived!=a.verified_existing_tree_digest:raise SystemExit('staging tree digest does not match the pinned predecessor manifest')
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
