#!/usr/bin/env python3
"""Fold changelog.d fragments into the CHANGELOG development section."""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

FRAGMENT = re.compile(r"^(\d+|GHSA-[0-9A-Za-z-]+)\.(issue|feature|security)$")
RELEASE_HEADING = re.compile(r"^(?:\d+\.\d+\.\d+(?:-[\w.]+)?|\d+\.\d+\.x)$")


def load_fragments(directory: Path) -> list[tuple[str, str, str]]:
	"""Returns (number, kind, description) for every well-formed fragment."""
	entries: list[tuple[str, str, str]] = []
	for item in sorted(directory.iterdir()):
		match = FRAGMENT.fullmatch(item.name)
		if match is None:
			continue
		text = item.read_text(encoding="utf-8").strip()
		if text:
			entries.append((match.group(1), match.group(2), text))
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

	# A release section runs security, then issue, then feature. Security and
	# issue entries are newest first, so they open their block; features run
	# oldest first, so they close theirs.
	order = ("security", "issue", "feature")
	folded = 0

	for rank, kind in enumerate(order):
		group = sorted(
			((n, t) for n, k, t in fragments if k == kind),
			key=lambda item: (0, int(item[0])) if item[0].isdigit() else (1, item[0]),
		)
		if not group:
			continue

		if kind == "feature":
			# close the block: after the last entry of this kind or the one before it
			prefixes = tuple(f"-{order[r]}" for r in range(rank + 1))
		else:
			# open the block: after the last entry of the preceding kinds only
			prefixes = tuple(f"-{order[r]}" for r in range(rank))

		anchor = start + 1
		if prefixes:
			for index in range(end - 1, start, -1):
				if lines[index].startswith(prefixes):
					anchor = index + 1
					break

		for offset, (number, text) in enumerate(group):
			lines.insert(anchor + offset, f"-{kind}#{number}: {text}")

		end += len(group)
		folded += len(group)

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
