#!/usr/bin/env python3
# Copyright (C) 2004-2026 The Cacti Group
# GPL-2.0-or-later
"""Prepare and publish isolated dependency remediations for Cacti 1.2.x."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import tempfile
from dataclasses import dataclass
from pathlib import Path, PurePosixPath

SHA_RE = re.compile(r"^[0-9a-f]{40}$")
EVENT_ID_RE = re.compile(r"^[0-9a-f]{16}$")
DEPENDENCY_RE = re.compile(r"^[A-Za-z0-9@._/+:-]{1,214}$")
REPOSITORY_RE = re.compile(r"^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$")
MAX_PATCH_BYTES = 25 * 1024 * 1024


class RemediationError(RuntimeError):
    """The audit or generated remediation violated its safety contract."""


@dataclass(frozen=True)
class Policy:
    """Allowed files and Dependabot package manager for one manifest."""

    package_manager: str
    manifest_files: frozenset[str]
    generated_files: frozenset[str] = frozenset()

    @property
    def allowed_files(self) -> frozenset[str]:
        return self.manifest_files | self.generated_files


ROOT_NPM_ASSETS = frozenset(
    {
        "include/js/billboard.js",
        "include/js/d3.js",
        "include/js/jquery.tablesorter.js",
        "include/js/jquery.tablesorter.pager.js",
        "include/js/jquery.tablesorter.widgets.js",
        "include/js/jstree.js",
        "include/js/pace.js",
        "include/js/purify.js",
        "include/js/purify.js.map",
        "include/js/screenfull.js",
        "include/js/select2.js",
    }
)

POLICIES = {
    ("composer", "/"): Policy(
        "composer", frozenset({"composer.json", "composer.lock"})
    ),
    ("composer", "/tests"): Policy(
        "composer",
        frozenset({"tests/composer.json", "tests/composer.lock"}),
    ),
    ("npm", "/"): Policy(
        "npm_and_yarn",
        frozenset({"package.json", "package-lock.json"}),
        ROOT_NPM_ASSETS,
    ),
    ("npm", "/tests/e2e"): Policy(
        "npm_and_yarn",
        frozenset({"tests/e2e/package.json", "tests/e2e/package-lock.json"}),
    ),
}


def normalize_directory(directory: str) -> str:
    """Return an absolute-looking repository directory without traversal."""
    directory = directory.strip()
    if directory in {"", ".", "/"}:
        return "/"

    path = PurePosixPath(directory.lstrip("/"))
    if not path.parts or any(part in {"", ".", ".."} for part in path.parts):
        raise RemediationError(f"unsafe manifest directory: {directory!r}")
    return "/" + path.as_posix()


def policy_for(ecosystem: str, directory: str) -> tuple[str, Policy]:
    """Resolve and validate a supported ecosystem/directory pair."""
    normalized = normalize_directory(directory)
    try:
        return normalized, POLICIES[(ecosystem, normalized)]
    except KeyError as error:
        raise RemediationError(
            f"unsupported released dependency manifest: {ecosystem}:{normalized}"
        ) from error


def load_json(path: Path) -> object:
    """Load a JSON document with a useful contract error."""
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise RemediationError(f"invalid JSON document {path}: {error}") from error


def require_mapping(value: object, label: str) -> dict:
    """Return a JSON object or reject the malformed report."""
    if not isinstance(value, dict):
        raise RemediationError(f"{label} must be a JSON object")
    return value


def validate_dependency_name(name: object) -> str:
    """Reject control characters and values unsafe for branch/PR metadata."""
    if not isinstance(name, str) or not DEPENDENCY_RE.fullmatch(name):
        raise RemediationError(f"invalid dependency name: {name!r}")
    return name


def composer_security_advisories(report: dict) -> list[dict]:
    """Translate Composer audit advisories into a Dependabot job."""
    advisories = report.get("advisories")
    if advisories == []:
        return []
    advisories = require_mapping(advisories, "Composer advisories")

    result = []
    for dependency in sorted(advisories):
        name = validate_dependency_name(dependency)
        records = advisories[dependency]
        if not isinstance(records, list) or not records:
            raise RemediationError(f"Composer advisory list is invalid for {name}")

        ranges = set()
        for record in records:
            record = require_mapping(record, f"Composer advisory for {name}")
            affected = record.get("affectedVersions")
            if not isinstance(affected, str) or not affected.strip():
                raise RemediationError(f"Composer advisory range is missing for {name}")
            ranges.add(affected.strip())

        result.append(
            {
                "dependency-name": name,
                "affected-versions": sorted(ranges),
                "patched-versions": [],
                "unaffected-versions": [],
            }
        )
    return result


def npm_security_advisories(report: dict) -> list[dict]:
    """Translate npm audit vulnerabilities into a Dependabot job."""
    vulnerabilities = require_mapping(
        report.get("vulnerabilities"), "npm vulnerabilities"
    )
    result = []
    for dependency in sorted(vulnerabilities):
        name = validate_dependency_name(dependency)
        record = require_mapping(
            vulnerabilities[dependency], f"npm vulnerability for {name}"
        )
        affected = record.get("range")
        if not isinstance(affected, str) or not affected.strip():
            raise RemediationError(f"npm vulnerability range is missing for {name}")
        result.append(
            {
                "dependency-name": name,
                "affected-versions": [affected.strip()],
                "patched-versions": [],
                "unaffected-versions": [],
            }
        )
    return result


def security_advisories(ecosystem: str, report: object) -> list[dict]:
    """Extract public security advisory ranges from an ecosystem audit."""
    report = require_mapping(report, f"{ecosystem} audit report")
    if ecosystem == "composer":
        return composer_security_advisories(report)
    if ecosystem == "npm":
        return npm_security_advisories(report)
    raise RemediationError(f"unsupported audit ecosystem: {ecosystem}")


def build_job_document(
    *,
    ecosystem: str,
    directory: str,
    report: object,
    repository: str,
    commit: str,
) -> dict:
    """Build a security-only Dependabot CLI job for the released branch."""
    directory, policy = policy_for(ecosystem, directory)
    if not REPOSITORY_RE.fullmatch(repository):
        raise RemediationError(f"invalid GitHub repository: {repository!r}")
    if not SHA_RE.fullmatch(commit):
        raise RemediationError(f"invalid released-branch commit: {commit!r}")

    advisories = security_advisories(ecosystem, report)
    return {
        "job": {
            "package-manager": policy.package_manager,
            # Security jobs choose the minimum non-vulnerable release. Keep
            # majors available when no in-series fix exists; the result stays
            # draft until maintainers complete compatibility testing.
            "allowed-updates": [{"update-type": "all"}],
            "dependencies": [item["dependency-name"] for item in advisories],
            "security-advisories": advisories,
            "security-updates-only": True,
            "source": {
                "provider": "github",
                "repo": repository,
                "directory": directory,
                "commit": commit,
            },
        }
    }


def run_command(
    arguments: list[str],
    *,
    cwd: Path,
    capture_output: bool = False,
    text: bool = True,
    check: bool = True,
    env: dict[str, str] | None = None,
) -> subprocess.CompletedProcess:
    """Run a command without a shell so generated metadata cannot execute code."""
    return subprocess.run(
        arguments,
        cwd=cwd,
        capture_output=capture_output,
        text=text,
        check=check,
        env=env,
    )


def event_dependencies(data: dict) -> list[str]:
    """Return the validated dependency names carried by a create event."""
    dependencies = data.get("dependencies")
    if not isinstance(dependencies, list) or not dependencies:
        raise RemediationError("Dependabot create event has no dependencies")

    names = []
    for dependency in dependencies:
        dependency = require_mapping(dependency, "Dependabot dependency")
        names.append(validate_dependency_name(dependency.get("name")))
    return sorted(set(names))


def event_file_path(file_data: dict, expected_directory: str) -> str:
    """Resolve one Dependabot file entry without allowing path traversal."""
    directory = normalize_directory(file_data.get("directory", ""))
    if directory != expected_directory:
        raise RemediationError(
            f"Dependabot event changed directory {directory}, expected {expected_directory}"
        )

    name = file_data.get("name")
    if not isinstance(name, str):
        raise RemediationError("Dependabot file name must be a string")
    name_path = PurePosixPath(name)
    if name_path.is_absolute() or len(name_path.parts) != 1 or name in {"", ".", ".."}:
        raise RemediationError(f"unsafe Dependabot file name: {name!r}")

    prefix = expected_directory.strip("/")
    return f"{prefix}/{name}" if prefix else name


def write_event_files(
    worktree: Path,
    data: dict,
    *,
    directory: str,
    policy: Policy,
) -> set[str]:
    """Apply only allowlisted dependency files from a Dependabot event."""
    files = data.get("updated-dependency-files")
    if not isinstance(files, list) or not files:
        raise RemediationError("Dependabot create event has no updated files")

    written = set()
    for file_value in files:
        file_data = require_mapping(file_value, "Dependabot updated file")
        path = event_file_path(file_data, directory)
        if path not in policy.manifest_files:
            raise RemediationError(
                f"Dependabot attempted to change disallowed file: {path}"
            )
        if path in written:
            raise RemediationError(f"Dependabot emitted duplicate file: {path}")

        deleted = file_data.get("deleted", False)
        if not isinstance(deleted, bool):
            raise RemediationError(f"invalid deleted flag for {path}")
        path_parts = PurePosixPath(path).parts
        target = worktree.joinpath(*path_parts)
        parent = worktree
        symlinked_parent = False
        for part in path_parts[:-1]:
            parent /= part
            symlinked_parent = symlinked_parent or parent.is_symlink()
        if target.is_symlink() or symlinked_parent:
            raise RemediationError(f"refusing to follow a symlink for {path}")

        if deleted:
            if target.exists():
                target.unlink()
        else:
            content = file_data.get("content")
            if not isinstance(content, str):
                raise RemediationError(f"Dependabot content is missing for {path}")
            target.parent.mkdir(parents=True, exist_ok=True)
            with target.open("w", encoding="utf-8", newline="") as output:
                output.write(content)
        written.add(path)

    return written


def rebuild_root_npm_assets(worktree: Path) -> None:
    """Regenerate shipped JavaScript without running npm lifecycle scripts."""
    environment = {
        **os.environ,
        "CI": "true",
        "npm_config_audit": "false",
        "npm_config_fund": "false",
    }
    run_command(
        ["npm", "ci", "--ignore-scripts", "--no-audit", "--no-fund"],
        cwd=worktree,
        env=environment,
    )
    run_command(
        ["node", "node_modules/patch-package/index.js"],
        cwd=worktree,
        env=environment,
    )
    run_command(["node", "build/sync-js.mjs"], cwd=worktree, env=environment)


def nul_paths(output: bytes) -> set[str]:
    """Decode a NUL-delimited Git path list."""
    return {path.decode("utf-8") for path in output.split(b"\0") if path}


def event_id(ecosystem: str, directory: str, dependencies: list[str]) -> str:
    """Return a deterministic identifier for one remediation stream."""
    payload = json.dumps(
        [ecosystem, directory, dependencies],
        ensure_ascii=True,
        separators=(",", ":"),
    ).encode("utf-8")
    return hashlib.sha256(payload).hexdigest()[:16]


def materialize_event(
    data: dict,
    *,
    repository_root: Path,
    base_commit: str,
    ecosystem: str,
    directory: str,
    output_root: Path,
) -> str:
    """Turn one create event into a reviewed patch artifact."""
    directory, policy = policy_for(ecosystem, directory)
    dependencies = event_dependencies(data)
    identifier = event_id(ecosystem, directory, dependencies)
    event_root = output_root / "events" / identifier
    if event_root.exists():
        raise RemediationError(f"duplicate remediation event: {identifier}")

    with tempfile.TemporaryDirectory(prefix="cacti-remediation-") as temporary:
        worktree = Path(temporary) / "worktree"
        run_command(
            ["git", "worktree", "add", "--detach", str(worktree), base_commit],
            cwd=repository_root,
        )
        try:
            write_event_files(worktree, data, directory=directory, policy=policy)
            if ecosystem == "npm" and directory == "/":
                rebuild_root_npm_assets(worktree)

            unstaged = run_command(
                ["git", "diff", "--name-only", "-z", base_commit, "--"],
                cwd=worktree,
                capture_output=True,
                text=False,
            ).stdout
            unexpected = nul_paths(unstaged) - policy.allowed_files
            if unexpected:
                raise RemediationError(
                    "remediation changed disallowed files: "
                    + ", ".join(sorted(unexpected))
                )

            run_command(
                ["git", "add", "--all", "--", *sorted(policy.allowed_files)],
                cwd=worktree,
            )
            changed_output = run_command(
                ["git", "diff", "--cached", "--name-only", "-z", base_commit, "--"],
                cwd=worktree,
                capture_output=True,
                text=False,
            ).stdout
            changed = nul_paths(changed_output)
            if not changed:
                raise RemediationError(
                    "Dependabot remediation produced no file changes"
                )
            if not changed <= policy.allowed_files:
                raise RemediationError("staged remediation escaped its file allowlist")

            patch = run_command(
                [
                    "git",
                    "diff",
                    "--cached",
                    "--no-ext-diff",
                    "--no-textconv",
                    "--binary",
                    "--full-index",
                    base_commit,
                    "--",
                ],
                cwd=worktree,
                capture_output=True,
                text=False,
            ).stdout
        finally:
            run_command(
                ["git", "worktree", "remove", "--force", str(worktree)],
                cwd=repository_root,
                check=False,
            )

    if not patch or len(patch) > MAX_PATCH_BYTES:
        raise RemediationError("generated remediation patch is empty or too large")
    event_root.mkdir(parents=True)
    (event_root / "changes.patch").write_bytes(patch)
    metadata = {
        "schema": 1,
        "id": identifier,
        "ecosystem": ecosystem,
        "directory": directory,
        "base_commit": base_commit,
        "dependencies": dependencies,
        "files": sorted(changed),
    }
    (event_root / "metadata.json").write_text(
        json.dumps(metadata, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    return identifier


def read_json_lines(path: Path) -> list[dict]:
    """Read the Dependabot CLI JSONL output without accepting malformed lines."""
    events = []
    try:
        lines = path.read_text(encoding="utf-8").splitlines()
    except OSError as error:
        raise RemediationError(
            f"cannot read Dependabot result {path}: {error}"
        ) from error
    for number, line in enumerate(lines, start=1):
        if not line.strip():
            continue
        try:
            value = json.loads(line)
        except json.JSONDecodeError as error:
            raise RemediationError(
                f"invalid Dependabot JSONL line {number}: {error}"
            ) from error
        events.append(require_mapping(value, f"Dependabot event on line {number}"))
    return events


def materialize_results(
    *,
    results: Path,
    repository_root: Path,
    base_commit: str,
    ecosystem: str,
    directory: str,
    output_root: Path,
) -> int:
    """Materialize all create events and record non-sensitive status metadata."""
    if not SHA_RE.fullmatch(base_commit):
        raise RemediationError(f"invalid materialization base commit: {base_commit!r}")
    output_root.mkdir(parents=True, exist_ok=True)
    events = read_json_lines(results)
    created = []
    errors = []
    for event in events:
        event_type = event.get("type")
        if event_type == "create_pull_request":
            data = require_mapping(event.get("data"), "Dependabot create event data")
            created.append(
                materialize_event(
                    data,
                    repository_root=repository_root,
                    base_commit=base_commit,
                    ecosystem=ecosystem,
                    directory=directory,
                    output_root=output_root,
                )
            )
        elif event_type == "record_update_job_error":
            data = require_mapping(event.get("data"), "Dependabot error event data")
            error_type = data.get("error-type")
            if isinstance(error_type, str):
                errors.append(error_type)

    status = {"schema": 1, "created": created, "error_types": sorted(set(errors))}
    (output_root / "status.json").write_text(
        json.dumps(status, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    return len(created)


def validate_metadata(value: object) -> dict:
    """Validate metadata before a write-enabled job trusts its patch."""
    metadata = require_mapping(value, "remediation metadata")
    if metadata.get("schema") != 1:
        raise RemediationError("unsupported remediation metadata schema")
    identifier = metadata.get("id")
    if not isinstance(identifier, str) or not EVENT_ID_RE.fullmatch(identifier):
        raise RemediationError("invalid remediation event identifier")
    base_commit = metadata.get("base_commit")
    if not isinstance(base_commit, str) or not SHA_RE.fullmatch(base_commit):
        raise RemediationError("invalid remediation base commit")

    ecosystem = metadata.get("ecosystem")
    directory = metadata.get("directory")
    if not isinstance(ecosystem, str) or not isinstance(directory, str):
        raise RemediationError("invalid remediation ecosystem or directory")
    directory, policy = policy_for(ecosystem, directory)
    metadata["directory"] = directory

    dependencies = metadata.get("dependencies")
    if not isinstance(dependencies, list) or not dependencies:
        raise RemediationError("remediation metadata has no dependencies")
    metadata["dependencies"] = sorted(
        {validate_dependency_name(name) for name in dependencies}
    )
    if event_id(ecosystem, directory, metadata["dependencies"]) != identifier:
        raise RemediationError(
            "remediation event identifier does not match its dependencies"
        )

    files = metadata.get("files")
    if (
        not isinstance(files, list)
        or not files
        or not all(isinstance(path, str) for path in files)
    ):
        raise RemediationError("remediation metadata has no valid file list")
    if len(files) != len(set(files)) or not set(files) <= policy.allowed_files:
        raise RemediationError("remediation metadata contains disallowed files")
    metadata["files"] = sorted(files)
    return metadata


def branch_name(metadata: dict) -> str:
    """Return a dedicated branch name derived only from validated metadata."""
    directory = metadata["directory"].strip("/").replace("/", "-") or "root"
    return f"ci/1.2-security-{metadata['ecosystem']}-{directory}-{metadata['id']}"


def pull_request_title(metadata: dict) -> str:
    """Make the released-branch security blind spot explicit in the title."""
    directory = metadata["directory"].strip("/") or "root"
    return (
        "build(deps): remediate 1.2.x security blind spot "
        f"({metadata['ecosystem']} {directory})"
    )


def pull_request_body(metadata: dict, *, issue: int, run_url: str) -> str:
    """Build a generic public body without copying advisory exploit details."""
    dependencies = "\n".join(f"- `{name}`" for name in metadata["dependencies"])
    return f"""Automated draft remediation for the `1.2.x` dependency-security blind spot.

