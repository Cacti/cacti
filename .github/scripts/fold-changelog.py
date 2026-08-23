#!/usr/bin/env python3
"""Fold changelog.d fragments into the CHANGELOG development section."""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

FRAGMENT = re.compile(r"^(\d+)\.(issue|feature)$")
RELEASE_HEADING = re.compile(r"^(?:\d+\.\d+\.\d+(?:-[\w.]+)?|\d+\.\d+\.x)$")


def load_fragments(directory: Path) -> list[tuple[int, str, str]]:
	"""Returns (number, kind, description) for every well-formed fragment."""
	entries: list[tuple[int, str, str]] = []
	for item in sorted(directory.iterdir()):
		match = FRAGMENT.fullmatch(item.name)
		if match is None:
			continue
		text = item.read_text(encoding="utf-8").strip()
		if text:
			entries.append((int(match.group(1)), match.group(2), text))
	return entries


def section_bounds(lines: list[str]) -> tuple[int, int]:
	"""Locates the first release section, which is always the development one."""
	start = next(i for i, line in enumerate(lines) if RELEASE_HEADING.fullmatch(line))
	end = len(lines)
	for index in range(start + 1, len(lines)):
		if RELEASE_HEADING.fullmatch(lines[index]):
			end = index
			break
	return start, end


def main() -> int:
	parser = argparse.ArgumentParser(description=__doc__)
	parser.add_argument("--check", action="store_true", help="report without writing")
	args = parser.parse_args()

	directory = Path("changelog.d")
	path = Path("CHANGELOG")
	if not path.is_file():
		print("CHANGELOG is missing", file=sys.stderr)
		return 1
	if not directory.is_dir():
		print("changelog.d is missing", file=sys.stderr)
		return 1

	fragments = load_fragments(directory)
	if not fragments:
		print("no fragments to fold")
		return 0

	lines = path.read_text(encoding="utf-8").splitlines()
	start, end = section_bounds(lines)

	issues = sorted((n, t) for n, kind, t in fragments if kind == "issue")
	features = sorted((n, t) for n, kind, t in fragments if kind == "feature")

	# Issues run newest first from the top of the section; features run oldest
	# first and are appended after the last existing feature.
	for number, text in issues:
		lines.insert(start + 1, f"-issue#{number}: {text}")
		end += 1

	anchor = end
	for index in range(end - 1, start, -1):
		if lines[index].startswith("-feature"):
			anchor = index + 1
			break

	for offset, (number, text) in enumerate(features):
		lines.insert(anchor + offset, f"-feature#{number}: {text}")

	folded = len(issues) + len(features)
	if args.check:
		print(f"{folded} fragment(s) would be folded")
		return 0

	path.write_text("\n".join(lines) + "\n", encoding="utf-8")
	for item in directory.iterdir():
		if FRAGMENT.fullmatch(item.name):
			item.unlink()

	print(f"folded {folded} fragment(s) into CHANGELOG")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
