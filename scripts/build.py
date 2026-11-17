#!/usr/bin/env python3
"""Deterministic owner-QA archive only; never installs or deploys."""
import hashlib,json,re
from pathlib import Path
from zipfile import ZipFile,ZipInfo,ZIP_DEFLATED
root=Path(__file__).resolve().parents[1]
source=root/'plugins/kaoscetak-lite-designer'
version=re.search(r'^ \* Version: (.+)$',(source/'kaoscetak-lite-designer.php').read_text(),re.M).group(1)
assert "define('KLD_VERSION','"+version+"')" in (source/'kaoscetak-lite-designer.php').read_text()
out=root/'dist';out.mkdir(exist_ok=True)
archive=out/f'kaoscetak-lite-designer-{version}.zip'
files=[p for p in sorted(source.rglob('*')) if p.is_file() and not any(part.startswith('.') for part in p.relative_to(source).parts)]
with ZipFile(archive,'w',compression=ZIP_DEFLATED) as z:
 for p in files:
  assert not p.is_symlink()
  info=ZipInfo('kaoscetak-lite-designer/'+p.relative_to(source).as_posix(),(2020,1,1,0,0,0));info.compress_type=ZIP_DEFLATED;info.external_attr=0o100644<<16
  z.writestr(info,p.read_bytes())
with ZipFile(archive) as z:
 assert z.testzip() is None
 assert all(n.startswith('kaoscetak-lite-designer/') and '..' not in n for n in z.namelist())
sha=hashlib.sha256(archive.read_bytes()).hexdigest()
manifest={'file':archive.name,'version':version,'sha256':sha,'bytes':archive.stat().st_size,'files':len(files),'entries':{p.relative_to(source).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in files},'status':'DRAFT — isolated QA only; Drive/Flatsome/live unverified'}
(out/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n');(out/'SHA256SUMS').write_text(f'{sha}  {archive.name}\n');print(json.dumps(manifest))
