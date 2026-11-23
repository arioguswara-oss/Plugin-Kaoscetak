import hashlib
import importlib.util
import io
import json
from pathlib import Path
import stat
import tempfile
import unittest
import warnings
import zipfile

MODULE = Path(__file__).resolve().parents[1] / 'tools/prepare-sanro-runtime.py'
spec = importlib.util.spec_from_file_location('sanro_prepare', MODULE)
prepare = importlib.util.module_from_spec(spec)
spec.loader.exec_module(prepare)


class RuntimePreparation(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.archive = self.root / 'core.zip'
        self.source = self.root / 'source.json'
        self.dest = self.root / 'editor'

    def tearDown(self):
        self.temp.cleanup()

    def fixture(self, extra=None, digest_override=None):
        stream = io.BytesIO()
        core = b'<?php // synthetic core\n'
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', UserWarning)
            with zipfile.ZipFile(stream, 'w') as bundle:
                bundle.writestr('lumise.php', core)
                bundle.writestr('LICENSE', b'Preserve vendor license notice')
                bundle.writestr('.git/objects/ignored', b'metadata')
                if extra:
                    bundle.writestr(*extra)
        data = stream.getvalue()
        self.archive.write_bytes(data)
        self.source.write_text(json.dumps({'version': '2.1.2', 'archive_sha256': hashlib.sha256(data).hexdigest(),
            'critical_files': {'lumise.php': digest_override or hashlib.sha256(core).hexdigest()}}))

    def run_prepare(self):
        return prepare.prepare(self.archive, self.dest, self.source)

    def test_success_and_preserves_license_without_git(self):
        self.fixture()
        result = self.run_prepare()
        self.assertEqual(result['status'], 'prepared')
        self.assertEqual(result['files'], 2)
        self.assertEqual((self.dest / 'LICENSE').read_text(), 'Preserve vendor license notice')
        self.assertFalse((self.dest / '.git').exists())

    def test_idempotent_does_not_rewrite_files(self):
        self.fixture(); self.run_prepare()
        stamp = (self.dest / 'lumise.php').stat().st_mtime_ns
        self.assertEqual(self.run_prepare()['status'], 'verified_existing')
        self.assertEqual((self.dest / 'lumise.php').stat().st_mtime_ns, stamp)

    def test_wrong_archive_rejected_before_creating_output(self):
        self.fixture(); self.archive.write_bytes(self.archive.read_bytes() + b'changed')
        with self.assertRaisesRegex(ValueError, 'Archive SHA256'):
            self.run_prepare()
        self.assertFalse(self.dest.exists())

    def test_unsafe_zip_names_rejected(self):
        for name in ['../escape.php', '/absolute.php', 'C:/file.php', 'dir\\escape.php']:
            with self.subTest(name=name):
                self.fixture((name, b'bad'))
                with self.assertRaisesRegex(ValueError, 'Unsafe ZIP'):
                    self.run_prepare()
                self.assertFalse(self.dest.exists())

    def test_zip_symlink_rejected(self):
        info = zipfile.ZipInfo('link'); info.create_system = 3
        info.external_attr = (stat.S_IFLNK | 0o777) << 16
        self.fixture((info, b'../../elsewhere'))
        with self.assertRaisesRegex(ValueError, 'Unsafe ZIP'):
            self.run_prepare()

    def test_duplicate_rejected(self):
        self.fixture(('lumise.php', b'second entry'))
        with self.assertRaisesRegex(ValueError, 'Duplicate ZIP'):
            self.run_prepare()

    def test_critical_source_mismatch_rejected(self):
        self.fixture(digest_override='a' * 64)
        with self.assertRaisesRegex(ValueError, 'Critical runtime'):
            self.run_prepare()

    def test_existing_changes_never_overwritten(self):
        self.fixture(); self.run_prepare()
        (self.dest / 'lumise.php').write_text('local edit')
        with self.assertRaisesRegex(ValueError, 'refusing to overwrite'):
            self.run_prepare()
        self.assertEqual((self.dest / 'lumise.php').read_text(), 'local edit')

    def test_destination_symlink_rejected(self):
        self.fixture()
        elsewhere = self.root / 'elsewhere'; elsewhere.mkdir()
        self.dest.symlink_to(elsewhere, target_is_directory=True)
        with self.assertRaisesRegex(ValueError, 'Destination must not'):
            self.run_prepare()

    def test_unexpected_existing_files_are_preserved_and_rejected(self):
        self.fixture(); self.run_prepare()
        (self.dest / 'unexpected.php').write_text('local file')
        with self.assertRaisesRegex(ValueError, 'file set differs'):
            self.run_prepare()
        self.assertEqual((self.dest / 'unexpected.php').read_text(), 'local file')


if __name__ == '__main__':
    unittest.main()
