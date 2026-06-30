#!/usr/bin/env python3
"""Enforce workflow hygiene policy for selected GitHub Actions workflows."""

from __future__ import annotations

import argparse
import re
from pathlib import Path

import yaml


PINNED_REF_RE = re.compile(r"^[0-9a-f]{40}$")
CURL_PIPE_RE = re.compile(r"curl\b[^\n|]*\|\s*(?:sudo\s+)?(?:/(?:usr/)?bin/)?(?:sh|bash)\b")
STRICT_LINE = "set -euo pipefail"
DEFAULT_GLOB = ".github/workflows/*"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--workflows",
        nargs="*",
        default=[],
        help="Explicit workflow file paths to validate (relative to repo root).",
    )
    return parser.parse_args()


def normalize_steps(job: dict) -> list[dict]:
    steps = job.get("steps")
    return steps if isinstance(steps, list) else []


def check_uses(path: str, step_name: str, uses_value: str, violations: list[str]) -> None:
    # Local composite actions (./...) ship in-repo and need no pin.
    if uses_value.startswith("./"):
        return

    # Container actions must be pinned to an immutable @sha256: digest, not a tag.
    if uses_value.startswith("docker://"):
        if "@sha256:" not in uses_value:
            violations.append(f"{path}:{step_name}: docker:// reference must be pinned by @sha256 digest: {uses_value}")
        return

    if "@" not in uses_value:
        violations.append(f"{path}:{step_name}: uses reference is missing @ref: {uses_value}")
        return

    ref = uses_value.split("@", 1)[1]
    if not PINNED_REF_RE.fullmatch(ref):
        violations.append(f"{path}:{step_name}: action ref must be a pinned SHA: {uses_value}")


def check_run(path: str, step_name: str, run_value: str, violations: list[str]) -> None:
    lines = [ln.strip() for ln in run_value.splitlines() if ln.strip()]
    if not lines:
        return

    if len(run_value.splitlines()) > 1 and lines[0] != STRICT_LINE:
        violations.append(f"{path}:{step_name}: multiline run must start with '{STRICT_LINE}'")

    if CURL_PIPE_RE.search(run_value):
        violations.append(f"{path}:{step_name}: curl|sh is disallowed")


def resolve_workflow_files(root: Path, explicit_paths: list[str]) -> list[Path]:
    if explicit_paths:
        files: list[Path] = []
        for rel in explicit_paths:
            path = (root / rel).resolve()
            if not path.is_file():
                print(f"Error: Workflow file not found: {rel}")
                raise SystemExit(1)
            files.append(path)
        return sorted(files)
    return sorted(
        path for path in root.glob(DEFAULT_GLOB) if path.suffix in (".yml", ".yaml")
    )


def main() -> int:
    args = parse_args()
    root = Path(__file__).resolve().parents[2]
    workflow_files = resolve_workflow_files(root, args.workflows)
    violations: list[str] = []

    if not workflow_files:
        print("No workflow files found to validate.")
        return 0

    for wf in workflow_files:
        rel = str(wf.relative_to(root))
        try:
            doc = yaml.safe_load(wf.read_text(encoding="utf-8"))
        except Exception as exc:  # pragma: no cover
            violations.append(f"{rel}: failed to parse YAML: {exc}")
            continue

        jobs = doc.get("jobs", {}) if isinstance(doc, dict) else {}
        if not isinstance(jobs, dict):
            continue

        for job_name, job in jobs.items():
            if not isinstance(job, dict):
                continue

            # Reusable-workflow calls live at the job level (jobs.<id>.uses) and
            # must be pinned just like step actions.
            job_uses = job.get("uses")
            if isinstance(job_uses, str):
                check_uses(rel, f"{job_name} (job)", job_uses.strip(), violations)

            for idx, step in enumerate(normalize_steps(job), start=1):
                if not isinstance(step, dict):
                    continue
                step_name = str(step.get("name", f"{job_name}.step{idx}"))

                uses_value = step.get("uses")
                if isinstance(uses_value, str):
                    check_uses(rel, step_name, uses_value.strip(), violations)

                run_value = step.get("run")
                if isinstance(run_value, str):
                    check_run(rel, step_name, run_value, violations)

    if violations:
        print("Workflow policy violations:")
        for violation in violations:
            print(f"- {violation}")
        return 1

    print("Workflow policy checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
