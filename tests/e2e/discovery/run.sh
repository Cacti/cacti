#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT"
# Resolve the actual executable because child shells can reorder mise's PATH.
if command -v mise >/dev/null 2>&1; then
  NODE_BINARY="$(mise exec node@22 -- node -p 'process.execPath')"
  NODE_BIN_DIR="$(dirname "$NODE_BINARY")"
  PATH="$NODE_BIN_DIR:$PATH"
  export PATH
fi
[[ "$(node --version)" == v22.* ]] || { echo 'Node 22 is required' >&2; exit 2; }
node -p '"Discovery runner Node: " + process.version + " (" + process.execPath + ")"'
export HOST_PORT="${HOST_PORT:-18094}"
export DISCOVERY_COMPOSE_PROJECT="${DISCOVERY_COMPOSE_PROJECT:-cacti-discovery-lts-review}"
[[ "$DISCOVERY_COMPOSE_PROJECT" =~ ^cacti-discovery-lts-[a-z0-9-]+$ ]] || exit 2
DC=(docker compose -p "$DISCOVERY_COMPOSE_PROJECT" -f "$ROOT/tests/e2e/discovery/compose.yml")
cleanup() {
  status=$?
  if ((status)); then
    "${DC[@]}" logs --no-color > /tmp/"${DISCOVERY_COMPOSE_PROJECT}"-failure.log 2>&1 || true
  fi
  "${DC[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
  exit "$status"
}
trap cleanup EXIT
"${DC[@]}" up -d --build --wait --wait-timeout 180
for source in lib/api_automation.php poller_automation.php include/layout.js lib/html_form.php; do
  expected="$(node -e 'process.stdout.write(require("node:crypto").createHash("sha256").update(require("node:fs").readFileSync(process.argv[1])).digest("hex"))' "$source")"
  # PHP reads its own $argv; the shell must leave that expression literal.
  # shellcheck disable=SC2016
  actual="$("${DC[@]}" exec -T cacti php -r 'echo hash_file("sha256", $argv[1]);' "$source")"
  [[ "$expected" == "$actual" ]] || { echo "Stale Docker source: $source" >&2; exit 1; }
  printf '%s  %s\n' "$actual" "$source"
done
"${DC[@]}" exec -T cacti php cli/import_package.php --filename=install/templates/Generic_SNMP_Device.xml.gz --with-profile
"${DC[@]}" exec -T cacti php tests/e2e/discovery/probe.php seed >/dev/null
for ip in 10 11 12; do
  "${DC[@]}" exec -T cacti snmpget -v2c -c discovery-fixture -t 1 -r 1 "172.30.251.$ip:1161" .1.3.6.1.2.1.1.1.0 >/dev/null
done
"${DC[@]}" exec -T cacti snmpget -v2c -c discovery-fixture -t 1 -r 1 172.30.251.13:1161 .1.3.6.1.2.1.1.2.0 >/dev/null
"${DC[@]}" exec -T cacti snmpget -v3 -l authPriv -u discovery -a SHA -A discovery-auth-fixture -x AES -X discovery-priv-fixture -t 1 -r 1 172.30.250.255:1161 .1.3.6.1.2.1.1.1.0 >/dev/null
cd tests/e2e
npm ci
npx playwright install chromium
CACTI_BASE_URL="http://127.0.0.1:$HOST_PORT" npx playwright test --config=discovery/playwright.config.ts
node - <<'JS'
const fs = require('node:fs');
const report = fs.readFileSync('test-results/discovery.xml', 'utf8');
const counts = [...report.matchAll(/<testsuite\b[^>]*\btests="(\d+)"/g)].map(m => Number(m[1]));
if (!counts.length || counts.reduce((a,b) => a+b, 0) !== 3 || /(?:skipped|failures|errors)="0*[1-9]/.test(report)) {
  throw new Error('LTS discovery E2E must execute all three scenarios without skips');
}
JS
