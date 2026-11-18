#!/usr/bin/env python3
"""Build an installable, staging-only SANRO Studio ZIP from a local checkout."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import argparse
import hashlib
import subprocess
import sys

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
    output = ROOT / args.output
    output.parent.mkdir(parents=True, exist_ok=True)
    if output.is_relative_to(PLUGIN):
        sys.exit("ERROR: output must be outside plugin source")
    with ZipFile(output, "w", ZIP_DEFLATED) as archive:
        for path in files:
            archive.write(path, "sanro-studio/" + path.relative_to(PLUGIN).as_posix())
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    print(f"PASS: {len(files)} files; ZIP={output}; SHA256={digest}")
    print("STAGING ONLY: build does not enable checkout or authorize production deployment")

if __name__ == "__main__":
    main()
