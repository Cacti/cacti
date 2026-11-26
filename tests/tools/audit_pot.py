#!/usr/bin/env python3
"""
audit_pot.py - Source-string linter for Cacti gettext .pot templates.

Checks:
  1. Trailing period parity between msgid and msgid_plural.
  2. Balanced and well-formed HTML tags in msgid and msgid_plural.
  3. Entries flagged php-format (without no-php-format) that contain no
     actual printf specifiers (e.g. literal '%' prose like "80% of" or
     "LIKE uses %").
  4. Duplicate, mixed, zero-indexed, gapped, or malformed printf specifiers
     in source strings.

Usage:
  python3 tests/tools/audit_pot.py locales/po/cacti.pot
  python3 tests/tools/audit_pot.py --base origin/develop locales/po/cacti.pot

With --base, only entries added or changed (including flag changes) since that
commit are audited, so pre-existing template issues do not block unrelated PRs.
If the base copy cannot be read (new file, unknown ref), every entry is audited.

Exit codes:
  0 - no errors
  1 - one or more errors found

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""

from __future__ import annotations

import argparse
from pathlib import Path
import re
import subprocess
import sys
import tempfile

import polib


_CONVERSION = r'[bcdeEfFgGosuxX]'
_PLACEHOLDER_PARTS = re.compile(
    r'%(?!%)'
    r'(?:([0-9]+)\$)?'
    r"[#0\-+']*"
    r'(?:[0-9]+|\*)?'
    r'(?:\.(?:[0-9]+|\*))?'
    r'(?:hh|h|ll|l|L|z|j|t)?'
    r'(' + _CONVERSION + r')'
)
_MALFORMED_POSITIONAL = re.compile(
    r'%\$'
    r'|%[0-9]+\$(?![#0\-+\']*(?:[0-9]+|\*)?(?:\.(?:[0-9]+|\*))?'
    r'(?:hh|h|ll|l|L|z|j|t)?' + _CONVERSION + r')'
)

_HTML_TAGS = frozenset({
    'a', 'abbr', 'b', 'blockquote', 'br', 'code', 'dd', 'div', 'dl', 'dt',
    'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img',
    'input', 'kbd', 'li', 'ol', 'p', 'pre', 'small', 'span', 'strong',
    'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'tt', 'u', 'ul',
})
_VOID_HTML_TAGS = frozenset({'br', 'hr', 'img', 'input'})
_TAG_RE = re.compile(r'<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>')
_UNTERMINATED_TAG_RE = re.compile(r'<\s*/?\s*([a-zA-Z][a-zA-Z0-9]*)(?:\s[^>]*)?$')


def _check_html_balance(text: str) -> list[str]:
    """Return a list of HTML balance/syntax error messages for text."""
    stack: list[str] = []
    problems: list[str] = []

    for match in _TAG_RE.finditer(text):
        closing = bool(match.group(1))
        tag = match.group(2).lower()
        rest = match.group(3)
        if tag not in _HTML_TAGS:
            continue
        if tag in _VOID_HTML_TAGS or rest.rstrip().endswith('/'):
            continue
        if not closing:
            stack.append(tag)
        else:
            if not stack or stack[-1] != tag:
                problems.append(f'unexpected closing </{tag}>')
                if tag in stack:
                    while stack and stack[-1] != tag:
                        stack.pop()
                    if stack:
                        stack.pop()
            else:
                stack.pop()

    if stack:
        unclosed = ', '.join(f'<{tag}>' for tag in stack)
        problems.append(f'unclosed HTML tag(s): {unclosed}')

    remainder = _TAG_RE.sub('', text)
    unterminated = _UNTERMINATED_TAG_RE.search(remainder)
    if unterminated and unterminated.group(1).lower() in _HTML_TAGS:
        problems.append(f'malformed/unterminated HTML tag <{unterminated.group(1).lower()}>')

    return problems


def _check_source_format(text: str) -> list[str]:
    """Check a single English source string for malformed/duplicate specifiers."""
    cleaned = text.replace('%%', '')
    problems: list[str] = []

    bad_pos = _MALFORMED_POSITIONAL.findall(cleaned)
    if bad_pos:
        problems.append(f'malformed positional printf specifier(s): {", ".join(bad_pos)}')

    matches = list(_PLACEHOLDER_PARTS.finditer(cleaned))
    if not matches:
        return problems

    positions = [int(m.group(1)) if m.group(1) is not None else None for m in matches]
    pos_only = [p for p in positions if p is not None]

    if 0 < len(pos_only) < len(positions):
        problems.append('mixed positional and non-positional printf specifiers')

    if any(p < 1 for p in pos_only):
        problems.append('zero-indexed positional printf specifier (%0$...)')

    if len(pos_only) != len(set(pos_only)):
        seen: set[int] = set()
        dups: list[str] = []
        for p in pos_only:
            if p in seen and f'%{p}$' not in dups:
                dups.append(f'%{p}$')
            seen.add(p)
        problems.append(f'duplicate positional printf specifier(s): {", ".join(dups)}')

    if pos_only and all(p >= 1 for p in pos_only):
        expected = set(range(1, max(pos_only) + 1))
        missing = sorted(expected - set(pos_only))
        if missing:
            gaps = ', '.join(f'%{idx}$' for idx in missing)
            problems.append(f'gapped positional printf specifier(s), missing: {gaps}')

    return problems


def _audit_entry(entry: polib.POEntry, loc: str) -> int:
    """Audit a single POT entry. Return error count."""
    errors = 0

    # 1. Trailing period parity between msgid and msgid_plural
    if entry.msgid_plural:
        id_dot = entry.msgid.endswith('.')
        plural_dot = entry.msgid_plural.endswith('.')
        if id_dot != plural_dot:
            print(
                f'ERROR [{loc}] Mismatched trailing "." between msgid and msgid_plural'
                f'\n  msgid:        {entry.msgid[:80]}'
                f'\n  msgid_plural: {entry.msgid_plural[:80]}'
            )
            errors += 1

    # 2. Balanced HTML tags in msgid and msgid_plural
    for field, text in (('msgid', entry.msgid), ('msgid_plural', entry.msgid_plural)):
        if not text:
            continue
        for problem in _check_html_balance(text):
            print(
                f'ERROR [{loc}] Unbalanced/malformed HTML in {field}: {problem}'
                f'\n  {field}: {text[:80]}'
            )
            errors += 1

    # 3. Literal '%' prose mistakenly flagged php-format (without no-php-format)
    if 'php-format' in entry.flags and 'no-php-format' not in entry.flags:
        combined = entry.msgid + ' ' + (entry.msgid_plural or '')
        cleaned = combined.replace('%%', '')
        if not _PLACEHOLDER_PARTS.search(cleaned):
            print(
                f'ERROR [{loc}] Entry flagged php-format contains no printf specifiers '
                f'(add /* xgettext:no-php-format */ in PHP source for literal "%")'
                f'\n  msgid: {entry.msgid[:80]}'
            )
            errors += 1

    # 4. Duplicate or malformed printf specifiers in source strings
    for field, text in (('msgid', entry.msgid), ('msgid_plural', entry.msgid_plural)):
        if not text:
            continue
        for problem in _check_source_format(text):
            print(
                f'ERROR [{loc}] Invalid printf specifier in {field}: {problem}'
                f'\n  {field}: {text[:80]}'
            )
            errors += 1

    return errors


def _entry_key(entry: polib.POEntry) -> tuple:
    return (entry.msgctxt, entry.msgid, entry.msgid_plural)


def _entry_signature(entry: polib.POEntry) -> tuple:
    return (tuple(sorted(entry.flags)),)


def base_entries(file_path: str, base: str) -> dict[tuple, tuple] | None:
    """Return POT entry signatures at base, or None if unreadable."""
    try:
        shown = subprocess.run(
            ['git', 'show', f'{base}:{file_path}'],
            capture_output=True, check=False,
        )
    except OSError:
        return None
    if shown.returncode != 0:
        return None
    with tempfile.TemporaryDirectory() as directory:
        copy = Path(directory) / 'base.pot'
        copy.write_bytes(shown.stdout)
        try:
            po = polib.pofile(str(copy))
        except (OSError, ValueError, UnicodeDecodeError):
            return None
    return {_entry_key(entry): _entry_signature(entry) for entry in po if not entry.obsolete}


def audit_pot_file(file_path: str, base: str | None = None) -> int:
    """Return the number of errors found in a single .pot file."""
    try:
        po = polib.pofile(file_path)
    except Exception as exc:
        print(f'ERROR: cannot parse {file_path}: {exc}')
        return 1

    previous = base_entries(file_path, base) if base else None
    errors = 0

    for entry in po:
        if entry.obsolete or not entry.msgid:
            continue
        if previous is not None and previous.get(_entry_key(entry)) == _entry_signature(entry):
            continue
        loc = f'{file_path}:{entry.linenum}'
        errors += _audit_entry(entry, loc)

    return errors


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description='Audit Cacti gettext .pot template files.')
    parser.add_argument('--base', help='audit only entries added or changed since this commit')
    parser.add_argument('files', nargs='+', metavar='file.pot')
    args = parser.parse_args(argv)

    total = sum(audit_pot_file(f, args.base) for f in args.files)

    if total:
        print(f'\n{total} error(s) found across {len(args.files)} file(s).')
        return 1
    print(f'OK: {len(args.files)} file(s) passed.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
