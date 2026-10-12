"""Exercise actual Git changes, including rename/deletion and missing bases."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
from ci_scope import select

class ScopeTests(unittest.TestCase):
    def test_git_change_selection(self):
        with tempfile.TemporaryDirectory() as path:
            def git(*args):
                return subprocess.check_output(["git", "-C", path, *args], text=True).strip()
            git("init", "-q")
            git("config", "user.email", "test@example.invalid")
            git("config", "user.name", "Scope test")
            root = Path(path)
            (root / "lib").mkdir()
            (root / "lib/poller.php").write_text("baseline")
            git("add", ".")
            git("commit", "-qm", "baseline")
            base = git("rev-parse", "HEAD")
            (root / "README.md").write_text("documentation")
            git("add", ".")
            git("commit", "-qm", "docs")
            docs = git("rev-parse", "HEAD")
            with patch("ci_scope.subprocess.run", wraps=subprocess.run) as run:
                old = os.getcwd()
                try:
                    os.chdir(path)
                    self.assertEqual(select("pull_request", base, docs)["run"], "false")
                    git("mv", "lib/poller.php", "archived.txt")
                    git("commit", "-qm", "move production code")
                    self.assertEqual(select("pull_request", docs, git("rev-parse", "HEAD"))["run"], "true")
                    self.assertEqual(select("push", "f" * 40, docs)["run"], "true")
                finally:
                    os.chdir(old)
                self.assertGreaterEqual(run.call_count, 3)

    def test_schedule_covers_both_branches(self):
        result = select("schedule", "", "a" * 40)
        self.assertEqual(result["run"], "true")
        self.assertEqual(result["full"], "true")
        self.assertEqual(result["refs"], '["develop", "1.2.x"]')

    def test_manual_quick_and_new_branch(self):
        with patch.dict(os.environ, {"FULL": "false"}):
            self.assertEqual(select("workflow_dispatch", "", "a" * 40)["full"], "false")
        self.assertEqual(select("push", "0" * 40, "a" * 40)["run"], "true")
        self.assertEqual(select("release", "", "a" * 40)["full"], "true")

if __name__ == "__main__":
    unittest.main()
