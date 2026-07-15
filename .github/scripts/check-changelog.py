#!/usr/bin/env python3
"""Validate the repository CHANGELOG grouping and entry format."""

from __future__ import annotations

import re
import os
import subprocess
import sys
from pathlib import Path


RELEASE_HEADING = re.compile(r"^(?:\d+\.\d+\.\d+(?:-[\w.]+)?|\d+\.\d+\.x)$")
ENTRY = re.compile(r"^-(issue|feature)(?:#\d+)?:\s+\S.*$")
NUMBERED_ENTRY = re.compile(r"^-(?:issue|feature)#\d+:")


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

	errors: list[str] = []
	seen_feature = False

	for index in range(start + 1, end):
		line = lines[index].rstrip()
		number = index + 1
		if not line:
			continue
		if not line.startswith(("-issue", "-feature")):
			if line.startswith("-"):
				errors.append(f"line {number}: malformed CHANGELOG entry: {line}")
			continue
		if not ENTRY.fullmatch(line):
			errors.append(f"line {number}: malformed CHANGELOG entry: {line}")
			continue

		kind = "feature" if line.startswith("-feature") else "issue"
		if kind == "feature":
			seen_feature = True
		elif seen_feature:
			errors.append(f"line {number}: issue entry appears after feature entries")

	if end < len(lines) and lines[end - 1].strip():
		errors.append(f"line {end + 1}: release headings must be separated by a blank line")

	# Existing releases contain legacy unnumbered entries. New entries must be
	# traceable to an issue or pull request, so enforce that only on PR additions.
	base_ref = os.environ.get("GITHUB_BASE_REF")
	if base_ref:
		result = subprocess.run(
			["git", "diff", "--unified=0", f"origin/{base_ref}...HEAD", "--", "CHANGELOG"],
			capture_output=True,
			text=True,
			check=False,
		)
		if result.returncode == 0:
			for added in result.stdout.splitlines():
				if added.startswith(("+++", "---")):
					continue
				line = added[1:] if added.startswith("+") else ""
				if line.startswith(("-issue", "-feature")) and not NUMBERED_ENTRY.match(line):
					errors.append(f"new CHANGELOG entries must include an issue or pull-request number: {line}")

	if errors:
		print("CHANGELOG validation failed:", file=sys.stderr)
		print("\n".join(f"- {error}" for error in errors), file=sys.stderr)
		return 1

	print("CHANGELOG format is valid")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
