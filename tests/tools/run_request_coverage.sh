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

run_php_probe() {
	local script="$1" pattern="$2"
	shift 2
	local php_args=(-d memory_limit=2G)
	if [[ -n "${CACTI_PR_XDEBUG_EXTENSION:-}" ]]; then
		php_args+=(-d "zend_extension=$CACTI_PR_XDEBUG_EXTENSION")
	fi
	if [[ -f "$script" ]] && grep -Eq "$pattern" <<< "$changed_paths"; then
		CACTI_PR_RAW_COVERAGE="$CACTI_PR_COVERAGE_OUTPUT" XDEBUG_MODE=coverage php "${php_args[@]}" "$script" "$@"
	fi
}

# Each probe group is a file in request_coverage.d, so a change adds a file
# there instead of a line here. A group names the changed paths that must reach
# it in "# dispatch:" lines, which test_request_coverage_dispatch.py verifies.
tools_directory=.
if [[ "${BASH_SOURCE[0]}" == */* ]]; then
	tools_directory=${BASH_SOURCE[0]%/*}
fi
# shellcheck source=tests/tools/probe_groups.sh
source "$tools_directory/probe_groups.sh"
source_probe_groups "$tools_directory/request_coverage.d"
