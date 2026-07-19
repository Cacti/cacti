#!/usr/bin/env python3
"""Refresh .all-contributorsrc and the README contributors table.

This is a best-effort daily heuristic, not a re-run of the manual audit that
seeded .all-contributorsrc. Known limitations:

- Author matching is done by comparing git commit author name/email against
  each contributor's stored "login"/"name" fields. Contributors whose git
  identity doesn't resemble either field (e.g. historical founders using an
  old alias) will not get their badges refreshed automatically; those stay
  as manually curated.
- The manually curated ordering (founders/emeritus, active developers,
  honorable mentions, then by contribution count) is preserved as-is. New
  contributors are appended at the end, not re-sorted into a tier.
- "review" badges come from the most recently updated merged PRs only (see
  REVIEW_PR_PAGES below), not full repo history, to keep the GraphQL query
  cheap enough to run daily.
"""

from __future__ import annotations

import json
import math
import re
import subprocess
import sys
from pathlib import Path

REPO = "Cacti/cacti"
CONTRIBUTORSRC = Path(".all-contributorsrc")
README = Path("README.md")

REVIEW_PR_PAGES = 5
REVIEW_PR_PAGE_SIZE = 50

# login (lowercased) and avatar_url substrings that should never be
# recorded as human contributors.
BOT_LOGINS = {"dependabot[bot]", "weblate", "copilot-pull-request-reviewer", "github-advanced-security"}
BOT_AVATAR_MARKERS = ("githubusercontent.com/in/",)

# Ordered so the rendered badge list always reads the same way as the
# hand-built table already in README.md.
BADGE_ORDER = [
    "code", "security", "doc", "infra", "translation", "test",
    "design", "maintenance", "review", "bug", "ideas",
    "projectManagement", "question",
]

EMOJI = {
    "code": ("\U0001F4BB", "Code"),
    "security": ("\U0001F6E1️", "Security"),
    "doc": ("\U0001F4D6", "Documentation"),
    "infra": ("\U0001F687", "Infrastructure"),
    "translation": ("\U0001F30D", "Translation"),
    "test": ("⚠️", "Tests"),
    "design": ("\U0001F3A8", "Design"),
    "maintenance": ("\U0001F6A7", "Maintenance"),
    "review": ("\U0001F440", "Reviewed Pull Requests"),
    "bug": ("\U0001F41B", "Bug reports"),
    "ideas": ("\U0001F914", "Ideas, Planning, & Feedback"),
    "projectManagement": ("\U0001F4C6", "Project Management"),
    "question": ("\U0001F4AC", "Answering Questions"),
}

# path prefix -> badge, checked against every file touched by a commit.
PATH_BADGES = [
    (re.compile(r"^locales/"), "translation"),
    (re.compile(r"^tests/"), "test"),
    (re.compile(r"^images/"), "design"),
    (re.compile(r"^(\.github/|docker/|Dockerfile|docker-compose)"), "infra"),
    (re.compile(r"^(composer\.json|phpstan|rector\.php|\.php-cs-fixer\.php|\.pre-commit-config\.yaml)"), "maintenance"),
    (re.compile(r"^docs/|\.md$"), "doc"),
]

SECURITY_MESSAGE_RE = re.compile(r"CVE-|GHSA|security|XSS|SQLi|vulnerab", re.IGNORECASE)


def run(cmd: list[str]) -> str:
    return subprocess.run(cmd, capture_output=True, text=True, check=True).stdout


def gh_api(path: str, paginate: bool = False) -> list | dict:
    cmd = ["gh", "api", path]
    if paginate:
        cmd.append("--paginate")
    return json.loads(run(cmd) or "[]")


def is_bot(login: str, avatar_url: str) -> bool:
    login_l = login.lower()
    if login_l in BOT_LOGINS or "weblate" in login_l:
        return True
    return any(marker in (avatar_url or "") for marker in BOT_AVATAR_MARKERS)


def load_contributors() -> dict:
    return json.loads(CONTRIBUTORSRC.read_text())


def fetch_current_contributors() -> list[dict]:
    return gh_api(f"repos/{REPO}/contributors", paginate=True)


def merge_new_contributors(config: dict) -> bool:
    existing_logins = {c["login"].lower() for c in config["contributors"]}
    changed = False
    for c in fetch_current_contributors():
        login = c.get("login", "")
        if not login or login.lower() in existing_logins:
            continue
        if is_bot(login, c.get("avatar_url", "")):
            continue
        config["contributors"].append({
            "login": login,
            "name": login,
            "avatar_url": c.get("avatar_url", ""),
            "profile": f"https://github.com/{login}",
            "contributions": ["code"],
        })
        existing_logins.add(login.lower())
        changed = True
    return changed


def git_log_by_author() -> dict[str, dict]:
    """Map a lowercase author name/email to touched paths and messages."""
    raw = run(["git", "log", "--name-only", "--pretty=format:@@%an|%ae|%s"])
    by_author: dict[str, dict] = {}
    current = None
    for line in raw.splitlines():
        if line.startswith("@@"):
            name, email, subject = line[2:].split("|", 2)
            key_name, key_email = name.strip().lower(), email.strip().lower()
            current = by_author.setdefault(key_name, {"emails": set(), "paths": set(), "messages": []})
            current["emails"].add(key_email)
            current["messages"].append(subject)
            # also index by email so either identity resolves to the same bucket
            by_author.setdefault(key_email, current)
        elif line.strip() and current is not None:
            current["paths"].add(line.strip())
    return by_author


