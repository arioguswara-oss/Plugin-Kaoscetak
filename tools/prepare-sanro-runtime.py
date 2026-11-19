#!/usr/bin/env python3
"""Prepare the owner's pinned private editor core, without copying it into Git history."""
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import stat
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'plugins/sanro-studio/runtime/source.json'
DEFAULT_DEST = SOURCE.parent / 'editor'


def digest(data):
    return hashlib.sha256(data).hexdigest()


def prepare(archive, destination=DEFAULT_DEST, source_file=SOURCE):
    source = json.loads(Path(source_file).read_text())
    archive = Path(archive)
    if digest(archive.read_bytes()) != source['archive_sha256']:
        raise ValueError('Archive SHA256 differs from the reviewed source')
    files = {}
    excluded = 0
    with zipfile.ZipFile(archive) as bundle:
        total = 0
        for item in bundle.infolist():
            path = PurePosixPath(item.filename)
            if (not item.filename or '\\' in item.filename or path.is_absolute()
                    or '..' in path.parts or ':' in item.filename
                    or stat.S_ISLNK(item.external_attr >> 16)):
                raise ValueError('Unsafe ZIP entry')
            if '.git' in path.parts or '__MACOSX' in path.parts:
                excluded += 1
                continue
            if item.is_dir():
                continue
            total += item.file_size
            if total > 100 * 1024 * 1024 or item.file_size > 20 * 1024 * 1024:
                raise ValueError('Expanded runtime exceeds source limits')
            name = path.as_posix()
            if name in files:
                raise ValueError('Duplicate ZIP entry')
            files[name] = bundle.read(item)
    for name, expected in source['critical_files'].items():
        if name not in files or digest(files[name]) != expected:
            raise ValueError('Critical runtime source is missing or changed')

    destination = Path(destination)
    if destination.is_symlink():
        raise ValueError('Destination must not be a symlink')
    if destination.exists():
        existing_files = set()
        for target in destination.rglob('*'):
            if target.is_symlink():
                raise ValueError('Existing runtime contains a symlink; refusing to overwrite')
            if target.is_file():
                existing_files.add(target.relative_to(destination).as_posix())
        if existing_files != set(files):
            raise ValueError('Existing runtime file set differs; refusing to overwrite')
        for name, data in files.items():
            target = destination / name
            if (not target.is_file() or target.is_symlink()
                    or not target.resolve().is_relative_to(destination.resolve())
                    or digest(target.read_bytes()) != digest(data)):
                raise ValueError('Existing runtime differs; refusing to overwrite')
        result = 'verified_existing'
    else:
        destination.parent.mkdir(parents=True, exist_ok=True)
        temporary = Path(tempfile.mkdtemp(prefix='.sanro-core-', dir=destination.parent))
        try:
            for name, data in files.items():
                target = temporary / name
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(data)
            os.rename(temporary, destination)
        finally:
            if temporary.exists():
                shutil.rmtree(temporary)
        result = 'prepared'
    return {'status': result, 'version': source['version'], 'files': len(files),
            'bytes': sum(len(data) for data in files.values()), 'metadata_entries_excluded': excluded}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path)
    parser.add_argument('--destination', type=Path, default=DEFAULT_DEST)
    args = parser.parse_args()
    try:
        print(json.dumps(prepare(args.archive, args.destination), sort_keys=True))
    except (ValueError, OSError, KeyError, zipfile.BadZipFile) as error:
        parser.exit(1, f'Core preparation rejected: {error}\n')


if __name__ == '__main__':
    main()
