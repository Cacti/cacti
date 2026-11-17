#!/usr/bin/env python3
"""Regression coverage for reading hostile PR content without executing it."""

import importlib.util
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[2] / ".github/scripts/collect_security_review.py"
SPEC = importlib.util.spec_from_file_location("collector", SCRIPT)
collector = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(collector)


class CollectionTests(unittest.TestCase):
    def test_head_scripts_and_symlinks_are_data(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            def git(*args):
                return subprocess.check_output(["git", *args], cwd=root).decode().strip()
            git("init", "-q")
            git("config", "user.email", "test@example.invalid")
            git("config", "user.name", "Test")
            git("config", "commit.gpgsign", "false")
            (root / "lib").mkdir()
            (root / "lib/auth.php").write_text("<?php // trusted base\n")
            (root / ".gitattributes").write_text("*.php diff=unsafe\n")
            git("config", "diff.unsafe.command", f"touch {root / 'executed'}")
            git("config", "diff.unsafe.textconv", f"touch {root / 'executed'}")
            git("add", ".")
            git("-c", "commit.gpgsign=false", "commit", "-qm", "base")
            base = git("rev-parse", "HEAD")
            marker = root / "executed"
            (root / "head.py").write_text(f"open({str(marker)!r}, 'w').write('unsafe')\n")
            (root / "lib/auth.php").write_text("<?php system($_GET['command']);\n")
            (root / "-option\nname.php").write_text("<?php exec($_POST['input']);\n")
            (root / "linked.php").symlink_to("/nonexistent/private/secret")
            git("add", ".")
            git("-c", "commit.gpgsign=false", "commit", "-qm", "head")
            head = git("rev-parse", "HEAD")
            git("checkout", "-q", base)
            previous = Path.cwd()
            try:
                os.chdir(root)
                collector.collect(base, head, root / "output")
            finally:
                os.chdir(previous)
            self.assertFalse(marker.exists())
            self.assertFalse((root / "head.py").exists())
            self.assertIn("system($_GET", (root / "output/pr.diff").read_text())
            self.assertIn("-option\\nname.php", (root / "output/grep_hits.txt").read_text())
            hotspots = (root / "output/hotspot_contents.txt").read_text()
            self.assertIn("trusted base", hotspots)
            self.assertNotIn("system($_GET", hotspots)

    def test_rejects_revision_option_injection(self):
        with patch.object(collector, "git") as git:
            with self.assertRaises(ValueError):
                collector.collect("--output=/tmp/executed", "a" * 40, Path("/tmp"))
            git.assert_not_called()

    def test_excessive_input_is_truncated_with_a_marker(self):
        result = subprocess.CompletedProcess([], 0, stdout=b"x" * 11, stderr=b"")
        with patch.object(collector, "MAX_BYTES", 10), patch.object(collector.subprocess, "run", return_value=result):
            output = collector.git("show", "a" * 40)
        self.assertTrue(output.startswith(b"x" * 10))
        self.assertIn(b"[... truncated ...]", output)

    def test_pathological_line_is_bounded(self):
        # Repeated opening tokens made the SQL concatenation regex quadratic.
        source = (b'"SELECT ' * 200_000) + b"\n"
        started = time.monotonic()
        hits = collector.match_lines("lib/hostile.php", source)
        self.assertLess(time.monotonic() - started, 5)
        self.assertTrue(all(len(hit) < collector.MAX_LINE + 200 for hit in hits))

    def test_policy_rejects_privileged_head_checkout(self):
        repository = SCRIPT.parents[2]
        workflow = (repository / ".github/workflows/security-review.yml").read_text()
        policy = (repository / ".github/scripts/check-workflow-policy.py").read_text()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            scripts = root / ".github/scripts"
            scripts.mkdir(parents=True)
            (scripts / "check-workflow-policy.py").write_text(policy)
            workflows = root / ".github/workflows"
            workflows.mkdir()
            path = workflows / "security-review.yml"
            command = [sys.executable, str(scripts / "check-workflow-policy.py"),
                       "--workflows", ".github/workflows/security-review.yml"]
            path.write_text(workflow)
            self.assertEqual(subprocess.run(command, capture_output=True).returncode, 0)
            path.write_text(workflow.replace(
                "ref: ${{ github.sha }}",
                "ref: ${{ github.event.pull_request.head.sha }}",
            ))
            result = subprocess.run(command, capture_output=True, text=True)
            self.assertEqual(result.returncode, 1)
            self.assertIn("trusted base SHA", result.stdout)


if __name__ == "__main__":
    unittest.main()
