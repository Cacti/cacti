#!/usr/bin/env python3
"""Validate the repository CHANGELOG grouping and entry format."""

from __future__ import annotations

import re
import os
import subprocess
import sys
from pathlib import Path


RELEASE_HEADING = re.compile(r"^(?:\d+\.\d+\.\d+(?:-[\w.]+)?|\d+\.\d+\.x)$")
ENTRY = re.compile(r"^-(issue|feature|security)(?:#[0-9A-Za-z-]+)?:\s+\S.*$")
RANK_NAMES = {0: "security", 1: "issue", 2: "feature"}
NUMBERED_ENTRY = re.compile(r"^-(?:issue|feature)#\d+:|^-security#(?:\d+|GHSA-[0-9A-Za-z-]+):")
FRAGMENT = re.compile(r"^(\d+|GHSA-[0-9A-Za-z-]+)\.(issue|feature|security)$")


def check_fragment_body(item: Path, errors: list[str]) -> None:
	text = item.read_text(encoding="utf-8")
	body = text.strip()
	if not body:
		errors.append(f"{item}: fragment is empty")
	elif len(body.splitlines()) > 1:
		errors.append(f"{item}: fragment must be a single line")
	elif body.startswith("-"):
		errors.append(f"{item}: fragment holds the description only, without the -issue or -feature prefix")


def check_fragments(directory: Path) -> list[str]:
	"""Validates changelog.d fragments, which replace direct CHANGELOG edits."""
	errors: list[str] = []
	if not directory.is_dir():
		return errors

	for item in sorted(directory.iterdir()):
		if item.name == "README.md" or item.name.startswith("."):
			continue
		if not item.is_file():
			errors.append(f"{item}: changelog.d holds fragment files only")
			continue
		match = FRAGMENT.fullmatch(item.name)
		if match is None:
			errors.append(f"{item}: fragment must be named <number>.issue, <number>.feature, or <number|GHSA-id>.security")
			continue
		if match.group(2) != "security" and not match.group(1).isdigit():
			errors.append(f"{item}: only a security fragment may use a GHSA identifier")
			continue

		check_fragment_body(item, errors)

	return errors


def entry_kind(line: str, number: int, errors: list[str]) -> tuple[str, int] | None:
	if not line:
		return None
	if not line.startswith(("-issue", "-feature", "-security")):
		if line.startswith("-"):
			errors.append(f"line {number}: malformed CHANGELOG entry: {line}")
		return None
	if not ENTRY.fullmatch(line):
		errors.append(f"line {number}: malformed CHANGELOG entry: {line}")
		return None
	if line.startswith("-security"):
		return "security", 0
	if line.startswith("-issue"):
		return "issue", 1
	return "feature", 2


def check_entries(lines: list[str], start: int, end: int, errors: list[str]) -> None:
	highest_rank = 0
	for index in range(start + 1, end):
		line = lines[index].rstrip()
		number = index + 1
		kind_rank = entry_kind(line, number, errors)
		if kind_rank is None:
			continue
		kind, rank = kind_rank

		if rank < highest_rank:
			errors.append(f"line {number}: {kind} entry appears after {RANK_NAMES[highest_rank]} entries")
		else:
			highest_rank = rank


def check_added_entries(errors: list[str]) -> None:
	base_ref = os.environ.get("GITHUB_BASE_REF")
	if not base_ref:
		return
	result = subprocess.run(
		["git", "diff", "--unified=0", f"origin/{base_ref}...HEAD", "--", "CHANGELOG"],
		capture_output=True,
		text=True,
		check=False,
	)
	if result.returncode != 0:
		return
	for added in result.stdout.splitlines():
		if added.startswith(("+++", "---")):
			continue
		line = added[1:] if added.startswith("+") else ""
		if line.startswith(("-issue", "-feature")) and not NUMBERED_ENTRY.match(line):
			errors.append(f"new CHANGELOG entries must include an issue or pull-request number: {line}")


def main() -> int:
	path = Path("CHANGELOG")
	if not path.is_file():
		print("CHANGELOG is missing", file=sys.stderr)
		return 1

	lines = path.read_text(encoding="utf-8").splitlines()
	try:
		start = lines.index("1.3.0-dev")
	except ValueError:
		print("CHANGELOG is missing the 1.3.0-dev section", file=sys.stderr)
		return 1

	end = len(lines)
	for index in range(start + 1, len(lines)):
		if RELEASE_HEADING.fullmatch(lines[index]):
			end = index
			break

	errors: list[str] = check_fragments(Path("changelog.d"))
	check_entries(lines, start, end, errors)

	if end < len(lines) and lines[end - 1].strip():
		errors.append(f"line {end + 1}: release headings must be separated by a blank line")

	# Existing releases contain legacy unnumbered entries. New entries must be
	# traceable to an issue or pull request, so enforce that only on PR additions.
	check_added_entries(errors)

	if errors:
		print("CHANGELOG validation failed:", file=sys.stderr)
		print("\n".join(f"- {error}" for error in errors), file=sys.stderr)
		return 1

	print("CHANGELOG format is valid")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
