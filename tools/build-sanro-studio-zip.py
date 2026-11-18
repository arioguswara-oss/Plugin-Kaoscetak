#!/usr/bin/env python3
"""Build an installable, staging-only SANRO Studio ZIP from a local checkout."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED, ZipInfo
import argparse
import hashlib
import subprocess
import sys
import os

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugins" / "sanro-studio"
EXCLUDE = {"tests", "runtime", "__pycache__", ".git"}
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", default="dist/sanro-studio-staging.zip")
    args = parser.parse_args()
    entry = PLUGIN / "sanro-studio.php"
    if not entry.is_file():
        sys.exit("ERROR: SANRO Studio entrypoint missing")
    files = []
    for path in sorted(PLUGIN.rglob("*")):
        if any(part in EXCLUDE for part in path.relative_to(PLUGIN).parts):
            continue
        if path.is_symlink():
            sys.exit(f"ERROR: symlink rejected: {path}")
        if path.is_file():
            files.append(path)
    if not files or not any(p.suffix == ".php" for p in files):
        sys.exit("ERROR: no plugin source")
    for path in files:
        if path.suffix == ".php":
            result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
            if result.returncode:
                sys.exit(f"FAIL: PHP lint {path}: {result.stdout} {result.stderr}")
    output = (ROOT / args.output).resolve()
    if output.is_relative_to(PLUGIN.resolve()):
        sys.exit("ERROR: output must be outside plugin source")
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output, "w", ZIP_DEFLATED) as archive:
        for path in files:
            info = ZipInfo("sanro-studio/" + path.relative_to(PLUGIN).as_posix(), date_time=(2026, 1, 1, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.external_attr = (0o100644 << 16)
            archive.writestr(info, path.read_bytes())
    with ZipFile(output) as archive:
        names = archive.namelist()
        if len(names) != len(files) or len(set(names)) != len(names) or "sanro-studio/sanro-studio.php" not in names or archive.testzip() is not None:
            sys.exit("ERROR: ZIP integrity validation failed")
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    print(f"PASS: {len(files)} files; ZIP={output}; SHA256={digest}")
    print("STAGING ONLY: build does not enable checkout or authorize production deployment")

if __name__ == "__main__":
    main()