def badges_for(activity: dict) -> set[str]:
    badges: set[str] = set()
    for path in activity["paths"]:
        for pattern, badge in PATH_BADGES:
            if pattern.search(path):
                badges.add(badge)
    if any(SECURITY_MESSAGE_RE.search(m) for m in activity["messages"]):
        badges.add("security")
    if activity["paths"] or activity["messages"]:
        badges.add("code")
    return badges


def refresh_path_badges(config: dict) -> bool:
    by_author = git_log_by_author()
    changed = False
    for contributor in config["contributors"]:
        login = contributor["login"].lower()
        name = contributor.get("name", "").lower()
        activity = by_author.get(login) or by_author.get(name)
        if activity is None:
            continue
        new_badges = badges_for(activity)
        before = set(contributor["contributions"])
        after = before | new_badges
        if after != before:
            contributor["contributions"] = [b for b in BADGE_ORDER if b in after]
            changed = True
    return changed


def refresh_review_badges(config: dict) -> bool:
    query = """
    query($cursor: String) {
      repository(owner: "Cacti", name: "cacti") {
        pullRequests(states: MERGED, first: %d, after: $cursor, orderBy: {field: UPDATED_AT, direction: DESC}) {
          pageInfo { hasNextPage endCursor }
          nodes { reviews(first: 20) { nodes { author { login } } } }
        }
      }
    }
    """ % REVIEW_PR_PAGE_SIZE

    reviewers: set[str] = set()
    cursor = None
    for _ in range(REVIEW_PR_PAGES):
        args = ["gh", "api", "graphql", "-f", f"query={query}"]
        if cursor:
            args += ["-f", f"cursor={cursor}"]
        data = json.loads(run(args))
        prs = data["data"]["repository"]["pullRequests"]
        for pr in prs["nodes"]:
            for review in pr["reviews"]["nodes"]:
                author = review.get("author")
                if author and author.get("login"):
                    reviewers.add(author["login"].lower())
        if not prs["pageInfo"]["hasNextPage"]:
            break
        cursor = prs["pageInfo"]["endCursor"]

    changed = False
    by_login = {c["login"].lower(): c for c in config["contributors"]}
    for login in reviewers:
        if is_bot(login, ""):
            continue
        contributor = by_login.get(login)
        if contributor is None:
            continue
        if "review" not in contributor["contributions"]:
            contributor["contributions"] = [
                b for b in BADGE_ORDER
                if b in set(contributor["contributions"]) | {"review"}
            ]
            changed = True
    return changed


def cell_html(contributor: dict, width_pct: str) -> str:
    links = "".join(
        f'<a href="#" title="{EMOJI[badge][1]}">{EMOJI[badge][0]}</a>'
        for badge in BADGE_ORDER if badge in contributor["contributions"]
    )
    return (
        f'        <td align="center" valign="top" width="{width_pct}">'
        f'<a href="{contributor["profile"]}">'
        f'<img src="{contributor["avatar_url"]}" width="100px;" alt="{contributor["name"]}"/>'
        f'<br /><sub><b>{contributor["name"]}</b></sub></a>'
        f'<br />{links}</td>'
    )


def render_table(config: dict) -> str:
    per_line = config["contributorsPerLine"]
    width_pct = f"{math.floor((100 / per_line) * 100) / 100:.2f}%"
    contributors = config["contributors"]

    rows = []
    for i in range(0, len(contributors), per_line):
        chunk = contributors[i:i + per_line]
        cells = "\n".join(cell_html(c, width_pct) for c in chunk)
        rows.append(f"      <tr>\n{cells}\n      </tr>")

    return (
        "<!-- markdownlint-disable -->\n"
        "<table>\n"
        "  <tbody>\n"
        + "\n".join(rows) +
        "\n  </tbody>\n"
        "</table>\n"
        "<!-- markdownlint-restore -->"
    )


def update_readme(config: dict) -> bool:
    text = README.read_text()
    start_marker = "<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->"
    end_marker = "<!-- ALL-CONTRIBUTORS-LIST:END -->"
    start = text.index(start_marker) + len(start_marker)
    end = text.index(end_marker)
    new_text = text[:start] + "\n" + render_table(config) + "\n\n" + text[end:]
    if new_text == text:
        return False
    README.write_text(new_text)
    return True


def main() -> int:
    config = load_contributors()

    changed = False
    changed |= merge_new_contributors(config)
    changed |= refresh_path_badges(config)
    changed |= refresh_review_badges(config)

    if not changed:
        print("no contributor changes detected")
        return 0

    CONTRIBUTORSRC.write_text(json.dumps(config, indent=2, ensure_ascii=False) + "\n")
    update_readme(config)
    print("contributors updated")
    return 0


if __name__ == "__main__":
    sys.exit(main())
