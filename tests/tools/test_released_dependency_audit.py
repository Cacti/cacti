"""Exercise the released-branch audit and remediation contracts offline."""

from __future__ import annotations

import importlib.util
import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/released-dependency-audit.yml"
REMEDIATION_SCRIPT = ROOT / ".github/scripts/released_dependency_remediation.py"

sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location(
    "released_dependency_remediation", REMEDIATION_SCRIPT
)
assert SPEC and SPEC.loader
REMEDIATION = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = REMEDIATION
SPEC.loader.exec_module(REMEDIATION)


class ReleasedDependencyAuditTest(unittest.TestCase):
    def setUp(self) -> None:
        self.workflow = yaml.safe_load(WORKFLOW.read_text(encoding="utf-8"))

    def audit(self, ecosystem, report, status=0):
        command = next(
            step["run"]
            for step in self.workflow["jobs"]["audit"]["steps"]
            if step["name"] == "Audit committed dependencies"
        )
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            executable = root / ecosystem
            executable.write_text(
                '#!/bin/sh\ncat "$FIXTURE_REPORT"\nexit "$FIXTURE_STATUS"\n',
                encoding="utf-8",
            )
            executable.chmod(0o755)
            fixture = root / "fixture.json"
            fixture.write_text(
                report if isinstance(report, str) else json.dumps(report),
                encoding="utf-8",
            )
            output = root / "github-output"
            result = subprocess.run(
                ["bash", "-c", command],
                check=False,
                text=True,
                capture_output=True,
                env={
                    **os.environ,
                    "PATH": f'{root}{os.pathsep}{os.environ["PATH"]}',
                    "PACKAGE_ECOSYSTEM": ecosystem,
                    "RUNNER_TEMP": directory,
                    "GITHUB_OUTPUT": str(output),
                    "FIXTURE_REPORT": str(fixture),
                    "FIXTURE_STATUS": str(status),
                },
            )
            audit_report = root / "dependency-audit.json"
            self.assertEqual(audit_report.read_text(), fixture.read_text())
            return result

    def test_clean_locks_pass(self):
        reports = [
            ("composer", {"advisories": [], "abandoned": {}}),
            ("npm", {"metadata": {"vulnerabilities": {"total": 0}}}),
        ]
        for ecosystem, report in reports:
            with self.subTest(ecosystem=ecosystem):
                result = self.audit(ecosystem, report)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertNotIn("::warning", result.stdout)

    def test_composer_advisories_are_reported(self):
        report = {
            "advisories": {"vendor/package": [{"title": "fixture"}]},
            "abandoned": {},
        }
        result = self.audit("composer", report, 1)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("1 advisory or abandoned-package records", result.stdout)

    def test_abandoned_packages_are_reported(self):
        report = {"advisories": [], "abandoned": {"vendor/package": None}}
        result = self.audit("composer", report, 2)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("1 advisory or abandoned-package records", result.stdout)

    def test_npm_advisories_are_reported(self):
        report = {"metadata": {"vulnerabilities": {"total": 3}}}
        result = self.audit("npm", report, 1)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("3 advisory or abandoned-package records", result.stdout)

    def test_invalid_reports_fail(self):
        for ecosystem in ["composer", "npm"]:
            for report in ["registry unavailable", {"error": {"message": "fixture"}}]:
                with self.subTest(ecosystem=ecosystem, report=report):
                    self.assertNotEqual(self.audit(ecosystem, report, 1).returncode, 0)

    def test_tool_failures_without_findings_fail(self):
        reports = [
            ("composer", {"advisories": [], "abandoned": {}}),
            ("npm", {"metadata": {"vulnerabilities": {"total": 0}}}),
        ]
        for ecosystem, report in reports:
            with self.subTest(ecosystem=ecosystem):
                self.assertEqual(self.audit(ecosystem, report, 42).returncode, 42)

    def test_workflow_is_daily_and_separates_write_permissions(self):
        triggers = self.workflow.get("on", self.workflow.get(True))
        self.assertEqual(triggers["schedule"], [{"cron": "17 6 * * *"}])
        self.assertEqual(self.workflow["permissions"], {"contents": "read"})
        self.assertEqual(
            self.workflow["jobs"]["audit"]["permissions"],
            {"contents": "read"},
        )
        self.assertEqual(
            self.workflow["jobs"]["publish-remediation"]["permissions"],
            {"contents": "write", "pull-requests": "write"},
        )
        publisher = self.workflow["jobs"]["publish-remediation"]
        self.assertIn("github.repository == 'Cacti/cacti'", publisher["if"])

    def test_checkout_credentials_are_not_persisted_and_publisher_authenticates(self):
        for job in self.workflow["jobs"].values():
            for step in job["steps"]:
                if step.get("uses", "").startswith("actions/checkout@"):
                    self.assertIs(step["with"]["persist-credentials"], False)

        publisher = self.workflow["jobs"]["publish-remediation"]["steps"]
        publish = next(step for step in publisher if " publish " in step.get("run", ""))
        script = publish["run"]
        self.assertEqual(publish["env"]["GH_TOKEN"], "${{ secrets.GITHUB_TOKEN }}")
        # the helper is set up inside the publishing step and removed on exit, so no other step holds it
        self.assertLess(script.index("trap "), script.index("gh auth setup-git"))
        self.assertLess(script.index("gh auth setup-git"), script.index(" publish "))
        self.assertIn("--unset-all credential.https://github.com.helper", script)
        self.assertEqual(sum("gh auth setup-git" in step.get("run", "") for step in publisher), 1)

    def test_cli_download_is_versioned_and_hash_pinned(self):
        environment = self.workflow["env"]
        self.assertRegex(environment["DEPENDABOT_CLI_VERSION"], r"^\d+\.\d+\.\d+$")
        self.assertRegex(environment["DEPENDABOT_CLI_SHA256"], r"^[0-9a-f]{64}$")

    def test_composer_setup_uses_released_manifest_and_normalized_lockfile(self):
        audit = self.workflow["jobs"]["audit"]
        setup = next(step for step in audit["steps"] if step["name"] == "Set up Cacti Composer toolchain")
        for entry in audit["strategy"]["matrix"]["include"]:
            if entry["ecosystem"] != "composer":
                continue
            cache_path = setup["with"]["cache-dependency-path"].replace("${{ matrix.lockfile }}", entry["lockfile"])
            self.assertNotIn("/./", cache_path)
            self.assertEqual(Path(cache_path), Path("released") / entry["workdir"] / "composer.lock")
            with tempfile.TemporaryDirectory() as directory:
                root = Path(directory)
                manifest = root / "released" / entry["workdir"] / "composer.json"
                manifest.parent.mkdir(parents=True, exist_ok=True)
                manifest.write_text('{}\n', encoding="utf-8")
                composer = setup["env"]["COMPOSER"].replace("${{ github.workspace }}", str(root)).replace("${{ matrix.workdir }}", entry["workdir"])
                result = subprocess.run(["composer", "config", "cache-files-dir"], cwd=root,
                                        env={**os.environ, "COMPOSER": composer}, capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertTrue(Path(result.stdout.strip()).is_absolute())


class ReleasedDependencyRemediationTest(unittest.TestCase):
    def test_builds_composer_security_job(self):
        report = {
            "advisories": {
                "vendor/package": [
                    {"affectedVersions": "<1.2.3"},
                    {"affectedVersions": ">=2.0,<2.1"},
                ]
            },
            "abandoned": {},
        }
        document = REMEDIATION.build_job_document(
            ecosystem="composer",
            directory="/tests",
            report=report,
            repository="Cacti/cacti",
            commit="a" * 40,
        )
        job = document["job"]
        self.assertEqual(job["package-manager"], "composer")
        self.assertEqual(job["dependencies"], ["vendor/package"])
        self.assertEqual(job["source"]["directory"], "/tests")
        self.assertTrue(job["security-updates-only"])
        self.assertEqual(
            job["security-advisories"][0]["affected-versions"],
            ["<1.2.3", ">=2.0,<2.1"],
        )

    def test_builds_npm_security_job_for_transitive_dependency(self):
        report = {
            "vulnerabilities": {
                "transitive-package": {
                    "isDirect": False,
                    "range": ">=1.0.0 <1.2.4",
                }
            },
            "metadata": {"vulnerabilities": {"total": 1}},
        }
        document = REMEDIATION.build_job_document(
            ecosystem="npm",
            directory="/",
            report=report,
            repository="Cacti/cacti",
            commit="b" * 40,
        )
        job = document["job"]
        self.assertEqual(job["package-manager"], "npm_and_yarn")
        self.assertEqual(job["dependencies"], ["transitive-package"])
        self.assertEqual(
            job["security-advisories"][0]["affected-versions"],
            [">=1.0.0 <1.2.4"],
        )

    def test_rejects_missing_advisory_ranges(self):
        with self.assertRaises(REMEDIATION.RemediationError):
            REMEDIATION.build_job_document(
                ecosystem="npm",
                directory="/",
                report={"vulnerabilities": {"package": {"range": ""}}},
                repository="Cacti/cacti",
                commit="c" * 40,
            )

    def test_materializes_only_manifest_files(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "repo"
            root.mkdir()
            self.git(root, "init", "--quiet")
            self.git(root, "config", "user.name", "Fixture")
            self.git(root, "config", "user.email", "fixture@example.com")
            self.git(root, "config", "commit.gpgsign", "false")
            manifest = root / "tests/e2e/package.json"
            lockfile = root / "tests/e2e/package-lock.json"
            manifest.parent.mkdir(parents=True)
            manifest.write_text('{"devDependencies":{"fixture":"1.0.0"}}\n')
            lockfile.write_text('{"lockfileVersion":3,"packages":{}}\n')
            self.git(root, "add", ".")
            self.git(root, "commit", "--quiet", "-m", "fixture")
            base_commit = self.git(root, "rev-parse", "HEAD").strip()

            event = self.create_event(
                directory="/tests/e2e",
                name="fixture",
                files={
                    "package.json": '{"devDependencies":{"fixture":"1.0.1"}}\n',
                    "package-lock.json": '{"lockfileVersion":3,"packages":{"fixture":"1.0.1"}}\n',
                },
            )
            results = Path(directory) / "results.jsonl"
            results.write_text(json.dumps(event) + "\n")
            output = Path(directory) / "output"

            count = REMEDIATION.materialize_results(
                results=results,
                repository_root=root,
                base_commit=base_commit,
                ecosystem="npm",
                directory="/tests/e2e",
                output_root=output,
            )

            self.assertEqual(count, 1)
            metadata_path = next(output.rglob("metadata.json"))
            metadata = json.loads(metadata_path.read_text())
            self.assertEqual(
                metadata["files"],
                ["tests/e2e/package-lock.json", "tests/e2e/package.json"],
            )
            patch = metadata_path.with_name("changes.patch")
            self.assertTrue(patch.read_bytes().startswith(b"diff --git "))
            self.git(root, "apply", "--check", str(patch))

    def test_rejects_generated_path_traversal(self):
        data = self.create_event(
            directory="/tests/e2e",
            name="fixture",
            files={"../../owned": "unsafe\n"},
        )["data"]
        with self.assertRaises(REMEDIATION.RemediationError):
            REMEDIATION.event_file_path(
                data["updated-dependency-files"][0],
                "/tests/e2e",
            )

    def test_root_npm_policy_includes_shipped_assets(self):
        _, policy = REMEDIATION.policy_for("npm", "/")
        self.assertIn("package-lock.json", policy.allowed_files)
        self.assertIn("include/js/purify.js", policy.allowed_files)
        self.assertIn("include/js/billboard.js", policy.allowed_files)
        self.assertNotIn("build/sync-js.mjs", policy.allowed_files)

    def test_draft_pr_copy_names_the_blind_spot_and_test_gate(self):
        metadata = {
            "ecosystem": "npm",
            "directory": "/",
            "base_commit": "d" * 40,
            "dependencies": ["fixture"],
        }
        title = REMEDIATION.pull_request_title(metadata)
        body = REMEDIATION.pull_request_body(
            metadata,
            issue=8577,
            run_url="https://github.example/actions/runs/1",
        )
        self.assertIn("security blind spot", title)
        self.assertIn("dependency-security blind spot", body)
        self.assertIn("Approve the draft's queued workflow runs", body)
        self.assertIn("include/js/", body)
        self.assertIn("#8577", body)

    @staticmethod
    def git(root: Path, *arguments: str) -> str:
        return subprocess.run(
            ["git", *arguments],
            cwd=root,
            check=True,
            capture_output=True,
            text=True,
        ).stdout

    @staticmethod
    def create_event(*, directory: str, name: str, files: dict[str, str]) -> dict:
        return {
            "type": "create_pull_request",
            "data": {
                "dependencies": [{"name": name}],
                "updated-dependency-files": [
                    {
                        "directory": directory,
                        "name": file_name,
                        "deleted": False,
                        "content": content,
                    }
                    for file_name, content in files.items()
                ],
            },
        }


if __name__ == "__main__":
    unittest.main()
