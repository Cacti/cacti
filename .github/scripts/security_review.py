#!/usr/bin/env python3
# Copyright (C) 2004-2026 The Cacti Group
# GPL-2.0-or-later
"""
Security review runner.

Reads the PR diff, grep hits, and hotspot excerpts from disk, calls the
review API with the security review prompt, then posts the result as a
PR issue comment via the GitHub REST API.

Exits 1 when the review recommends blocking the PR so the workflow step
fails and gates the merge. Exits 0 for all other outcomes (including API
errors) to avoid blocking PRs on infrastructure failures.
"""

import json
import os
import sys
import textwrap
import urllib.error
import urllib.request

# ---------------------------------------------------------------------------
# Configuration from environment
# ---------------------------------------------------------------------------

ANTHROPIC_API_KEY = os.environ.get("ANTHROPIC_API_KEY", "")
GITHUB_TOKEN      = os.environ.get("GITHUB_TOKEN", "")
GITHUB_REPO       = os.environ.get("GITHUB_REPOSITORY", "")   # "owner/repo"
PR_NUMBER         = os.environ.get("GITHUB_PR_NUMBER", "")

PROMPT_FILE  = os.environ.get("SECURITY_PROMPT_FILE", ".github/prompts/security-review.md")
DIFF_FILE    = os.environ.get("PR_DIFF_FILE",    "/tmp/pr.diff")
GREP_FILE    = os.environ.get("GREP_HITS_FILE",  "/tmp/grep_hits.txt")
HOTSPOT_FILE = os.environ.get("HOTSPOT_FILE",    "/tmp/hotspot_contents.txt")

MODEL          = "claude-opus-4-8"
MAX_TOKENS     = 4096
MAX_DIFF       = 40_000
MAX_GREP       = 20_000
MAX_HOTSPOT    = 20_000
API_TIMEOUT    = 300   # seconds
GITHUB_TIMEOUT = 30


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

class EmptyReviewError(RuntimeError):
    """API responded but carried no usable review text; gate must fail closed."""


def read_truncated(path: str, max_chars: int) -> str:
    """Return up to max_chars of a file, appending a truncation notice."""
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            content = fh.read(max_chars)
        if os.path.getsize(path) > max_chars:
            content += "\n[... truncated ...]"
        return content
    except FileNotFoundError:
        return "(not available)"


def call_claude(system_prompt: str, user_message: str) -> str:
    """Call the Anthropic Messages API and return the assistant text."""
    if not ANTHROPIC_API_KEY:
        raise RuntimeError("ANTHROPIC_API_KEY is not set")

    payload = json.dumps({
        "model": MODEL,
        "max_tokens": MAX_TOKENS,
        "system": system_prompt,
        "messages": [{"role": "user", "content": user_message}],
    }).encode()

    req = urllib.request.Request(
        "https://api.anthropic.com/v1/messages",
        data=payload,
        headers={
            "x-api-key": ANTHROPIC_API_KEY,
            "anthropic-version": "2023-06-01",
            "content-type": "application/json",
        },
    )
    with urllib.request.urlopen(req, timeout=API_TIMEOUT) as resp:
        body = json.loads(resp.read())

    # The Messages API returns a list of content blocks; only some are text
    # (e.g. tool_use blocks have no "text"). Concatenate every text block.
    blocks = body.get("content", [])
    text = "".join(
        block.get("text", "")
        for block in blocks
        if isinstance(block, dict) and block.get("type") == "text"
    )
    if not text.strip():
        raise EmptyReviewError(f"no text content in API response (stop_reason={body.get('stop_reason')})")
    return text


