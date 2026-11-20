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

for fixture in ScriptSubstitutionMatrix DeviceCollectorMessages ReportMessageDelivery ExternalLinkMessage MessageFormatting TrustedActionMessages PackageManifestMatrix PackageSaveMatrix ConstantTimeGuardMatrix GraphDrilldownMarkup HtmlGraphMarkup CardWidgetMarkup SpikekillMutationMatrix SpikekillFloatDebug RowGuardMatrix RrdInfoHtmlEscape GraphItemsListMarkup; do
	if [[ -f "tests/fixtures/Core/$fixture.php" ]]; then
		run_probe "$fixture.php" "$fixture"
	fi
done
if [[ -f tests/fixtures/Core/RemoveSpikesHelp.php ]]; then
	run_probe RemoveSpikesHelp.php RemoveSpikesHelp
fi
if [[ -f tests/fixtures/Core/RowGuardMatrix.php ]]; then
	for mode in auth-changepassword add-device reports-edit; do
		run_probe RowGuardMatrix.php "row-guard-$mode" "$mode"
	done
fi
if [[ -f tests/fixtures/Core/SpikekillRequestGate.php ]]; then
	run_probe SpikekillRequestGate.php spike-get GET '' mutation
	run_probe SpikekillRequestGate.php spike-dry GET dryrun mutation
	run_probe SpikekillRequestGate.php spike-post POST '' mutation
	run_probe SpikekillRequestGate.php spike-setting-get GET '' settings
	run_probe SpikekillRequestGate.php spike-setting-post POST '' settings
fi
for fixture in PackageArchive PackageDiffBoundary PackageRepositoryWorkspace PackageImportFileStatus; do
	run_probe "$fixture.php" "$fixture"
done
if [[ -f tests/fixtures/Core/RraContainmentMatrix.php ]]; then
	run_probe RraContainmentMatrix.php rra-normal normal
	run_probe RraContainmentMatrix.php rra-missing missing
fi
if [[ -f tests/fixtures/Core/Boost/FailedDataSourceIsolation.php ]]; then
	run_probe Boost/FailedDataSourceIsolation.php boost-failed-data-source-isolation
fi
if [[ -f tests/fixtures/BoostBulkDrainFailureProbe.php ]]; then
	for scenario in middle boundary tail none; do
		run_probe ../BoostBulkDrainFailureProbe.php "boost-bulk-drain-$scenario" "$scenario" > /dev/null
	done
fi
if [[ -f tests/fixtures/BoostOnDemandFailureProbe.php ]]; then
	for scenario in first middle ok-substring archive-copy temp-create tail ack-create ack-insert ack-delete archive-ok ack-empty; do
		run_probe ../BoostOnDemandFailureProbe.php "boost-on-demand-$scenario" "$scenario" > /dev/null
	done
fi
if [[ -f tests/fixtures/BoostSourceExistenceProbe.php ]]; then
	for scenario in deleted query-failed; do
		run_probe ../BoostSourceExistenceProbe.php "boost-source-existence-$scenario" "$scenario" > /dev/null
	done
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

if [[ -f tests/fixtures/Core/GraphItemLabelMarkup.php ]]; then
	run_probe GraphItemLabelMarkup.php graph-item-label-aggregate label '<em>Literal label</em>'
	run_probe GraphItemLabelMarkup.php graph-item-label-graph graph '<em>Literal label</em>'
fi

run_probe ConfiguredCommands.php configured-commands
run_probe ConfiguredCommands.php deprecated-rebuild deprecated
run_probe DisplayAndValidation.php display-validation
run_probe DisplayAndValidation.php input-method-save save

browser_specs=()
for spec in graph-drilldown graph-spikekill filter-dialog-theme table-column-widths; do
	if [[ -f "tests/e2e/tests/$spec.spec.js" ]]; then
		browser_specs+=("tests/$spec.spec.js")
	fi
done
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
