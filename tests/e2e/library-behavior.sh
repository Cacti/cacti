#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
temporary="$(mktemp -d "${TMPDIR:-/tmp}/cacti-library-behavior.XXXXXX")"
project="cacti-library-$(basename "$temporary" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9')"
compose=(docker compose -p "$project" -f "$root/tests/e2e/docker-compose.yml" -f "$temporary/compose.yml")

cleanup() {
	"${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
	rm -rf "$temporary"
}
trap cleanup EXIT

cat > "$temporary/compose.yml" <<'YAML'
services:
  snmp-fixture:
    build:
      context: ../fixtures/snmp-coverage
  cacti:
    build:
      target: profiling
    environment:
      CACTI_E2E_DISPOSABLE: '1'
      XDEBUG_MODE: coverage
YAML

export HOST_PORT="${HOST_PORT:-18278}"
"${compose[@]}" build cacti snmp-fixture
"${compose[@]}" up -d --wait mariadb snmp-fixture openldap
"${compose[@]}" up -d --no-deps --wait cacti
"${compose[@]}" exec -T cacti mkdir -p /tmp/library-coverage
"${compose[@]}" exec -T -e CACTI_PR_RAW_COVERAGE=/tmp/library-coverage cacti \
	php -d memory_limit=2G tests/fixtures/Core/LibraryBehavior.php | tee "$temporary/result.log"
grep -Eq '^PASS: library behavior \([1-9][0-9]* assertions\)$' "$temporary/result.log"
"${compose[@]}" cp cacti:/tmp/library-coverage "$temporary/evidence"
reports=("$temporary/evidence"/library-behavior-*.json)
[[ -f "${reports[0]}" ]] || { echo 'Library behavior produced no coverage evidence' >&2; exit 1; }
if [[ -n "${CACTI_PR_COVERAGE_OUTPUT:-}" ]]; then
	mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
	cp "${reports[@]}" "$CACTI_PR_COVERAGE_OUTPUT/"
fi
