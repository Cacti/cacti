#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail

if [[ $# != 1 ]]; then
	printf 'Usage: %s BASE_COMMIT\n' "$0" >&2
	exit 2
fi
base_commit=$1
git rev-parse --verify "${base_commit}^{commit}" >/dev/null
coverage_dir=${CACTI_COVERAGE_DIR:-$(mktemp -d)}
mkdir -p "$coverage_dir"
coverage_dir=$(cd "$coverage_dir" && pwd)
php_coverage=()
php_binary=${CACTI_TEST_PHP:-php}
python_binary=${CACTI_TEST_PYTHON:-python3}

run_probe() {
	local fixture=$1 label=$2
	shift 2
	local output="$coverage_dir/$label.json"
	CACTI_COVERAGE_FILE="$output" "$php_binary" -d memory_limit=1G -d xdebug.mode=coverage "tests/fixtures/Core/$fixture" "$@"
	php_coverage+=("$output")
}

browser_specs=()
add_browser_spec() {
	if [[ -f "tests/e2e/tests/$1.spec.js" ]]; then
		browser_specs+=("tests/$1.spec.js")
	fi
}

# Each group of fixture probes and browser specs is a file in
# pr_change_tests.d, so a change adds a file there instead of a line here.
tools_directory=.
if [[ "${BASH_SOURCE[0]}" == */* ]]; then
	tools_directory=${BASH_SOURCE[0]%/*}
fi
# shellcheck source=tests/tools/probe_groups.sh
source "$tools_directory/probe_groups.sh"
source_probe_groups "$tools_directory/pr_change_tests.d"

# Theme stylesheets, and the shared include/css sheets the theme specs install,
# are only exercised by the theme correctness specs, which record CSS coverage
# when CACTI_THEME_COVERAGE is set. They are slow, so they run only when the
# change touches a theme or a stylesheet one of those specs loads.
theme_specs=()
for spec in develop-theme-correctness develop-theme-cleanup; do
	if [[ -f "tests/e2e/tests/$spec.spec.ts" ]]; then
		theme_specs+=("tests/e2e/tests/$spec.spec.ts")
	fi
done
theme_coverage=0
if (( ${#theme_specs[@]} )); then
	while IFS= read -r changed; do
		if [[ "$changed" == include/themes/* ]] || grep -qF "'$changed'" "${theme_specs[@]}"; then
			theme_coverage=1
			break
		fi
	done < <(git diff --name-only "$base_commit" -- include/themes include/css)
fi
if (( theme_coverage )); then
	for spec in "${theme_specs[@]}"; do
		browser_specs+=("${spec#tests/e2e/}")
	done
fi
browser_args=()
if (( ${#browser_specs[@]} )); then
	(cd tests/e2e && CACTI_THEME_COVERAGE=$theme_coverage npx --no-install playwright test "${browser_specs[@]}" --workers=2 --reporter=line)
	browser_args=(--browser-root tests/e2e/test-results)
fi

"$python_binary" tests/tools/test_report_changed_coverage.py
"$python_binary" tests/tools/report_changed_coverage.py --base "$base_commit" --php-coverage "${php_coverage[@]}" "${browser_args[@]}" | tee "$coverage_dir/report.json"
