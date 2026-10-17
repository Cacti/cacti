#!/usr/bin/env python3
"""Behavior contracts for workflow policy, changelog and contributor tools."""
from __future__ import annotations

import contextlib
import importlib.util
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]


def load(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / '.github/scripts' / (name + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


@contextlib.contextmanager
def working_directory(path):
    previous = Path.cwd()
    os.chdir(path)
    try:
        yield
    finally:
        os.chdir(previous)


class WorkflowPolicyTests(unittest.TestCase):
    def setUp(self):
        self.tool = load('check-workflow-policy')

    def validate(self, document, composite=False):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            relative = '.github/actions/test/action.yml' if composite else '.github/workflows/test.yml'
            workflow = root / relative
            workflow.parent.mkdir(parents=True)
            workflow.write_text(document)
            output = io.StringIO()
            with patch.object(self.tool, '__file__', str(root / '.github/scripts/check-workflow-policy.py')), patch('sys.argv', ['check']), contextlib.redirect_stdout(output):
                result = self.tool.main()
            return result, output.getvalue()

    def test_pinned_job_and_checkout_pass(self):
        result, output = self.validate('jobs:\n  test:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 5\n    steps:\n      - uses: actions/checkout@' + 'a' * 40 + '\n        with:\n          persist-credentials: false\n      - run: |\n          set -euo pipefail\n          echo ok\n')
        self.assertEqual(result, 0)
        self.assertEqual(output, 'Workflow policy checks passed.\n')

    def test_rejects_runner_timeout_images_credentials_and_scripts(self):
        result, output = self.validate('jobs:\n  test:\n    runs-on: ubuntu-latest\n    container: php:latest\n    services:\n      db:\n        image: mariadb:latest\n    steps:\n      - uses: actions/checkout@main\n      - run: |\n          echo unsafe\n          curl https://example.test/script | bash\n')
        self.assertEqual(result, 1)
        messages = output.splitlines()[1:]
        self.assertEqual(len(messages), 8)
        for expected in ['fixed approved image', 'timeout-minutes', 'test.container', 'test.services.db', 'pinned SHA', 'persist-credentials: false', 'multiline run', 'curl|sh']:
            self.assertTrue(any(expected in message for message in messages), expected)

    def test_reusable_workflow_is_pinned_without_runner(self):
        result, output = self.validate('jobs:\n  call:\n    uses: Cacti/cacti/.github/workflows/test.yml@' + 'a' * 40 + '\n')
        self.assertEqual(result, 0, output)
        result, output = self.validate('jobs:\n  call:\n    uses: Cacti/cacti/.github/workflows/test.yml@main\n')
        self.assertEqual(result, 1)
        self.assertIn('call (job)', output)
        self.assertNotIn('timeout-minutes', output)

    def test_composite_action_checks_container_pin_and_shell(self):
        result, output = self.validate('runs:\n  using: composite\n  steps:\n    - uses: ./local\n    - uses: docker://alpine:latest\n    - run: |\n        set -euo pipefail\n        echo ok\n', True)
        self.assertEqual(result, 1)
        self.assertIn('action.step2: docker:// reference', output)
        self.assertEqual(len(output.splitlines()), 2)

    def test_non_mapping_document_and_jobs_are_ignored(self):
        for source in ['', '[]', 'jobs: []', 'jobs:\n  ignored: []']:
            with self.subTest(source=source):
                self.assertEqual(self.validate(source)[0], 0)

    def test_missing_explicit_file_is_an_error(self):
        with tempfile.TemporaryDirectory() as tmp, contextlib.redirect_stdout(io.StringIO()) as output:
            with self.assertRaises(SystemExit) as raised:
                self.tool.resolve_workflow_files(Path(tmp), ['missing.yml'])
            self.assertEqual(raised.exception.code, 1)
            self.assertIn('Workflow file not found', output.getvalue())

    def test_yaml_errors_are_reported(self):
        result, output = self.validate('jobs: [')
        self.assertEqual(result, 1)
        self.assertIn('failed to parse YAML:', output)

    def test_action_reference_boundaries(self):
        for reference, expected in [('./local', False), ('docker://alpine@sha256:abc', False), ('owner/action', True), ('owner/action@' + 'a'*40, False), ('owner/action@v1', True)]:
            violations = []
            self.tool.check_uses('workflow', 'step', reference, violations)
            self.assertEqual(bool(violations), expected)


class ChangelogTests(unittest.TestCase):
    def setUp(self):
        self.check = load('check-changelog')
        self.fold = load('fold-changelog')

    def test_fragment_diagnostics_and_skips(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for name, text in [('README.md', 'ignored'), ('.hidden', 'ignored'), ('bad.txt', 'description'), ('GHSA-abc.issue', 'description'), ('1.issue', ''), ('2.feature', 'two\nlines'), ('3.security', '-security: prefix'), ('GHSA-abc.security', 'valid')]:
                (root / name).write_text(text)
            (root / 'directory').mkdir()
            errors = self.check.check_fragments(root)
            self.assertEqual(len(errors), 6)
            for expected in ['fragment files only', 'fragment must be named', 'only a security fragment', 'fragment is empty', 'single line', 'description only']:
                self.assertTrue(any(expected in error for error in errors), expected)
            self.assertEqual(self.check.check_fragments(root/'missing'), [])

    def run_check(self, source, diff=None):
        with tempfile.TemporaryDirectory() as tmp, working_directory(tmp):
            if source is not None:
                Path('CHANGELOG').write_text(source)
            stdout, stderr = io.StringIO(), io.StringIO()
            environment = {'GITHUB_BASE_REF': 'develop'} if diff is not None else {}
            with patch.dict(os.environ, environment, clear=True), patch.object(self.check.subprocess, 'run') as run, contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
                run.return_value.returncode = 0
                run.return_value.stdout = diff or ''
                result = self.check.main()
            return result, stdout.getvalue(), stderr.getvalue()

    def test_section_order_malformed_entries_and_spacing(self):
        result, _, stderr = self.run_check('1.3.0-dev\n-security#1: security\n-issue#2: issue\n-feature#3: feature\n-issue#4: late\n-invalid: bad\n1.2.0\n')
        self.assertEqual(result, 1)
        self.assertIn('issue entry appears after feature', stderr)
        self.assertIn('malformed CHANGELOG entry', stderr)
        self.assertIn('separated by a blank line', stderr)

    def test_missing_file_or_development_section(self):
        for source, expected in [(None, 'CHANGELOG is missing'), ('1.2.0\n', 'missing the 1.3.0-dev section')]:
            result, _, stderr = self.run_check(source)
            self.assertEqual(result, 1)
            self.assertIn(expected, stderr)

    def test_legacy_entries_allowed_but_new_entries_numbered(self):
        source = '1.3.0-dev\n-issue: legacy\n\n1.2.0\n'
        self.assertEqual(self.run_check(source)[0], 0)
        self.assertEqual(self.run_check(source, '+++ b/CHANGELOG\n+-issue#12: numbered\n')[0], 0)
        result, _, stderr = self.run_check(source, '+++ b/CHANGELOG\n+-feature: missing number\n')
        self.assertEqual(result, 1)
        self.assertIn('include an issue or pull-request number', stderr)

    def test_folding_preserves_blocks_and_dry_run(self):
        original = '1.3.0-dev\n-security#1: old security\n-issue#1: old issue\n-feature#1: old feature\n\n1.2.0\n-issue#1: historical\n'
        with tempfile.TemporaryDirectory() as tmp, working_directory(tmp):
            Path('CHANGELOG').write_text(original)
            Path('changelog.d').mkdir()
            fragments = {'20.issue':'new issue', '10.issue':'earlier issue', 'GHSA-abc.security':'new security', '4.feature':'new feature'}
            for name, text in fragments.items():
                (Path('changelog.d') / name).write_text(text)
            for arguments in [['fold', '--check'], ['fold']]:
                with patch('sys.argv', arguments), contextlib.redirect_stdout(io.StringIO()) as output:
                    self.assertEqual(self.fold.main(), 0)
                self.assertIn('4 fragment(s)', output.getvalue())
                if '--check' in arguments:
                    self.assertEqual(Path('CHANGELOG').read_text(), original)
                    self.assertEqual(len(list(Path('changelog.d').iterdir())), 4)
            expected = '1.3.0-dev\n-security#GHSA-abc: new security\n-security#1: old security\n-issue#10: earlier issue\n-issue#20: new issue\n-issue#1: old issue\n-feature#1: old feature\n-feature#4: new feature\n\n1.2.0\n-issue#1: historical\n'
            self.assertEqual(Path('CHANGELOG').read_text(), expected)
            self.assertEqual(list(Path('changelog.d').iterdir()), [])


class ContributorTests(unittest.TestCase):
    def setUp(self):
        self.tool = load('update-contributors')

    def test_documentation_badge_anchors_preserve_matching(self):
        for source, expected in [('docs/a.php', True), ('README.md', True), ('a/b.md', True), ('prefixdocs/a.php', False), ('a.md.php', False)]:
            activity = {'paths': {source}, 'messages': []}
            self.assertEqual('doc' in self.tool.badges_for(activity), expected, source)

    def test_alias_activity_merges_and_badges_are_idempotent(self):
        config = {'contributors': [{'login':'TheWitness', 'name':'Larry', 'contributions':['ideas']}, {'login':'absent', 'contributions':['doc']}]}
        author = {'thewitness': {'paths': {'tests/a.php'}, 'messages': []}, 'larry adams': {'paths': {'docs/b.md'}, 'messages': ['fix XSS']}}
        with patch.object(self.tool, 'git_log_by_author', return_value=author):
            self.assertTrue(self.tool.refresh_path_badges(config))
            self.assertFalse(self.tool.refresh_path_badges(config))
        self.assertEqual(config['contributors'][0]['contributions'], ['code','security','doc','test','ideas'])
        self.assertEqual(config['contributors'][1]['contributions'], ['doc'])

    def test_reviews_paginate_skip_deleted_users_bots_and_unknowns(self):
        def page(logins, more, cursor):
            return json.dumps({'data': {'repository': {'pullRequests': {'nodes': [{'reviews': {'nodes': [{'author': None}] + [{'author': {'login': login}} for login in logins]}}], 'pageInfo': {'hasNextPage': more, 'endCursor': cursor}}}}})
        config = {'contributors': [{'login':'Alice','contributions':['code']}, {'login':'dependabot[bot]','contributions':['code']}]}
        with patch.object(self.tool, 'run', side_effect=[page(['ALICE'], True, 'cursor1'), page(['unknown','dependabot[bot]'], False, None)]) as run:
            self.assertTrue(self.tool.refresh_review_badges(config))
            self.assertEqual(run.call_count, 2)
            self.assertIn('cursor=cursor1', run.call_args.args[0])
        self.assertEqual(config['contributors'][0]['contributions'], ['code','review'])
        self.assertEqual(config['contributors'][1]['contributions'], ['code'])

    def test_new_contributors_skip_bots_and_case_duplicates(self):
        config = {'contributors': [{'login': 'Alice', 'contributions':['doc']}]}
        remote = [{'login':'ALICE'}, {'login':'dependabot[bot]'}, {'login':'new','avatar_url':'https://example.test/avatar'}, {'login':'new'}, {'login':''}]
        with patch.object(self.tool, 'fetch_current_contributors', return_value=remote):
            self.assertTrue(self.tool.merge_new_contributors(config))
            self.assertFalse(self.tool.merge_new_contributors(config))
        self.assertEqual([c['login'] for c in config['contributors']], ['Alice','new'])

    def test_main_preserves_success_exit_and_exception_failures(self):
        for changed in [False, True]:
            with tempfile.TemporaryDirectory() as tmp:
                target = Path(tmp)/'contributors.json'
                with patch.object(self.tool, 'CONTRIBUTORSRC', target), patch.object(self.tool, 'load_contributors', return_value={'contributors':[]}), patch.object(self.tool, 'merge_new_contributors', return_value=changed), patch.object(self.tool, 'refresh_path_badges', return_value=False) as paths, patch.object(self.tool, 'refresh_review_badges', return_value=False) as reviews, patch.object(self.tool, 'update_readme') as readme, contextlib.redirect_stdout(io.StringIO()):
                    self.assertIn(self.tool.main(), (None, 0))
                    paths.assert_called_once()
                    reviews.assert_called_once()
                    self.assertEqual(readme.call_count, int(changed))
                    self.assertEqual(target.exists(), changed)
        with patch.object(self.tool, 'load_contributors', side_effect=RuntimeError('failed')):
            with self.assertRaisesRegex(RuntimeError, 'failed'):
                self.tool.main()


if __name__ == '__main__':
    unittest.main()