def post_pr_comment(body: str) -> None:
    """Post a comment on the PR via the GitHub REST API."""
    if not GITHUB_TOKEN or not GITHUB_REPO or not PR_NUMBER:
        raise RuntimeError("Missing GITHUB_TOKEN / GITHUB_REPOSITORY / GITHUB_PR_NUMBER")

    url = f"https://api.github.com/repos/{GITHUB_REPO}/issues/{PR_NUMBER}/comments"
    payload = json.dumps({"body": body}).encode()
    req = urllib.request.Request(
        url,
        data=payload,
        headers={
            "Authorization": f"Bearer {GITHUB_TOKEN}",
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
            "Content-Type": "application/json",
        },
    )
    with urllib.request.urlopen(req, timeout=GITHUB_TIMEOUT) as resp:
        status = resp.status
    if status not in (200, 201):
        raise RuntimeError(f"GitHub API returned HTTP {status}")


# ---------------------------------------------------------------------------
# Decision parsing
# ---------------------------------------------------------------------------

# The review prompt requires the model to END with exactly one of these markers.
DECISION_BLOCK  = "BLOCK PR"
DECISION_REVIEW = "REVIEW REQUIRED"
DECISION_PASS   = "NO BLOCKING SECURITY ISSUES FOUND"
DECISIONS = (DECISION_BLOCK, DECISION_REVIEW, DECISION_PASS)


def final_decision(review: str) -> "str | None":
    """Return the review's final decision marker, or None when absent.

    We gate on the decision marker rather than substring-matching "BLOCK PR":
    a substring match misfires on prose ("I would not BLOCK PR") and, worse, on
    the attacker-controlled diff echoing the instruction text back into the
    review body. Scan from the bottom and return the first line that, once
    stripped of markdown decoration, equals one of the markers.
    """
    for raw in reversed(review.splitlines()):
        line = raw.strip().strip("`*_# ").strip().rstrip(".:").strip().upper()

        if line in DECISIONS:
            return line

    return None


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main() -> None:
    # Load system prompt
    try:
        with open(PROMPT_FILE, encoding="utf-8") as fh:
            system_prompt = fh.read()
    except FileNotFoundError:
        print(f"::error::Prompt file not found: {PROMPT_FILE}", file=sys.stderr)
        sys.exit(0)  # infrastructure failure; do not block PR

    diff     = read_truncated(DIFF_FILE,    MAX_DIFF)
    grep_out = read_truncated(GREP_FILE,    MAX_GREP)
    hotspots = read_truncated(HOTSPOT_FILE, MAX_HOTSPOT)

    user_message = textwrap.dedent(f"""\
        ## PR Diff

        ```diff
        {diff}
        ```

        ## grep / static-analysis hits on changed files

        ```
        {grep_out}
        ```

        ## Hotspot file excerpts (prior-advisory targets)

        ```
        {hotspots}
        ```

        Perform your review now. Follow the output structure in your instructions exactly.
    """)

    try:
        review = call_claude(system_prompt, user_message)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode(errors="replace")
        print(f"::warning::Anthropic API error {exc.code}: {body[:500]}", file=sys.stderr)
        sys.exit(0)
    except EmptyReviewError as exc:
        # A malformed/empty response means the gate did not actually run; fail
        # closed rather than letting the PR pass without a review.
        print(f"::error::Security review failed: {exc}", file=sys.stderr)
        sys.exit(1)
    except Exception as exc:  # noqa: BLE001
        print(f"::warning::Security review skipped: {exc}", file=sys.stderr)
        sys.exit(0)

    comment_body = (
        "## Security Review\n\n"
        + review
        + "\n\n---\n*[security-review.yml](.github/workflows/security-review.yml)*"
    )

    try:
        post_pr_comment(comment_body)
        print("Security review comment posted.")
    except Exception as exc:  # noqa: BLE001
        print(f"::warning::Failed to post review comment: {exc}", file=sys.stderr)
        # Print to stdout so the review is visible in the workflow log regardless
        print("\n--- REVIEW OUTPUT ---\n")
        print(review)

    # Gate the merge on the model's *final decision*, not a substring match.
    decision = final_decision(review)

    if decision is None:
        print("::warning::Security review produced no recognizable final decision; not gating.", file=sys.stderr)
    elif decision == DECISION_BLOCK:
        print("::error::Security review recommends blocking this PR.", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
