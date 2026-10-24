"""Keep contributor badge refreshes from leaking or dropping badges.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import importlib.util
import json
from pathlib import Path
import unittest
from unittest import mock

SCRIPT = Path(__file__).resolve().parents[2] / '.github/scripts/update-contributors.py'
SPEC = importlib.util.spec_from_file_location('update_contributors', SCRIPT)
TOOL = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(TOOL)


def bucket(*paths):
    return {'emails': set(), 'paths': set(paths), 'messages': ['change']}


class AliasMergeTest(unittest.TestCase):
    def test_alias_merge_does_not_leak_into_shared_author_buckets(self):
        alice = bucket('lib/functions.php')
        by_author = {'alice': alice, 'alice@example.com': alice,
                     'alice smith': bucket('tests/Unit/FooTest.php')}
        config = {'contributors': [
            {'login': 'alice', 'name': 'alice', 'contributions': ['code']},
            {'login': 'bob', 'name': 'bob', 'contributions': ['code']},
        ]}
        aliases = {'alice': ['Alice Smith'], 'bob': ['alice@example.com']}
        with mock.patch.object(TOOL, 'git_log_by_author', return_value=by_author), \
                mock.patch.object(TOOL, 'FOUNDER_ALIASES', aliases):
            self.assertTrue(TOOL.refresh_path_badges(config))
        self.assertEqual(config['contributors'][0]['contributions'], ['code', 'test'])
        self.assertEqual(config['contributors'][1]['contributions'], ['code'])
        self.assertEqual(alice['paths'], {'lib/functions.php'})


class BadgeOrderTest(unittest.TestCase):
    def test_badge_types_outside_badge_order_are_kept(self):
        self.assertEqual(TOOL.ordered_badges(['video', 'code'], {'video', 'code', 'review'}),
                         ['code', 'review', 'video'])

    def test_review_refresh_keeps_curated_badges(self):
        config = {'contributors': [{'login': 'carol', 'contributions': ['code', 'video']}]}
        page = {'data': {'repository': {'pullRequests': {
            'pageInfo': {'hasNextPage': False, 'endCursor': None},
            'nodes': [{'reviews': {'nodes': [{'author': {'login': 'Carol'}}]}}],
        }}}}
        with mock.patch.object(TOOL, 'run', return_value=json.dumps(page)):
            self.assertTrue(TOOL.refresh_review_badges(config))
        self.assertEqual(config['contributors'][0]['contributions'], ['code', 'review', 'video'])


if __name__ == '__main__':
    unittest.main()
