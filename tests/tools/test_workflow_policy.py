"""Exercise the workflow policy's credentialed checkout allowance."""

from __future__ import annotations

import contextlib
import importlib.util
import io
import sys
import unittest
from pathlib import Path
from unittest import mock

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / ".github/scripts/check-workflow-policy.py"
PUBLISHER = ".github/workflows/released-dependency-audit.yml"

sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location("check_workflow_policy", SCRIPT)
assert SPEC and SPEC.loader
POLICY = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = POLICY
SPEC.loader.exec_module(POLICY)


def run_policy(*workflows: str) -> tuple[int, str]:
    output = io.StringIO()
    argv = ["check-workflow-policy.py", "--workflows", *workflows]
    with mock.patch.object(sys, "argv", argv), contextlib.redirect_stdout(output):
        status = POLICY.main()
    return status, output.getvalue()


class WorkflowPolicyTest(unittest.TestCase):
    def test_publisher_checkout_may_keep_credentials_for_its_push(self) -> None:
        status, output = run_policy(PUBLISHER)

        self.assertEqual(0, status, output)

    def test_unlisted_workflow_with_a_credentialed_checkout_is_rejected(self) -> None:
        allowed = POLICY.CHECKOUT_WRITE_WORKFLOWS - {PUBLISHER}

        with mock.patch.object(POLICY, "CHECKOUT_WRITE_WORKFLOWS", allowed):
            status, output = run_policy(PUBLISHER)

        self.assertEqual(1, status)
        self.assertIn(
            f"{PUBLISHER}:Checkout released branch for publication: "
            "read-only checkout must set persist-credentials: false",
            output,
        )


if __name__ == "__main__":
    unittest.main()
