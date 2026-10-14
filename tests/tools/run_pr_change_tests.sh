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

for fixture in ScriptSubstitutionMatrix PackageManifestMatrix PackageSaveMatrix ConstantTimeGuardMatrix GraphDrilldownMarkup SpikekillMutationMatrix SpikekillFloatDebug SpikekillCliStatistics; do
	if [[ -f "tests/fixtures/Core/$fixture.php" ]]; then
		run_probe "$fixture.php" "$fixture"
	fi
done
if [[ -f tests/fixtures/Core/SpikekillRequestGate.php ]]; then
	run_probe SpikekillRequestGate.php spike-get GET '' mutation
	run_probe SpikekillRequestGate.php spike-dry GET dryrun mutation
	run_probe SpikekillRequestGate.php spike-post POST '' mutation
	run_probe SpikekillRequestGate.php spike-setting-get GET '' settings
	run_probe SpikekillRequestGate.php spike-setting-post POST '' settings
fi
if [[ -f tests/fixtures/Core/RraContainmentMatrix.php ]]; then
	run_probe RraContainmentMatrix.php rra-normal normal
	run_probe RraContainmentMatrix.php rra-missing missing
fi
if [[ -f tests/fixtures/Core/TableColumnSizing.php ]]; then
	case_number=0
	for request in '{}' '{"action":"go"}' '{"action":"change","tab":"devices"}' '{"action":"reset","report":"summary"}' '{"action":"edit"}'; do
		for mode in table div; do
			for title in '' Title; do
				run_probe TableColumnSizing.php "table-$case_number" "$request" "$mode" "$title"
				case_number=$((case_number + 1))
			done
		done
	done
fi

browser_specs=()
for spec in graph-drilldown graph-spikekill filter-dialog-theme table-column-widths; do
	if [[ -f "tests/e2e/tests/$spec.spec.js" ]]; then
		browser_specs+=("tests/$spec.spec.js")
	fi
done
browser_args=()
if (( ${#browser_specs[@]} )); then
	(cd tests/e2e && npx --no-install playwright test "${browser_specs[@]}" --workers=2 --reporter=line)
	browser_args=(--browser-root tests/e2e/test-results)
fi

"$python_binary" tests/tools/test_report_changed_coverage.py
"$python_binary" tests/tools/report_changed_coverage.py --base "$base_commit" --php-coverage "${php_coverage[@]}" "${browser_args[@]}" | tee "$coverage_dir/report.json"