GitHub-hosted Dependabot security updates only target the default branch (`develop`). This draft applies the public-advisory remediation generated for the supported `1.2.x` branch. For root npm updates, it also rebuilds the JavaScript files shipped in `include/js/` so the runtime assets match the lockfile.

Approve the draft's queued workflow runs, then complete compatibility and release testing before merge.

- Tracking issue: #{issue}
- Ecosystem: `{metadata['ecosystem']}`
- Manifest directory: `{metadata['directory']}`
- Source commit: `{metadata['base_commit']}`
- Workflow run: {run_url}

Dependencies:
{dependencies}
"""


def existing_pull_request(
    repository: str, base_branch: str, branch: str, cwd: Path
) -> dict | None:
    """Return the open remediation PR for a dedicated branch, if any."""
    result = run_command(
        [
            "gh",
            "pr",
            "list",
            "--repo",
            repository,
            "--base",
            base_branch,
            "--head",
            branch,
            "--state",
            "open",
            "--json",
            "number,isDraft",
        ],
        cwd=cwd,
        capture_output=True,
    ).stdout
    pulls = json.loads(result)
    if not isinstance(pulls, list):
        raise RemediationError("GitHub CLI returned invalid pull-request data")
    return pulls[0] if pulls else None


def publish_event(
    *,
    metadata_path: Path,
    repository_root: Path,
    repository: str,
    base_branch: str,
    assignee: str,
    issue: int,
    run_url: str,
) -> bool:
    """Apply, push, and open or refresh one draft remediation PR."""
    metadata = validate_metadata(load_json(metadata_path))
    patch_path = metadata_path.with_name("changes.patch")
    try:
        patch_size = patch_path.stat().st_size
    except OSError as error:
        raise RemediationError(f"missing remediation patch: {patch_path}") from error
    if patch_size <= 0 or patch_size > MAX_PATCH_BYTES:
        raise RemediationError("remediation patch is empty or too large")

    base_commit = metadata["base_commit"]
    ancestor = run_command(
        ["git", "merge-base", "--is-ancestor", base_commit, f"origin/{base_branch}"],
        cwd=repository_root,
        check=False,
    )
    if ancestor.returncode != 0:
        raise RemediationError(
            f"remediation base {base_commit} is not an ancestor of origin/{base_branch}"
        )

    branch = branch_name(metadata)
    run_command(["git", "config", "core.hooksPath", "/dev/null"], cwd=repository_root)
    run_command(
        ["git", "switch", "-C", branch, f"origin/{base_branch}"], cwd=repository_root
    )
    apply_result = run_command(
        ["git", "apply", "--index", "--3way", str(patch_path)],
        cwd=repository_root,
        check=False,
    )
    if apply_result.returncode != 0:
        raise RemediationError(
            f"remediation patch no longer applies cleanly: {metadata['id']}"
        )

    changed_output = run_command(
        ["git", "diff", "--cached", "--name-only", "-z", "--"],
        cwd=repository_root,
        capture_output=True,
        text=False,
    ).stdout
    changed = nul_paths(changed_output)
    if not changed:
        return False
    if not changed <= set(metadata["files"]):
        raise RemediationError(
            "applied patch changed files not declared by the read-only job"
        )

    run_command(
        ["git", "config", "user.name", "github-actions[bot]"], cwd=repository_root
    )
    run_command(
        [
            "git",
            "config",
            "user.email",
            "41898282+github-actions[bot]@users.noreply.github.com",
        ],
        cwd=repository_root,
    )
    run_command(
        [
            "git",
            "commit",
            "--no-verify",
            "--signoff",
            "--message",
            f"build(deps): remediate 1.2.x {metadata['ecosystem']} advisories",
        ],
        cwd=repository_root,
    )
    run_command(
        [
            "git",
            "push",
            "--no-verify",
            "--force",
            "origin",
            f"HEAD:refs/heads/{branch}",
        ],
        cwd=repository_root,
    )

    title = pull_request_title(metadata)
    body = pull_request_body(metadata, issue=issue, run_url=run_url)
    with tempfile.NamedTemporaryFile("w", encoding="utf-8", delete=False) as body_file:
        body_file.write(body)
        body_path = Path(body_file.name)
    try:
        existing = existing_pull_request(
            repository, base_branch, branch, repository_root
        )
        if existing:
            run_command(
                [
                    "gh",
                    "pr",
                    "edit",
                    str(existing["number"]),
                    "--repo",
                    repository,
                    "--title",
                    title,
                    "--body-file",
                    str(body_path),
                    "--add-assignee",
                    assignee,
                    "--add-label",
                    "dependencies",
                    "--add-label",
                    "1.2.x",
                ],
                cwd=repository_root,
            )
        else:
            run_command(
                [
                    "gh",
                    "pr",
                    "create",
                    "--repo",
                    repository,
                    "--base",
                    base_branch,
                    "--head",
                    branch,
                    "--draft",
                    "--title",
                    title,
                    "--body-file",
                    str(body_path),
                    "--assignee",
                    assignee,
                    "--label",
                    "dependencies",
                    "--label",
                    "1.2.x",
                ],
                cwd=repository_root,
            )
    finally:
        body_path.unlink(missing_ok=True)
    return True


def publish_results(
    *,
    artifacts: Path,
    repository_root: Path,
    repository: str,
    base_branch: str,
    assignee: str,
    issue: int,
    run_url: str,
) -> int:
    """Publish every validated remediation artifact."""
    if not REPOSITORY_RE.fullmatch(repository):
        raise RemediationError(f"invalid GitHub repository: {repository!r}")
    published = 0
    for metadata_path in sorted(artifacts.rglob("metadata.json")):
        if publish_event(
            metadata_path=metadata_path,
            repository_root=repository_root,
            repository=repository,
            base_branch=base_branch,
            assignee=assignee,
            issue=issue,
            run_url=run_url,
        ):
            published += 1
    return published


def parse_arguments() -> argparse.Namespace:
    """Parse the workflow-facing command line."""
    parser = argparse.ArgumentParser(description=__doc__)
    subparsers = parser.add_subparsers(dest="command", required=True)

    job = subparsers.add_parser("build-job")
    job.add_argument("--ecosystem", required=True, choices=("composer", "npm"))
    job.add_argument("--directory", required=True)
    job.add_argument("--report", required=True, type=Path)
    job.add_argument("--repository", required=True)
    job.add_argument("--commit", required=True)
    job.add_argument("--output", required=True, type=Path)

    materialize = subparsers.add_parser("materialize")
    materialize.add_argument("--results", required=True, type=Path)
    materialize.add_argument("--repository-root", required=True, type=Path)
    materialize.add_argument("--base-commit", required=True)
    materialize.add_argument("--ecosystem", required=True, choices=("composer", "npm"))
    materialize.add_argument("--directory", required=True)
    materialize.add_argument("--output", required=True, type=Path)

    publish = subparsers.add_parser("publish")
    publish.add_argument("--artifacts", required=True, type=Path)
    publish.add_argument("--repository-root", required=True, type=Path)
    publish.add_argument("--repository", required=True)
    publish.add_argument("--base-branch", default="1.2.x")
    publish.add_argument("--assignee", default="TheWitness")
    publish.add_argument("--issue", required=True, type=int)
    publish.add_argument("--run-url", required=True)
    return parser.parse_args()


def main() -> None:
    """Run the requested preparation or publication phase."""
    arguments = parse_arguments()
    try:
        if arguments.command == "build-job":
            document = build_job_document(
                ecosystem=arguments.ecosystem,
                directory=arguments.directory,
                report=load_json(arguments.report),
                repository=arguments.repository,
                commit=arguments.commit,
            )
            arguments.output.parent.mkdir(parents=True, exist_ok=True)
            arguments.output.write_text(
                json.dumps(document, indent=2, sort_keys=True) + "\n",
                encoding="utf-8",
            )
            print(len(document["job"]["dependencies"]))
        elif arguments.command == "materialize":
            print(
                materialize_results(
                    results=arguments.results,
                    repository_root=arguments.repository_root,
                    base_commit=arguments.base_commit,
                    ecosystem=arguments.ecosystem,
                    directory=arguments.directory,
                    output_root=arguments.output,
                )
            )
        else:
            print(
                publish_results(
                    artifacts=arguments.artifacts,
                    repository_root=arguments.repository_root,
                    repository=arguments.repository,
                    base_branch=arguments.base_branch,
                    assignee=arguments.assignee,
                    issue=arguments.issue,
                    run_url=arguments.run_url,
                )
            )
    except RemediationError as error:
        raise SystemExit(f"released dependency remediation failed: {error}") from error


if __name__ == "__main__":
    main()
