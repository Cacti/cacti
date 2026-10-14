#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail

: "${BASE_COMMIT:?Coverage needs the pull request base commit}"
: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage needs an evidence directory}"
mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
merge_base="$(git merge-base "$BASE_COMMIT" HEAD)"
changed_paths="$(git diff --name-only "$merge_base" HEAD)"

run_probe() {
	local script="$1" pattern="$2" port="$3"
	if [[ -f "$script" ]] && printf '%s\n' "$changed_paths" | rg -q "$pattern"; then
		HOST_PORT="$port" bash "$script"
	fi
}

run_probe tests/e2e/aggregate-item-overrides.sh '^(aggregate_(graphs|templates)\.php|lib/api_aggregate\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-item-overrides)' 18273
CACTI_AGGREGATE_TEST_BROWSER=1 run_probe tests/e2e/aggregate-automation.sh '^(automation_aggregates\.php|lib/(api_automation|automation|api_aggregate)\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-automation)' 18274
run_probe tests/e2e/accessibility-coverage.sh '^(include/(top_header|bottom_footer|top_graph_header|top_general_header|layout)\.php|lib/html\.php|.*\.php|tests/e2e/accessibility)' 18275
run_probe tests/e2e/queue/run.sh '^(lib/api_queue\.php|cli/queue_(admin|worker)\.php|include/global_settings\.php|install/upgrades/1_3_0\.php|tests/e2e/queue/)' 18276
