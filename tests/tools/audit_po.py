#!/usr/bin/env python3
"""
audit_po.py - Strict HTML tag and placeholder audit for Cacti .po files.

Checks:
  1. HTML tag count parity between msgid and msgstr.
  2. Printf-style placeholder count and conversion-specifier parity between
     msgid and msgstr (ignoring literal '%%' and prose percent signs).
  3. Consistent placeholder style in msgstr: either all bare in-order
     (%s, %d matching msgid) or all positional (%1$s, %2$d / reordered
     %2$d, %1$s with valid 1-based indices). Mixed positional and bare
     placeholders, out-of-range indices, missing/extra placeholders, and
     mismatched conversion specifiers are rejected.

Usage:
  python3 tests/tools/audit_po.py locales/po/*.po
  python3 tests/tools/audit_po.py --base origin/develop locales/po/de-DE.po

With --base, only entries added or changed since that commit are audited, so
existing problems elsewhere in a catalog do not fail an unrelated change. If
the base copy cannot be read (new file, unknown ref), every entry is audited.

Exit codes:
  0 - no errors
  1 - one or more errors found
"""

import argparse
from pathlib import Path
import re
import subprocess
import sys
import tempfile

import polib


_HTML_TAG = re.compile(r'<[^>]+>')

# Match printf-style placeholders with optional positional index, flags,
# width, precision, and length modifiers. Ignores literal '%%'.
# Examples matched: %s, %d, %0.2f, %1$0.2f, %02d, %-10s
# The space flag and non-printf letters are left out on purpose: prose such as
# "100% of" or French "50 % des" is not a placeholder.
_CONVERSION = r'[bcdeEfFgGosuxX]'
_PLACEHOLDER = re.compile(
    r'(?<!%)%(?!%)'         # % not part of literal '%%'
    r'(?:[0-9]+\$)?'        # optional positional index, e.g. 1$
    r"[#0\-+']*"            # optional flags
    r'(?:[0-9]+|\*)?'       # optional width
    r'(?:\.(?:[0-9]+|\*))?'  # optional precision
    r'(?:hh|h|ll|l|L|z|j|t)?'  # optional length modifier
    + _CONVERSION           # conversion specifier
)

# Positional: must include %<n>$ prefix
_POSITIONAL = re.compile(
    r'(?<!%)%(?!%)'
    r'[0-9]+\$'             # required positional index
    r"[#0\-+']*"
    r'(?:[0-9]+|\*)?'
    r'(?:\.(?:[0-9]+|\*))?'
    r'(?:hh|h|ll|l|L|z|j|t)?'
    + _CONVERSION
)

# Capturing variant used internally to extract (positional_index, conversion).
_PLACEHOLDER_PARTS = re.compile(
    r'%(?!%)'
    r'(?:([0-9]+)\$)?'
    r"[#0\-+']*"
    r'(?:[0-9]+|\*)?'
    r'(?:\.(?:[0-9]+|\*))?'
    r'(?:hh|h|ll|l|L|z|j|t)?'
    r'(' + _CONVERSION + r')'
)


def _parse_placeholders(text: str) -> 'list[tuple[int | None, str, str]]':
    """Return (positional_index_or_None, conversion, raw) for each placeholder."""
    # Consume literal percent pairs first so '%%s' is literal and '%%%s' yields '%s'.
    cleaned = text.replace('%%', '')
    parsed: 'list[tuple[int | None, str, str]]' = []
    for match in _PLACEHOLDER_PARTS.finditer(cleaned):
        pos = int(match.group(1)) if match.group(1) is not None else None
        parsed.append((pos, match.group(2), match.group(0)))
    return parsed


def _expected_arg_types(
    src: 'list[tuple[int | None, str, str]]',
) -> 'dict[int, str]':
    """Map 1-based argument positions in msgid to their conversion specifier."""
    types: 'dict[int, str]' = {}
    seq = 1
    for pos, conv, _ in src:
        if pos is None:
            idx = seq
            seq += 1
        else:
            idx = pos
        types[idx] = conv
    return types


