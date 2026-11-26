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


def _split_args(argv: list[str]) -> tuple[list[str], list[str]]:
    """Split msgcheck CLI arguments into (options, file_args)."""
    options: list[str] = []
    files: list[str] = []
    idx = 0
    end_of_opts = False

    while idx < len(argv):
        arg = argv[idx]
        if end_of_opts:
            files.append(arg)
        elif arg == '--':
            end_of_opts = True
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

    return options, files


def _rewrite_paths(text: str, replacements: list[tuple[str, str]]) -> str:
    for temp_path, orig_path in replacements:
        text = text.replace(temp_path, orig_path)
    return text


def run_msgcheck(
    argv: list[str],
    msgcheck_cmd: str = 'msgcheck',
) -> tuple[int, str, str]:
    """Run msgcheck, staging normalized copies for Devanagari .po catalogs."""
    options, file_args = _split_args(argv)
    forwarded_files: list[str] = []
    replacements: list[tuple[str, str]] = []

    with tempfile.TemporaryDirectory(prefix='cacti-msgcheck-') as temp_dir:
        temp_root = Path(temp_dir)
        for index, raw_path in enumerate(file_args):
            path = Path(raw_path)
            if path.is_file() and path.suffix == '.po':
                content = path.read_text(encoding='utf-8')
                if is_devanagari_po(content):
                    staged_dir = temp_root / str(index)
                    staged_dir.mkdir()
                    staged_file = staged_dir / path.name
                    staged_file.write_text(
                        normalize_devanagari_danda(content),
                        encoding='utf-8',
                    )
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
