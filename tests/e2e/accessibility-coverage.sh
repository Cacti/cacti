#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
cd "$(dirname "$0")/../.."
project="cacti_accessibility_coverage_$$"
compose=(docker compose -p "$project" -f tests/e2e/docker-compose.yml)
fixture_file="$(mktemp)"
cleanup() {
    result=$?
    if [[ "$result" != 0 ]]; then
        "${compose[@]}" logs --tail=100 cacti >&2 || true
    fi
    "${compose[@]}" down -v --remove-orphans
    rm -f "$fixture_file"
}
trap cleanup EXIT
export HOST_PORT="${HOST_PORT:-18275}"
mise exec node@22 -- npm ci --ignore-scripts
mise exec node@22 -- npm run postinstall
"${compose[@]}" up -d --build --wait --wait-timeout 180
"${compose[@]}" exec -T -e CACTI_E2E_DISPOSABLE=1 cacti php tests/fixtures/Ui/DevelopAccessibilitySetup.php
"${compose[@]}" cp cacti:/tmp/cacti-accessibility-fixture.json "$fixture_file"
"${compose[@]}" exec -T cacti chown -R www-data:www-data /etc/cacti/pki /var/www/html/log
"${compose[@]}" exec -T --user www-data cacti php -r '
$directory = "/var/www/html/log";
$file = $directory . "/cacti.log";
if (!is_writable($directory) || (file_exists($file) && !is_writable($file))) {
    fwrite(STDERR, "The web worker cannot write the fixture log.\n");
    exit(1);
}
'
mise exec node@22 -- npm ci --prefix tests/e2e --ignore-scripts
mise exec node@22 -- tests/e2e/node_modules/.bin/playwright install chromium
CACTI_ACCESSIBILITY_FIXTURE="$fixture_file" CACTI_BASE_URL="http://127.0.0.1:$HOST_PORT" \
    mise exec node@22 -- tests/e2e/node_modules/.bin/playwright test \
    --config=tests/e2e/playwright.config.ts tests/develop-accessibility.spec.ts --project=chromium --workers=1 --max-failures=1
if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then
    : "${CACTI_PR_COVERAGE_OUTPUT:?Coverage output directory is required}"
    mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
    "${compose[@]}" cp cacti:/tmp/cacti-pr-coverage/. "$CACTI_PR_COVERAGE_OUTPUT"
fi
