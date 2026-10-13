#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
cd "$(dirname "$0")/../.."
project="cacti_aggregate_automation_$$"
compose=(docker compose -p "$project" -f tests/e2e/docker-compose.yml)
trap '"${compose[@]}" down -v --remove-orphans' EXIT
mise exec node@22 -- npm ci
"${compose[@]}" up -d --build --wait --wait-timeout 180
"${compose[@]}" exec -T cacti touch /tmp/cacti-aggregate-automation-disposable
"${compose[@]}" exec -T -e CACTI_AGGREGATE_TEST_DISPOSABLE=1 -e CACTI_AGGREGATE_TEST_SCALE="${CACTI_AGGREGATE_TEST_SCALE:-0}" cacti php tests/e2e/aggregate-automation.php
if [[ "${CACTI_AGGREGATE_TEST_BROWSER:-0}" == 1 ]]; then
	mise exec node@22 -- npm ci --prefix tests/e2e --ignore-scripts
	mise exec node@22 -- tests/e2e/node_modules/.bin/playwright install chromium
	CACTI_AGGREGATE_TEST_DISPOSABLE=1 CACTI_BASE_URL="http://127.0.0.1:${HOST_PORT:-8080}" \
		mise exec node@22 -- node tests/e2e/aggregate-automation-browser.mjs
fi
