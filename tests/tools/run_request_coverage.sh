#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail

: "${BASE_COMMIT:?Coverage needs the pull request base commit}"
: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage needs an evidence directory}"
mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
command -v grep > /dev/null
merge_base="$(git merge-base "$BASE_COMMIT" HEAD)"
changed_paths="$(git diff --name-only "$merge_base" HEAD)"

run_probe() {
	local script="$1" pattern="$2" port="$3"
	if [[ -f "$script" ]] && grep -Eq "$pattern" <<< "$changed_paths"; then
		HOST_PORT="$port" bash "$script"
	fi
}

run_probe tests/e2e/aggregate-item-overrides.sh '^(aggregate_(graphs|templates)\.php|lib/api_aggregate\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-item-overrides)' 18273
CACTI_AGGREGATE_TEST_BROWSER=1 run_probe tests/e2e/aggregate-automation.sh '^(automation_aggregates\.php|lib/(api_automation|automation|api_aggregate)\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-automation)' 18274
run_probe tests/e2e/accessibility-coverage.sh '^((about|aggregate_graphs|auth_changepassword|auth_resetpassword|automation_templates|color|data_queries|data_templates|graph_realtime|graph_xport|graphs_new|host|host_templates|index|package|permission_denied|plugins|tree|user_admin|user_group_admin|utilities)\.php|include/top_(general_|graph_)?header\.php|install/install\.php|lib/html(_form|_graph)?\.php|tests/e2e/accessibility)' 18275
run_probe tests/e2e/queue/run.sh '^(lib/api_queue\.php|cli/queue_(admin|worker)\.php|include/global_settings\.php|install/upgrades/1_3_0\.php|tests/e2e/queue/)' 18276

run_php_probe() {
	local script="$1" pattern="$2"
	local php_args=(-d memory_limit=2G)
	if [[ -n "${CACTI_PR_XDEBUG_EXTENSION:-}" ]]; then
		php_args+=(-d "zend_extension=$CACTI_PR_XDEBUG_EXTENSION")
	fi
	if [[ -f "$script" ]] && grep -Eq "$pattern" <<< "$changed_paths"; then
		CACTI_PR_RAW_COVERAGE="$CACTI_PR_COVERAGE_OUTPUT" XDEBUG_MODE=coverage php "${php_args[@]}" "$script"
	fi
}

run_php_probe tests/fixtures/Core/Auth/BoundaryContracts.php '^(auth_changepassword\.php|include/global\.php|lib/auth\.php|package_import\.php|tests/fixtures/Core/Auth/BoundaryContracts\.php)'

run_php_probe tests/fixtures/Core/Rrd/FallbackSourceIdentity.php '^(lib/rrd\.php|tests/fixtures/Core/Rrd/FallbackSourceIdentity\.php)'

run_php_probe tests/fixtures/UserAdministrationAuditCoverage.php '^(user_admin\.php|lib/functions\.php|tests/fixtures/UserAdministrationAudit)'

run_php_probe tests/fixtures/Core/GraphViewCrossFilterCoverage.php '^(lib/html\.php|lib/html_graph\.php|tests/fixtures/Core/GraphViewCrossFilterCoverage\.php)'

run_php_probe tests/fixtures/Core/TreeFilterDeviceTemplateCoverage.php '^(lib/html_tree\.php|tests/fixtures/Core/TreeFilterDeviceTemplateCoverage\.php)'

run_php_probe tests/fixtures/Core/DataSourceRrdRepairCoverage.php '^(data_sources\.php|tests/fixtures/Core/DataSourceRrdRepairCoverage\.php)'

run_probe tests/e2e/installer-coverage.sh '^(lib/installer\.php|cli/(install_cacti|upgrade_database)\.php|install/(functions|preflight|report|background|install)\.php|tests/e2e/installer)' 18277
