#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
cd "$(dirname "$0")/../.."
export HOST_PORT="${HOST_PORT:-18173}"
project="cacti_aggregate_items_$$"
compose=(docker compose -p "$project" -f tests/e2e/docker-compose.yml)
trap '"${compose[@]}" down -v --remove-orphans' EXIT
# Local runs pin Node through mise; CI jobs that already set up Node 22 on
# PATH have no mise, so run it directly.
node_exec=()
if command -v mise > /dev/null; then
	node_exec=(mise exec node@22 --)
fi
${node_exec[@]+"${node_exec[@]}"} npm ci
"${compose[@]}" up -d --build --wait --wait-timeout 180
"${compose[@]}" exec -T cacti touch /tmp/cacti-aggregate-item-disposable
"${compose[@]}" exec -T -e CACTI_AGGREGATE_ITEM_DISPOSABLE=1 -e CACTI_AGGREGATE_ITEM_SCALE="${CACTI_AGGREGATE_ITEM_SCALE:-2}" cacti php tests/e2e/aggregate-item-overrides.php
${node_exec[@]+"${node_exec[@]}"} npm ci --prefix tests/e2e --ignore-scripts
${node_exec[@]+"${node_exec[@]}"} tests/e2e/node_modules/.bin/playwright install chromium
CACTI_AGGREGATE_ITEM_DISPOSABLE=1 CACTI_BASE_URL="http://127.0.0.1:$HOST_PORT" \
	${node_exec[@]+"${node_exec[@]}"} node tests/e2e/aggregate-item-overrides-browser.mjs

if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then
    : "${CACTI_PR_COVERAGE_OUTPUT:?Coverage output directory is required}"
    mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
    "${compose[@]}" cp cacti:/tmp/cacti-pr-coverage/. "$CACTI_PR_COVERAGE_OUTPUT"
fi
