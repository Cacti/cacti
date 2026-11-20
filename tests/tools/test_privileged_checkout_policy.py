"""Exercise trusted checkout policy without copying the production module.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
from contextlib import redirect_stdout
import importlib.util
import io
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import yaml

SCRIPT = Path(__file__).resolve().parents[2] / '.github/scripts/check-workflow-policy.py'
SPEC = importlib.util.spec_from_file_location('privileged_checkout_policy', SCRIPT)
POLICY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(POLICY)


class PrivilegedCheckoutPolicyTest(unittest.TestCase):
    def validate(self, event, ref, repository=None):
        checkout = {'persist-credentials': False, 'ref': ref}
        if repository is not None:
            checkout['repository'] = repository
        workflow = {'on': event, 'jobs': {'review': {
            'runs-on': 'ubuntu-24.04', 'timeout-minutes': 5, 'steps': [{
                'uses': 'actions/checkout@' + 'a' * 40, 'with': checkout}]}}}
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            path = root / '.github/workflows/review.yml'
            path.parent.mkdir(parents=True)
            path.write_text(yaml.safe_dump(workflow))
            output = io.StringIO()
            with patch.object(POLICY, '__file__', str(root / '.github/scripts/check-workflow-policy.py')), \
                    patch('sys.argv', ['policy', '--workflows', '.github/workflows/review.yml']), \
                    redirect_stdout(output):
                status = POLICY.main()
            return status, output.getvalue()

    def test_privileged_events_accept_only_the_current_repositories_trusted_refs(self):
        for event in ['pull_request_target', ['pull_request_target'], {'pull_request_target': {}}]:
            for ref in ['${{ github.sha }}', '${{ github.event.pull_request.base.sha }}']:
                with self.subTest(event=event, ref=ref):
                    self.assertEqual(self.validate(event, ref)[0], 0)

    def test_privileged_events_reject_head_refs_and_repository_substitution(self):
        for ref, repository in [('${{ github.event.pull_request.head.sha }}', None),
                                ('${{ github.sha }}', 'other/repository')]:
            with self.subTest(ref=ref, repository=repository):
                status, output = self.validate({'pull_request_target': {}}, ref, repository)
                self.assertEqual(status, 1)
                self.assertIn('trusted base SHA', output)

    def test_read_only_pull_request_jobs_can_check_out_the_head(self):
        self.assertEqual(self.validate({'pull_request': {}}, '${{ github.event.pull_request.head.sha }}')[0], 0)


if __name__ == '__main__':
    unittest.main()
