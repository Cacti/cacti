"""Verify review inputs default to the runner's private temporary directory."""

import importlib.util
import os
from pathlib import Path
from unittest import TestCase, main, mock
import tempfile

SCRIPT = Path(__file__).resolve().parents[2] / '.github/scripts/security_review.py'


class SecurityReviewPathsTest(TestCase):
    def load(self, environment):
        with mock.patch.dict(os.environ, environment, clear=True):
            spec = importlib.util.spec_from_file_location('cacti_review_paths', SCRIPT)
            module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(module)
        return module

    def test_runner_inputs_stay_in_the_dedicated_directory(self):
        with tempfile.TemporaryDirectory() as directory:
            module = self.load({'RUNNER_TEMP': directory})
            for attribute, filename in [('DIFF_FILE', 'pr.diff'), ('GREP_FILE', 'grep_hits.txt'), ('HOTSPOT_FILE', 'hotspot_contents.txt')]:
                self.assertEqual(Path(getattr(module, attribute)), Path(directory) / 'cacti-security-review' / filename)

    def test_local_defaults_are_beside_the_script(self):
        module = self.load({})
        self.assertEqual(Path(module.DIFF_FILE).parent, SCRIPT.parent / 'cacti-security-review')

    def test_explicit_input_and_truncation_contract_are_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'explicit.diff'
            path.write_text('abcdefghij')
            module = self.load({'PR_DIFF_FILE': str(path)})
            self.assertEqual(module.DIFF_FILE, str(path))
            self.assertEqual(module.read_truncated(module.DIFF_FILE, 4), 'abcd\n[... truncated ...]')
            self.assertEqual(module.read_truncated(str(path.parent / 'absent'), 4), '(not available)')


if __name__ == '__main__':
    main()
