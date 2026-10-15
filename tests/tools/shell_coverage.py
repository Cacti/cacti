"""Instrument unchanged shell fixture copies when coverage is requested.

Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
"""
import hashlib
import json
import os
from pathlib import Path
import shutil
import uuid

ROOT = Path(__file__).resolve().parents[2]


def shell_command(script, original, interpreter='/bin/sh'):
    script, original = Path(script).resolve(), Path(original).resolve()
    if not os.getenv('CACTI_SHELL_COVERAGE'):
        return [interpreter, str(script)]
    if script.read_bytes() != original.read_bytes():
        raise ValueError('Shell fixture must retain the original source bytes')
    kcov = shutil.which('kcov')
    if not kcov:
        raise RuntimeError('Shell coverage requires kcov')
    directory = Path(os.environ['CACTI_SHELL_COVERAGE']) / uuid.uuid4().hex
    directory.mkdir(parents=True)
    (directory / 'source.json').write_text(json.dumps({
        'source': str(original.relative_to(ROOT)),
        'native_source': str(script),
        'sha256': hashlib.sha256(original.read_bytes()).hexdigest(),
    }))
    return [kcov, '--bash-parser=/bin/bash', '--cobertura-only',
            '--include-pattern=' + str(script),
            '--replace-src-path=' + str(script) + ':' + str(original),
            '--strip-path=' + str(ROOT), str(directory), str(script)]
