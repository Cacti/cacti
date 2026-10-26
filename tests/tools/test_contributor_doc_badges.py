"""Preserve documentation badges for directory and Markdown path matches.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import importlib.util
from pathlib import Path
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / '.github/scripts/update-contributors.py'
SPEC = importlib.util.spec_from_file_location('contributor_badges', SCRIPT)
TOOL = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(TOOL)


class ContributorDocumentationBadgesTest(unittest.TestCase):
    def test_documentation_paths_and_markdown_files_keep_the_doc_badge(self):
        for path in ['docs/architecture.txt', 'README.md', 'lib/README.md']:
            with self.subTest(path=path):
                self.assertIn('doc', TOOL.badges_for({'paths': {path}, 'messages': []}))

    def test_non_documentation_paths_do_not_gain_the_doc_badge(self):
        self.assertNotIn('doc', TOOL.badges_for({'paths': {'lib/functions.php'}, 'messages': []}))


if __name__ == '__main__':
    unittest.main()
