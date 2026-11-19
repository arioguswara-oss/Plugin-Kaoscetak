#!/usr/bin/env python3
"""Smoke-test the SANRO Studio staging packager without network or WordPress."""
from pathlib import Path
from tempfile import TemporaryDirectory
from zipfile import ZipFile
import hashlib
import shutil
import subprocess
import sys

SOURCE = Path(__file__).with_name("build-sanro-studio-zip.py")
def run(root, output="dist/staging.zip"):
    return subprocess.run([sys.executable, str(root / "tools" / SOURCE.name), "--output", output], text=True, capture_output=True)
def check(condition, message):
    if not condition:
        raise AssertionError(message)
    print("PASS:", message)
with TemporaryDirectory(prefix="sanro-studio-build-test-") as tmp:
    root = Path(tmp)
    (root / "tools").mkdir()
    shutil.copy2(SOURCE, root / "tools" / SOURCE.name)
    plugin = root / "plugins" / "sanro-studio"
    (plugin / "includes").mkdir(parents=True)
    (plugin / "tests").mkdir()
    (plugin / "runtime").mkdir()
    (plugin / "sanro-studio.php").write_text("<?php\n// staging fixture\n")
    (plugin / "includes" / "core.php").write_text("<?php\nfunction fixture_core() {}\n")
    (plugin / "tests" / "not-for-release.php").write_text("<?php\n")
    (plugin / "runtime" / "private.json").write_text('{"secret":"not-for-release"}')
    first = run(root)
    check(first.returncode == 0, "valid fixture builds: " + first.stderr)
    archive = root / "dist" / "staging.zip"
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    with ZipFile(archive) as z:
        names = z.namelist()
        check(names == ["sanro-studio/includes/core.php", "sanro-studio/sanro-studio.php"], "release contains only plugin source")
        check(z.testzip() is None, "ZIP integrity")
    second = run(root)
    check(second.returncode == 0 and hashlib.sha256(archive.read_bytes()).hexdigest() == digest, "reproducible ZIP checksum")
    invalid = run(root, "plugins/sanro-studio/bad.zip")
    check(invalid.returncode != 0, "reject output within plugin directory")
    (plugin / "includes" / "core.php").write_text("<?php\nfunction (\n")
    invalid_php = run(root)
    check(invalid_php.returncode != 0 and "PHP lint" in invalid_php.stderr, "fail closed on invalid PHP")
    (plugin / "includes" / "core.php").write_text("<?php\nfunction fixture_core() {}\n")
    (plugin / "sanro-studio.php").unlink()
    missing = run(root)
    check(missing.returncode != 0 and "entrypoint missing" in missing.stderr, "reject missing entrypoint")
print("PASS: staging packager smoke tests")
