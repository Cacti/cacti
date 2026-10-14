#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
umask 077

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
export CACTI_PROFILE_OUTPUT="${CACTI_PROFILE_OUTPUT:-$(mktemp -d "${TMPDIR:-/tmp}/cacti-profile.XXXXXX")}"
mkdir -p "$CACTI_PROFILE_OUTPUT"
CACTI_PROFILE_OUTPUT="$(cd "$CACTI_PROFILE_OUTPUT" && pwd)"
export CACTI_PROFILE_OUTPUT
# Do not combine old browser timings with a fresh server profile.
if [[ -e "$CACTI_PROFILE_OUTPUT/metadata.json" || -e "$CACTI_PROFILE_OUTPUT/fixture.json" ]]; then
	echo 'Select a new output directory for each run.' >&2
	exit 1
fi
export HOST_PORT="${HOST_PORT:-18093}"
export CACTI_BASE_URL="http://127.0.0.1:$HOST_PORT/"
export CACTI_PROFILE_DISPOSABLE=1
PROJECT="cacti_profile_$$"
COMPOSE=(docker compose -p "$PROJECT" -f tests/e2e/docker-compose.yml -f tests/performance/compose.profile.yml)
cleanup() { "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT

mise exec node@22 -- npm ci --prefix tests/e2e --ignore-scripts
if ! mise exec node@22 -- node -e 'const {chromium}=require("./tests/e2e/node_modules/@playwright/test"); process.exit(require("node:fs").existsSync(chromium.executablePath()) ? 0 : 1);'; then
	mise exec node@22 -- tests/e2e/node_modules/.bin/playwright install chromium
fi
"${COMPOSE[@]}" build cacti
"${COMPOSE[@]}" up -d --wait --wait-timeout 180
"${COMPOSE[@]}" exec -T cacti touch /tmp/cacti-profile-disposable
"${COMPOSE[@]}" exec -T cacti php tests/performance/profile-seed.php
"${COMPOSE[@]}" exec -T cacti chown -R www-data:www-data rra cache

# No authenticated browser storage, HTTP bodies or credentials go into metadata.
mise exec python@3.12 -- python - "$CACTI_PROFILE_OUTPUT" <<'PY'
import hashlib
import json
import platform
import subprocess
import sys
from pathlib import Path

root = Path.cwd()
digest = hashlib.sha256()
for directory in ('include', 'lib', 'tests/performance', 'tests/e2e'):
    for path in sorted((root / directory).rglob('*')):
        if path.is_file() and not any(part in ('vendor', 'node_modules', '__pycache__') for part in path.parts) and path.suffix in ('.php', '.js', '.mjs', '.css', '.py', '.sh', '.yml'):
            digest.update(str(path.relative_to(root)).encode())
            digest.update(path.read_bytes())
for path in sorted(root.glob('*.php')):
    digest.update(path.name.encode())
    digest.update(path.read_bytes())
metadata = {'revision': subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip(),
            'source_sha256': digest.hexdigest(), 'machine': platform.node(), 'platform': platform.platform(),
            'load_average': list(__import__('os').getloadavg()),
            'diagnostic_only': True}
(Path(sys.argv[1]) / 'metadata.json').write_text(json.dumps(metadata, indent=2) + '\n')
PY

for scenario in render boost; do
	"${COMPOSE[@]}" exec -T cacti php \
		-d xdebug.mode=profile -d xdebug.start_with_request=yes \
		-d xdebug.output_dir=/profiles -d "xdebug.profiler_output_name=cachegrind.out.$scenario" \
		tests/performance/profile-graphs.php "$scenario" > "$CACTI_PROFILE_OUTPUT/$scenario.json"
done
# Browser runs use real authentication, AJAX navigation, CSS and RRD images.
# The realtime transport uses controlled response delays/errors, without SNMP.
mise exec node@22 -- node tests/e2e/profile-client.mjs
mise exec python@3.12 -- python tests/performance/profile-report.py "$CACTI_PROFILE_OUTPUT"
printf 'Profiling results: %s\n' "$CACTI_PROFILE_OUTPUT"
