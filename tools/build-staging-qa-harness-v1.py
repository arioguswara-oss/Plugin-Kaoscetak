#!/usr/bin/env python3
"""Build the small, source-independent QA contract bundle reproducibly."""
from __future__ import annotations
import argparse
import hashlib
import json
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INCLUDED = [Path('README.md'), Path('tests/staging-qa-harness-contract.php')]
FIXED_TIME = (2026, 1, 1, 0, 0, 0)

def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--output-dir', type=Path, required=True)
    args = parser.parse_args()
    if len(args.source_sha) != 40 or any(c not in '0123456789abcdef' for c in args.source_sha):
        parser.error('--source-sha must be a full lowercase Git commit SHA')
    payload = {p.as_posix(): (ROOT / p).read_bytes() for p in INCLUDED}
    manifest = {
        'schema': 1,
        'bundle': 'SANRO Studio Staging QA Harness V1',
        'repository': 'arioguswara-oss/Plugin-Kaoscetak',
        'source_commit': args.source_sha,
        'version': '1.1.0',
        'purpose': 'offline source contract validation; no staging operations',
        'files': [{'path': p, 'bytes': len(data), 'sha256': sha(data)} for p, data in sorted(payload.items())],
        'staging_execution': 'NOT_RUN',
        'plugin_installer_payload': False,
        'baseline_percent': 96.60,
    }
    manifest_bytes = (json.dumps(manifest, indent=2, ensure_ascii=False) + '\n').encode()
    payload['manifest.json'] = manifest_bytes
    args.output_dir.mkdir(parents=True, exist_ok=True)
    archive = args.output_dir / 'sanro-studio-staging-qa-harness-v1.zip'
    with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for name, data in sorted(payload.items()):
            info = zipfile.ZipInfo(name, date_time=FIXED_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            info.create_system = 3
            zf.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    (args.output_dir / 'manifest.json').write_bytes(manifest_bytes)
    sums = f"{sha(archive.read_bytes())}  {archive.name}\n{sha(manifest_bytes)}  manifest.json\n"
    (args.output_dir / 'SHA256SUMS').write_text(sums, encoding='ascii')
    print(f'ARCHIVE: {archive}')
    print(f'ARCHIVE_SHA256: {sha(archive.read_bytes())}')
    print(f'MANIFEST_SHA256: {sha(manifest_bytes)}')
    print(f'FILES: {len(payload)}')
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
