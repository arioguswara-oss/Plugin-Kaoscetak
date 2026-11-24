"""Only the new GC flag in the existing installer probe; no installation/subprocess/DB writes."""
import importlib.util
import json
import pathlib
import types
import unittest
from unittest.mock import patch

PATH = pathlib.Path(__file__).resolve().parents[1] / 'tools/studio-staging-install.py'
spec = importlib.util.spec_from_file_location('studio_gc_gate_test', PATH)
installer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installer)

class NativeGCGate(unittest.TestCase):
    def state(self):
        return {'url': installer.TARGET, 'plugin_dir': '/fixture/wp/wp-content/plugins',
                'active': ['sanro-studio/sanro-studio.php', 'woocommerce/woocommerce.php'],
                'flags': {name: False for name in installer.FLAGS}, 'multisite': False}

    def probe(self, state):
        with patch.object(installer.subprocess, 'run', return_value=types.SimpleNamespace(
                returncode=0, stdout=json.dumps(state))) as run:
            result = installer.probe('wp', pathlib.Path('/fixture/wp'))
            self.assertIn("'STORAGE_GC'", run.call_args.args[0][-1])
            return result

    def test_explicit_false_accepted(self):
        self.assertIs(self.probe(self.state())['flags']['STORAGE_GC'], False)

    def test_enabled_gc_refused(self):
        state = self.state(); state['flags']['STORAGE_GC'] = True
        with self.assertRaises(ValueError): self.probe(state)

    def test_missing_gc_proof_refused(self):
        state = self.state(); del state['flags']['STORAGE_GC']
        with self.assertRaises(ValueError): self.probe(state)

    def test_numeric_false_not_boolean_proof(self):
        state = self.state(); state['flags']['STORAGE_GC'] = 0
        with self.assertRaises(ValueError): self.probe(state)

if __name__ == '__main__': unittest.main()
