#!/usr/bin/env python3
# Copyright (C) 2004-2026 The Cacti Group
# GPL-2.0-or-later
"""Read PR Git objects as data without checking out or executing head files."""

import os
from pathlib import Path
import re
import subprocess

MAX_BYTES = 20_000_000
# Regexes backtrack quadratically on a long line, so only the head of a line is matched.
MAX_LINE = 4_096
TRUNCATED = b"\n[... truncated ...]\n"
PATTERNS = {
    "SQL sinks": r"db_(query|execute|fetch_assoc|fetch_row|fetch_cell)\s*\(",
    "SQL concatenation": r'"(?:SELECT|INSERT|UPDATE|DELETE|WHERE|FROM|ORDER BY|LIMIT).*\.\s*\$',
    "Direct superglobal access": r"\$_(GET|POST|REQUEST|COOKIE|FILES)\s*\[",
    "Unfiltered request variables": r"get_nfilter_request_var\s*\(",
    "Variable output": r"(echo|print)\s+\$",
    "Command execution": r"\b(exec|system|shell_exec|passthru|proc_open|popen)\s*\(",
    "Dynamic includes": r"\b(include|require)(_once)?\s+\$",
    "File writes": r"\b(file_put_contents|move_uploaded_file|fopen|rename|copy)\s*\(",
    "Archive extraction": r"\b(extractTo|ZipArchive|PharData)\b",
    "Variable file paths": r"(include|require|fopen|file_get_contents)\s*\(\s*\$(?!real_path|resolved)",
}
HOTSPOTS = (
    "auth/auth_login.php", "auth/changepassword.php", "lib/import.php",
    "lib/package.php", "lib/plugins.php", "lib/auth.php", "managers.php",
    "help.php", "install/index.php",
)


def git(*arguments):
    result = subprocess.run(
        ["git", "--no-pager", *arguments], check=True,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=120,
    )
    # The reviewer sees a bounded excerpt either way; truncating keeps the
    # review running on a very large PR instead of failing the job.
    if len(result.stdout) > MAX_BYTES:
        return result.stdout[:MAX_BYTES] + TRUNCATED
    return result.stdout


def match_lines(name, source):
    hits = []
    lines = source.decode(errors="replace").splitlines()
    for label, pattern in PATTERNS.items():
        for number, line in enumerate(lines, 1):
            if re.search(pattern, line[:MAX_LINE]):
                hits.append(f"{label}: {name!r}:{number}: {line[:MAX_LINE]}")
    return hits


def write_private(path, content):
    data = content if isinstance(content, bytes) else content.encode()
    # Review input can contain secrets from the diff; keep it owner-only.
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, "wb") as handle:
        handle.write(data)


def collect(base, head, output):
    if not all(re.fullmatch(r"[0-9a-f]{40}", value) for value in (base, head)):
        raise ValueError("Review revisions must be full commit SHAs")
    merge_base = git("merge-base", base, head).decode().strip()
    diff = git("diff", "--no-ext-diff", "--no-textconv", f"{merge_base}..{head}", "--")
    names = git("diff", "--name-only", "-z", "--diff-filter=ACMRT", merge_base, head, "--")
    hits = []
    total = len(diff)
    for raw in names.split(b"\0"):
        if not raw:
            continue
        name = os.fsdecode(raw)
        if not name.endswith(".php") or name.startswith(("include/vendor/", "locales/")):
            continue
        # git show returns a blob. Symlinks remain link text, never file reads.
        source = git("show", f"{head}:{name}")
        total += len(source)
        if total > MAX_BYTES:
            hits.append("(changed PHP sources exceed the collection limit; remaining files not scanned)")
            break
        hits.extend(match_lines(name, source))
    hotspots = []
    for name in HOTSPOTS:
        path = Path(name)
        if path.is_file() and not path.is_symlink():
            lines = path.read_text(errors="replace").splitlines()[:120]
            hotspots.append(f"=== Trusted base: {name} ===\n" + "\n".join(lines))
    output.mkdir(mode=0o700, parents=True, exist_ok=True)
    write_private(output / "pr.diff", diff)
    write_private(output / "grep_hits.txt", "\n".join(hits) or "(no PHP pattern matches)")
    write_private(output / "hotspot_contents.txt", "\n\n".join(hotspots))
    print(f"Collected {len(diff)} diff bytes and {len(hits)} static pattern matches.")


if __name__ == "__main__":
    collect(
        os.environ["PR_BASE_SHA"],
        os.environ["PR_HEAD_SHA"],
        Path(os.environ["RUNNER_TEMP"]) / "cacti-security-review",
    )
