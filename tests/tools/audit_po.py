#!/usr/bin/env python3
"""
audit_po.py - Strict HTML tag and positional placeholder audit for Cacti .po files.

Checks:
  1. HTML tag count parity between msgid and msgstr.
  2. Positional placeholder usage in multi-variable strings (%1$s, not bare %s).

Usage:
  python3 tests/tools/audit_po.py locales/po/*.po

Exit codes:
  0 - no errors
  1 - one or more errors found
"""

import polib
import re
import sys


_HTML_TAG = re.compile(r'<[^>]+>')

# Match printf-style placeholders with optional positional index, flags,
# width, precision, and length modifiers. Ignores literal '%%'.
# Examples matched: %s, %d, %0.2f, %1$0.2f, %02d, %-10s
_PLACEHOLDER = re.compile(
    r'%(?!%)'               # % not followed by % (ignore literal '%%')
    r'(?:[0-9]+\$)?'        # optional positional index, e.g. 1$
    r"[#0\- +']*"           # optional flags
    r'(?:[0-9]+|\*)?'       # optional width
    r'(?:\.(?:[0-9]+|\*))?'  # optional precision
    r'(?:hh|h|ll|l|L|z|j|t)?'  # optional length modifier
    r'[a-zA-Z]'             # conversion specifier
)

# Positional: must include %<n>$ prefix
_POSITIONAL = re.compile(
    r'%(?!%)'
    r'[0-9]+\$'             # required positional index
    r"[#0\- +']*"
    r'(?:[0-9]+|\*)?'
    r'(?:\.(?:[0-9]+|\*))?'
    r'(?:hh|h|ll|l|L|z|j|t)?'
    r'[a-zA-Z]'
)


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

    # 2. Positional placeholder requirement for multi-variable strings
    src_fmt = _PLACEHOLDER.findall(msgid)
    tgt_fmt = _PLACEHOLDER.findall(msgstr)

    if len(src_fmt) > 1:
        positional = _POSITIONAL.findall(msgstr)
        if tgt_fmt and len(positional) < len(tgt_fmt):
            print(
                f'ERROR [{loc}] Non-positional placeholders in multi-variable string '
                f'(use %1$s, %2$s ... to allow reordering)'
                f'\n  msgid:  {msgid[:80]}'
                f'\n  msgstr: {msgstr[:80]}'
            )
            errors += 1

    # 3. Symmetric check: msgstr carries placeholders that msgid no longer has.
    # This catches stale translations where English dropped a format specifier
    # but the translation was not updated to match.
    if tgt_fmt and not src_fmt:
        print(
            f'ERROR [{loc}] msgstr has format specifier(s) but msgid has none '
            f'(English dropped this placeholder; translation needs clearing)'
            f'\n  msgid:  {msgid[:80]}'
            f'\n  msgstr: {msgstr[:80]}'
        )
        errors += 1

    return errors


def audit_po_file(file_path: str) -> int:
    """Return the number of errors found in a single .po file."""
    try:
        po = polib.pofile(file_path)
    except Exception as exc:
        print(f'ERROR: cannot parse {file_path}: {exc}')
        return 1

    errors = 0

    for entry in po:
        if entry.obsolete:
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


def main() -> None:
    if len(sys.argv) < 2:
        print(f'Usage: {sys.argv[0]} <file.po> [file.po ...]', file=sys.stderr)
        sys.exit(1)

    total = sum(audit_po_file(f) for f in sys.argv[1:])

    if total:
        print(f'\n{total} error(s) found across {len(sys.argv) - 1} file(s).')
        sys.exit(1)
    else:
        print(f'OK: {len(sys.argv) - 1} file(s) passed.')
        sys.exit(0)


if __name__ == '__main__':
    main()
