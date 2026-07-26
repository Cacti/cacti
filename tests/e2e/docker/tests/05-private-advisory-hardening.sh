#!/usr/bin/env bash
# Runs Docker-backed private advisory hardening checks inside cacti-master.
set -euo pipefail
IFS=$'\n\t'

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

PROBE_OUT=$("${DC[@]}" exec -T cacti-master php /var/www/html/tests/e2e/docker/probes/probe_private_advisory_hardening.php 2>&1 || true)
printf '%s\n' "$PROBE_OUT"

if ! printf '%s\n' "$PROBE_OUT" | grep -q 'Tests: 30, Passed: 30, Failed: 0'; then
	echo "FAIL: private advisory hardening probe did not report all tests passing" >&2
	exit 1
fi

echo "PASS: private advisory hardening Docker probe passed"
