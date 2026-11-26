#!/usr/bin/env python3
"""Run msgcheck with Devanagari purna viram (।, U+0964) support.

msgcheck 4.2.0 only maps '.' to '。' (ja, zh) in end-punctuation checks and
rejects Devanagari purna viram ('।') in Hindi and other Devanagari catalogs.
For Devanagari PO files, this wrapper stages a temporary copy that normalizes
only the terminal '।' of each msgstr/msgstr[n] value to '.' while preserving
line numbers and rewriting reported paths back to the original catalog path.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""

from __future__ import annotations

from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile

DEVANAGARI_LANGUAGES = frozenset({'hi', 'mr', 'ne', 'sa'})
DEVANAGARI_DANDA = '\u0964'

_LANGUAGE_RE = re.compile(
    r'"Language:\s*([A-Za-z]+)(?:[-_][^"\\]*)?\\n"',
    re.IGNORECASE,
)
_MSGSTR_RE = re.compile(r'^msgstr(?:\[\d+\])?\s+"(.*)"$')

# msgcheck options that consume the next argv token as their value.
_OPTIONS_WITH_VALUE = frozenset({
    '-s', '--spelling',
    '-d', '--dicts',
    '-P', '--pwl',
    '-o', '--output-format',
})

_PO_STRING_RE = re.compile(r'^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?)\s+"(.*)"$')
_PO_CONT_RE = re.compile(r'^"(.*)"$')
_FUZZY_FLAG_RE = re.compile(r'(^#,\s*|,\s*)fuzzy\b')


def _parse_block_entry(block_lines: list[str]) -> tuple[tuple[str, str, str], tuple[bool, dict[str, str]]] | None:
    """Extract (key, value) from a single PO block, or None for comments/obsolete/header."""
    fuzzy = False
    fields: dict[str, list[str]] = {}
    current: str | None = None

    for raw in block_lines:
        stripped = raw.rstrip('\r\n')
        if stripped.startswith('#~'):
            return None
        if stripped.startswith('#,'):
            if 'fuzzy' in stripped.split(','):
                fuzzy = True
            elif re.search(r'\bfuzzy\b', stripped):
                fuzzy = True
            continue
        if stripped.startswith('#') or not stripped:
            continue
        match = _PO_STRING_RE.match(stripped)
        if match is not None:
            current = match.group(1)
            fields.setdefault(current, []).append(match.group(2))
            continue
        cont = _PO_CONT_RE.match(stripped)
        if cont is not None and current is not None:
            fields[current].append(cont.group(1))

    if 'msgid' not in fields:
        return None
    msgid = ''.join(fields['msgid'])
    if msgid == '':
        return None
    msgctxt = ''.join(fields.get('msgctxt', []))
    msgid_plural = ''.join(fields.get('msgid_plural', []))
    msgstrs = {
        key: ''.join(parts)
        for key, parts in fields.items()
        if key == 'msgstr' or key.startswith('msgstr[')
    }
    return (msgctxt, msgid, msgid_plural), (fuzzy, msgstrs)


def _blank_block_translations(block_lines: list[str]) -> list[str]:
    """Clear msgstr values and fuzzy flags in a block while keeping exact line count."""
    out: list[str] = []
    in_msgstr = False

    for raw in block_lines:
        stripped = raw.rstrip('\r\n')
        newline = raw[len(stripped):]
        if stripped.startswith('#,'):
            cleaned = re.sub(r'\bfuzzy\b', '', stripped)
            cleaned = re.sub(r',\s*,', ',', cleaned)
            cleaned = re.sub(r'^#,\s*,?\s*', '#, ', cleaned).rstrip(', ')
            out.append(('#' if cleaned == '#,' else cleaned) + newline)
            in_msgstr = False
            continue
        match = _PO_STRING_RE.match(stripped)
        if match is not None:
            key = match.group(1)
            if key == 'msgstr' or key.startswith('msgstr['):
                in_msgstr = True
                out.append(f'{key} ""{newline}')
            else:
                in_msgstr = False
                out.append(raw)
            continue
        if in_msgstr and _PO_CONT_RE.match(stripped):
            out.append(f'""{newline}')
            continue
        in_msgstr = False
        out.append(raw)

    return out


def _split_po_blocks(content: str) -> list[list[str]]:
    """Split PO text into alternating entry and blank-line blocks preserving every line."""
    blocks: list[list[str]] = []
    current: list[str] = []
    for line in content.splitlines(keepends=True):
        if not line.strip():
            if current:
                blocks.append(current)
                current = []
            blocks.append([line])
        else:
            current.append(line)
    if current:
        blocks.append(current)
    return blocks


def filter_po_to_changed_entries(content: str, base_content: str) -> str:
    """Keep translations only for entries added or modified since base_content.

    Unchanged entries have their msgstr values cleared and fuzzy flag removed
    while preserving exact line numbers and valid PO syntax so msgcheck audits
    only entries touched by the change.
    """
    base_map: dict[tuple[str, str, str], tuple[bool, dict[str, str]]] = {}
    for block in _split_po_blocks(base_content):
        parsed = _parse_block_entry(block)
        if parsed is not None:
            base_map[parsed[0]] = parsed[1]

    result: list[str] = []
    for block in _split_po_blocks(content):
        parsed = _parse_block_entry(block)
        if parsed is not None and base_map.get(parsed[0]) == parsed[1]:
            result.extend(_blank_block_translations(block))
        else:
            result.extend(block)
    return ''.join(result)


def _read_git_base_file(base: str, file_path: str) -> str | None:
    """Return file content at git commit base, or None if unavailable."""
    try:
        proc = subprocess.run(
            ['git', 'show', f'{base}:{file_path}'],
            capture_output=True,
            check=False,
        )
    except OSError:
        return None
    if proc.returncode != 0:
        return None
    try:
        return proc.stdout.decode('utf-8')
    except UnicodeDecodeError:
        return None


def is_devanagari_po(content: str) -> bool:
    """Return True when the PO header declares a Devanagari language."""
    match = _LANGUAGE_RE.search(content)
    if match is None:
        return False
    return match.group(1).lower() in DEVANAGARI_LANGUAGES


def normalize_devanagari_danda(content: str) -> str:
    """Replace a terminal Devanagari purna viram in msgstr values with '.'.

    Line counts and all non-terminal characters, msgid strings, headers, and
    comments remain unchanged so msgcheck line numbers stay exact.
    """
    lines = content.splitlines(keepends=True)
    in_msgstr = False
    last_nonempty_idx: int | None = None

    def flush() -> None:
        if not in_msgstr or last_nonempty_idx is None:
            return
        raw = lines[last_nonempty_idx]
        stripped = raw.rstrip('\r\n')
        newline = raw[len(stripped):]
        if stripped.endswith(f'{DEVANAGARI_DANDA}"'):
            lines[last_nonempty_idx] = stripped[:-2] + '."' + newline

    for idx, line in enumerate(lines):
        stripped = line.rstrip('\r\n')
        match = _MSGSTR_RE.match(stripped)
        if match is not None:
            flush()
            in_msgstr = True
            last_nonempty_idx = idx if match.group(1) else None
        elif in_msgstr and stripped.startswith('"') and stripped.endswith('"'):
            if len(stripped) > 2:
                last_nonempty_idx = idx
        else:
            flush()
            in_msgstr = False
            last_nonempty_idx = None

    flush()
    return ''.join(lines)


def _split_args(argv: list[str]) -> tuple[list[str], list[str], str | None]:
    """Split msgcheck CLI arguments into (options, file_args, base_ref)."""
    options: list[str] = []
    files: list[str] = []
    base_ref: str | None = None
    idx = 0
    end_of_opts = False

    while idx < len(argv):
        arg = argv[idx]
        if end_of_opts:
            files.append(arg)
        elif arg == '--':
            end_of_opts = True
        elif arg == '--base':
            if idx + 1 < len(argv):
                idx += 1
                base_ref = argv[idx]
        elif arg.startswith('--base='):
            base_ref = arg.split('=', 1)[1]
        elif arg in _OPTIONS_WITH_VALUE:
            options.append(arg)
            if idx + 1 < len(argv):
                idx += 1
                options.append(argv[idx])
        elif arg.startswith('-') and arg != '-':
            options.append(arg)
        else:
            files.append(arg)
        idx += 1

    return options, files, base_ref


def _rewrite_paths(text: str, replacements: list[tuple[str, str]]) -> str:
    for temp_path, orig_path in replacements:
        text = text.replace(temp_path, orig_path)
    return text


def run_msgcheck(
    argv: list[str],
    msgcheck_cmd: str = 'msgcheck',
) -> tuple[int, str, str]:
    """Run msgcheck, staging filtered or Devanagari-normalized copies as needed."""
    options, file_args, base_ref = _split_args(argv)
    forwarded_files: list[str] = []
    replacements: list[tuple[str, str]] = []

    with tempfile.TemporaryDirectory(prefix='cacti-msgcheck-') as temp_dir:
        temp_root = Path(temp_dir)
        for index, raw_path in enumerate(file_args):
            path = Path(raw_path)
            if path.is_file() and path.suffix == '.po':
                content = path.read_text(encoding='utf-8')
                staged_content = content
                needs_stage = False

                if base_ref:
                    base_content = _read_git_base_file(base_ref, raw_path)
                    if base_content is not None:
                        staged_content = filter_po_to_changed_entries(staged_content, base_content)
                        needs_stage = True

                if is_devanagari_po(staged_content):
                    staged_content = normalize_devanagari_danda(staged_content)
                    needs_stage = True

                if needs_stage:
                    staged_dir = temp_root / str(index)
                    staged_dir.mkdir()
                    staged_file = staged_dir / path.name
                    staged_file.write_text(staged_content, encoding='utf-8')
                    forwarded_files.append(str(staged_file))
                    resolved_orig = str(path.resolve())
                    replacements.append((str(staged_file.resolve()), resolved_orig))
                    replacements.append((str(staged_file), raw_path))
                    continue
            forwarded_files.append(raw_path)

        resolved_cmd = shutil.which(msgcheck_cmd)
        if resolved_cmd is None:
            return 127, '', f'ERROR: {msgcheck_cmd} not found in PATH.\n'

        cmd = [resolved_cmd, *options, *forwarded_files]
        try:
            proc = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                check=False,
            )
        except FileNotFoundError:
            return 127, '', f'ERROR: {msgcheck_cmd} not found in PATH.\n'

    stdout = _rewrite_paths(proc.stdout, replacements)
    stderr = _rewrite_paths(proc.stderr, replacements)
    return proc.returncode, stdout, stderr


def main(argv: list[str] | None = None) -> int:
    args = sys.argv[1:] if argv is None else argv
    rc, stdout, stderr = run_msgcheck(args)
    if stdout:
        sys.stdout.write(stdout)
    if stderr:
        sys.stderr.write(stderr)
    return rc


if __name__ == '__main__':
    sys.exit(main())
