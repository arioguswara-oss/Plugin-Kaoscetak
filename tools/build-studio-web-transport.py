#!/usr/bin/env python3
"""Bundle a pinned candidate with the exact-staging PHP installer, never deploy."""
import argparse,hashlib,json,pathlib,zipfile
p=argparse.ArgumentParser();p.add_argument('--candidate',required=True);p.add_argument('--out',required=True);a=p.parse_args()
repo=pathlib.Path(__file__).resolve().parents[1];candidate=pathlib.Path(a.candidate);out=pathlib.Path(a.out);out.mkdir(parents=True,exist_ok=True)
manifest=(candidate/'candidate-manifest.json').read_bytes();m=json.loads(manifest);archive=(candidate/'sanro-studio-staging-candidate.zip').read_bytes()
if hashlib.sha256(archive).hexdigest()!=m['archive_sha256']:raise SystemExit('candidate checksum mismatch')
binding='<?php\n'+''.join("const SSWR_%s='%s';\n"%(key,value) for key,value in [('SOURCE',m['source_commit']),('ARCHIVE',m['archive_sha256']),('MANIFEST',hashlib.sha256(manifest).hexdigest())])
data={'candidate.zip':archive,'candidate-manifest.json':manifest,'binding.php':binding.encode()}
for name in ['sanro-studio-staging-release.php','transaction.php']:data[name]=(repo/'tools/staging-web-release'/name).read_bytes()
with zipfile.ZipFile(out/'sanro-studio-staging-release.zip','w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
 for name,content in sorted(data.items()):
  info=zipfile.ZipInfo('sanro-studio-staging-release/'+name,(1980,1,1,0,0,0));info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,content)
(out/'transport-binding.json').write_text(json.dumps({'schema':1,'candidate_source':m['source_commit'],'candidate_zip_sha256':m['archive_sha256'],'candidate_manifest_sha256':hashlib.sha256(manifest).hexdigest(),'transport_sha256':hashlib.sha256((out/'sanro-studio-staging-release.zip').read_bytes()).hexdigest(),'staging_url':'https://saddlebrown-peafowl-616656.hostingersite.com','apply_automatic':False,'writers_changed':False},indent=2)+'\n')
print((out/'transport-binding.json').read_text())