def _audit_pair(msgid: str, msgstr: str, loc: str) -> int:
    """Audit a single msgid/msgstr pair. Return error count."""
    errors = 0

    # 1. HTML tag count parity
    tags_id = _HTML_TAG.findall(msgid)
    tags_str = _HTML_TAG.findall(msgstr)
    if len(tags_id) != len(tags_str):
        print(
            f'ERROR [{loc}] HTML tag count mismatch: '
            f'msgid has {len(tags_id)}, msgstr has {len(tags_str)}'
            f'\n  msgid:  {msgid[:80]}'
            f'\n  msgstr: {msgstr[:80]}'
        )
        errors += 1

    # 2. Printf-style placeholder parity and style consistency
    src = _parse_placeholders(msgid)
    tgt = _parse_placeholders(msgstr)

    # Symmetric check: msgstr carries placeholders that msgid does not have.
    if tgt and not src:
        print(
            f'ERROR [{loc}] msgstr has format specifier(s) but msgid has none '
            f'(English dropped this placeholder; translation needs clearing)'
            f'\n  msgid:  {msgid[:80]}'
            f'\n  msgstr: {msgstr[:80]}'
        )
        return errors + 1

    # Missing placeholders: msgid has format specifiers but msgstr has none.
    if src and not tgt:
        print(
            f'ERROR [{loc}] msgstr is missing format specifier(s): '
            f'msgid has {len(src)}, msgstr has 0'
            f'\n  msgid:  {msgid[:80]}'
            f'\n  msgstr: {msgstr[:80]}'
        )
        return errors + 1

    if not src:
        return errors

    src_types = _expected_arg_types(src)
    positional_count = sum(1 for pos, _, _ in tgt if pos is not None)

    if 0 < positional_count < len(tgt):
        print(
            f'ERROR [{loc}] Mixed positional and non-positional placeholders '
            f'in msgstr (use either all bare in-order or all positional %1$s, %2$s ...)'
            f'\n  msgid:  {msgid[:80]}'
            f'\n  msgstr: {msgstr[:80]}'
        )
        return errors + 1

    if positional_count == len(tgt):
        max_idx = max(src_types)
        out_of_range = [raw for pos, _, raw in tgt if pos is None or pos < 1 or pos not in src_types]
        if out_of_range:
            print(
                f'ERROR [{loc}] Out-of-range positional placeholder(s) '
                f'{", ".join(out_of_range)} (expected 1..{max_idx})'
                f'\n  msgid:  {msgid[:80]}'
                f'\n  msgstr: {msgstr[:80]}'
            )
            errors += 1

        type_mismatches = [
            f'{raw} (expected %{pos}${src_types[pos]})'
            for pos, conv, raw in tgt
            if pos is not None and pos in src_types and conv != src_types[pos]
        ]
        if type_mismatches:
            print(
                f'ERROR [{loc}] Positional placeholder type mismatch: '
                f'{", ".join(type_mismatches)}'
                f'\n  msgid:  {msgid[:80]}'
                f'\n  msgstr: {msgstr[:80]}'
            )
            errors += 1

        referenced = {pos for pos, _, _ in tgt if pos is not None}
        missing = sorted(set(src_types) - referenced)
        if missing:
            missing_desc = ', '.join(f'%{idx}${src_types[idx]}' for idx in missing)
            print(
                f'ERROR [{loc}] Missing positional placeholder(s): {missing_desc}'
                f'\n  msgid:  {msgid[:80]}'
                f'\n  msgstr: {msgstr[:80]}'
            )
            errors += 1

        return errors

    # All placeholders in msgstr are bare (non-positional): must match msgid
    # count and conversion-specifier order.
    if len(tgt) != len(src):
        print(
            f'ERROR [{loc}] Placeholder count mismatch: '
            f'msgid has {len(src)}, msgstr has {len(tgt)}'
            f'\n  msgid:  {msgid[:80]}'
            f'\n  msgstr: {msgstr[:80]}'
        )
        return errors + 1

    src_convs = [conv for _, conv, _ in src]
    tgt_convs = [conv for _, conv, _ in tgt]
    if tgt_convs != src_convs:
        print(
            f'ERROR [{loc}] Placeholder type/order mismatch: '
            f'msgid has [{", ".join("%" + c for c in src_convs)}], '
            f'msgstr has [{", ".join("%" + c for c in tgt_convs)}] '
            f'(use positional %1$s, %2$d ... when reordering)'
            f'\n  msgid:  {msgid[:80]}'
            f'\n  msgstr: {msgstr[:80]}'
        )
        errors += 1

    return errors


def _entry_key(entry: polib.POEntry) -> tuple:
    return (entry.msgctxt, entry.msgid, entry.msgid_plural)


def _entry_value(entry: polib.POEntry) -> tuple:
    return (entry.msgstr, tuple(sorted(entry.msgstr_plural.items())))


def base_entries(file_path: str, base: str) -> 'dict[tuple, tuple] | None':
    """Return the translations of file_path at base, or None if unreadable."""
    try:
        shown = subprocess.run(
            ['git', 'show', f'{base}:{file_path}'],
            capture_output=True, check=False,
        )
    except OSError:
        return None
    if shown.returncode != 0:
        return None
    # Parse from a file: polib splits string input with str.splitlines(),
    # which also breaks on U+2028 and other separators found in translations.
    with tempfile.TemporaryDirectory() as directory:
        copy = Path(directory) / 'base.po'
        copy.write_bytes(shown.stdout)
        try:
            po = polib.pofile(str(copy))
        except (OSError, ValueError, UnicodeDecodeError):
            return None
    return {_entry_key(entry): _entry_value(entry) for entry in po if not entry.obsolete}


def audit_po_file(file_path: str, base: 'str | None' = None) -> int:
    """Return the number of errors found in a single .po file."""
    try:
        po = polib.pofile(file_path)
    except Exception as exc:
        print(f'ERROR: cannot parse {file_path}: {exc}')
        return 1

    previous = base_entries(file_path, base) if base else None
    errors = 0

    for entry in po:
        if entry.obsolete:
            continue
        if previous is not None and previous.get(_entry_key(entry)) == _entry_value(entry):
            continue

        loc = f'{file_path}:{entry.linenum}'

        # Singular form
        if entry.msgstr:
            errors += _audit_pair(entry.msgid, entry.msgstr, loc)

        # Plural forms (msgstr_plural is a dict: index -> translation string)
        if entry.msgid_plural and entry.msgstr_plural:
            for idx, plural_str in sorted(entry.msgstr_plural.items()):
                if not plural_str:
                    continue
                # Use msgid_plural as the reference for plural forms
                source = entry.msgid_plural if idx > 0 else entry.msgid
                errors += _audit_pair(source, plural_str, f'{loc}[{idx}]')

    return errors


def main(argv: 'list[str] | None' = None) -> int:
    parser = argparse.ArgumentParser(description='Audit Cacti .po files.')
    parser.add_argument('--base', help='audit only entries changed since this commit')
    parser.add_argument('files', nargs='+', metavar='file.po')
    args = parser.parse_args(argv)

    total = sum(audit_po_file(f, args.base) for f in args.files)

    if total:
        print(f'\n{total} error(s) found across {len(args.files)} file(s).')
        return 1
    print(f'OK: {len(args.files)} file(s) passed.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
