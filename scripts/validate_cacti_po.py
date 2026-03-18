#!/usr/bin/env python3
"""Cacti PO file validator -- catches format string errors before runtime."""
from __future__ import annotations
import re, shutil, subprocess, sys, ast
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional, Set

PERCENT_RE = re.compile(r'%(?:\((?P<mapping>[^\)]+)\))?[#0\- +\']*(?:\d+|\*)?(?:\.(?:\d+|\*))?[hlL]?(?P<type>[diouxXeEfFgGcrs%])', re.VERBOSE)

@dataclass
class Entry:
    comments: List[str] = field(default_factory=list)
    flags: Set[str] = field(default_factory=set)
    msgid: Optional[str] = None
    msgid_plural: Optional[str] = None
    msgstr: Dict[int, str] = field(default_factory=dict)
    lineno: int = 0

def po_unquote(s: str) -> str:
    return ast.literal_eval(s.strip())

def parse_po(path: Path) -> List[Entry]:
    lines = path.read_text(encoding="utf-8").splitlines()
    entries, cur = [], Entry()
    active_field, active_index = None, None
    def flush():
        nonlocal cur, active_field, active_index
        if cur.msgid is not None or cur.msgstr or cur.comments:
            entries.append(cur)
        cur.__init__(); active_field = active_index = None
    for lineno, line in enumerate(lines, 1):
        line = line.rstrip()
        if not line.strip(): flush(); continue
        if line.startswith("#,"): cur.comments.append(line); cur.flags.update(x.strip() for x in line[2:].split(",") if x.strip()); continue
        if line.startswith("#"): cur.comments.append(line); continue
        if line.startswith("msgid_plural "): cur.lineno = cur.lineno or lineno; cur.msgid_plural = po_unquote(line[len("msgid_plural "):]); active_field = "msgid_plural"; continue
        if line.startswith("msgid "): cur.lineno = cur.lineno or lineno; cur.msgid = po_unquote(line[len("msgid "):]); active_field = "msgid"; continue
        if line.startswith("msgstr["): close = line.index("]"); idx = int(line[len("msgstr["):close]); cur.msgstr[idx] = po_unquote(line[close+1:].strip()); active_field = "msgstr"; active_index = idx; continue
        if line.startswith("msgstr "): cur.lineno = cur.lineno or lineno; cur.msgstr[0] = po_unquote(line[len("msgstr "):]); active_field = "msgstr"; active_index = 0; continue
        if line.startswith('"'):
            piece = po_unquote(line)
            if active_field == "msgid": cur.msgid = (cur.msgid or "") + piece
            elif active_field == "msgid_plural": cur.msgid_plural = (cur.msgid_plural or "") + piece
            elif active_field == "msgstr" and active_index is not None: cur.msgstr[active_index] = cur.msgstr.get(active_index, "") + piece
            continue
    flush()
    return entries

def percent_tokens(text: str) -> List[str]:
    return sorted(m.group(0) for m in PERCENT_RE.finditer(text) if m.group(0) != "%%")

def validate_file(path: Path) -> List[str]:
    errors = []
    msgfmt = shutil.which("msgfmt")
    if msgfmt:
        proc = subprocess.run([msgfmt, "-c", "--check-format", "-o", "/dev/null", str(path)], capture_output=True, text=True)
        if proc.returncode != 0: errors.append(f"{path}: msgfmt failed:\n{proc.stderr.strip()}")
    try:
        entries = parse_po(path)
    except Exception as exc:
        errors.append(f"{path}: parse failure: {exc}"); return errors
    for entry in entries:
        if not entry.msgid: continue
        src = percent_tokens(entry.msgid)
        if not src: continue
        for idx, tr in sorted(entry.msgstr.items()):
            if not tr: continue
            tr_tokens = percent_tokens(tr)
            if src != tr_tokens:
                errors.append(f"{path}:{entry.lineno}: msgstr[{idx}] format mismatch src={src} tr={tr_tokens}")
    return errors

def main(argv: List[str]) -> int:
    files = [Path(a) for a in argv[1:] if a.endswith(".po")]
    all_errors = []
    for path in files:
        if path.exists(): all_errors.extend(validate_file(path))
    if all_errors:
        for err in all_errors: print(f"- {err}", file=sys.stderr)
        return 1
    return 0

if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
