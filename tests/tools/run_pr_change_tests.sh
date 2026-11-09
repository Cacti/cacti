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

for fixture in ScriptSubstitutionMatrix PackageManifestMatrix PackageSaveMatrix ConstantTimeGuardMatrix GraphDrilldownMarkup HtmlGraphMarkup CardWidgetMarkup SpikekillMutationMatrix SpikekillFloatDebug LibraryHelpersCoverage AggregateLibraryCoverage SchedulerLibraryCoverage ExportLibraryCoverage ImportLibraryCoverage UtilityLibraryCoverage; do
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
if [[ -f tests/fixtures/Core/LibraryHelpersCoverage.php ]]; then
	run_probe LibraryHelpersCoverage.php library-helpers-no-vendor no-vendor
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

# Library code is exercised by Pest and the Docker request probes. Reuse the
# 80 percent gate's suites, changed-file scope and probe dispatcher so both gates
# measure the same evidence.
request_raw="$coverage_dir/request-coverage"
BASE_COMMIT="$base_commit" CACTI_TEST_COVERAGE=1 CACTI_PR_RAW_COVERAGE="${CACTI_PR_RAW_COVERAGE:-/tmp/cacti-pr-coverage}" \
	CACTI_PR_COVERAGE_OUTPUT="$request_raw" bash tests/tools/run_request_coverage.sh

clover_args=()
if [[ -n "$(git diff --name-only "$(git merge-base "$base_commit" HEAD)" HEAD -- '*.php' ':(exclude)tests/**' ':(exclude)include/vendor/**')" ]]; then
	"$python_binary" tests/tools/check_changed_php_coverage.py --base "$base_commit" \
		--prepare-config tests/phpunit.xml --output-config "$coverage_dir/phpunit-changed.xml"
	XDEBUG_MODE=coverage "$php_binary" -d memory_limit=2G include/vendor/bin/pest --configuration="$coverage_dir/phpunit-changed.xml" \
		--testsuite=Unit,Integration --coverage-clover="$coverage_dir/pest-clover.xml"
	clover_args=(--clover "$coverage_dir/pest-clover.xml")

	# Raw Xdebug credits only the first line of a wrapped statement. Normalize the
	# fixture and request evidence with the analyser that wrote the Clover report,
	# which rejects a report whose source no longer matches this checkout.
	native="$coverage_dir/native-coverage"
	mkdir -p "$native"
	"$python_binary" - "$native" "${php_coverage[@]}" <<'PY'
import hashlib
import json
import sys
from pathlib import Path

root = Path.cwd().resolve()
for name in sys.argv[2:]:
    coverage = {}
    for file, data in json.loads(Path(name).read_text()).items():
        path = Path(file)
        lines = data.get('lines', data) if isinstance(data, dict) else {}
        # PHP encodes a file without line evidence as an empty list.
        if path.is_file() and path.resolve().is_relative_to(root) and '/vendor/' not in file and isinstance(lines, dict):
            coverage[str(path.resolve())] = lines
    hashes = {file: hashlib.sha256(Path(file).read_bytes()).hexdigest() for file in coverage}
    report = {'root': str(root), 'coverage': coverage, 'hashes': hashes}
    (Path(sys.argv[1]) / ('fixture-' + Path(name).name)).write_text(json.dumps(report))
PY
	if compgen -G "$request_raw/*.json" > /dev/null; then
		cp "$request_raw"/*.json "$native/"
	fi
	"$php_binary" tests/tools/normalize_request_coverage.php "$native" "$coverage_dir/native-normalized" "$coverage_dir/pest-clover.xml"

	# Rebase each container report onto this checkout, dropping evidence for any
	# file whose source differs so it stays uninstrumented and fails closed.
	"$python_binary" - "$coverage_dir/native-normalized" "$coverage_dir/php-coverage.json" <<'PY'
import hashlib
import json
import sys
from pathlib import Path

root = Path.cwd().resolve()
merged = {}
for report_path in sorted(Path(sys.argv[1]).glob('*.json')):
    report = json.loads(report_path.read_text())
    prefix = report['root'].rstrip('/') + '/'
    for file, lines in report['coverage'].items():
        if not file.startswith(prefix):
            continue
        local = (root / file[len(prefix):]).resolve()
        if not local.is_relative_to(root) or not local.is_file():
            continue
        if hashlib.sha256(local.read_bytes()).hexdigest() != report['hashes'].get(file):
            continue
        target = merged.setdefault(str(local), {})
        for number, hits in (lines or {}).items():
            target[number] = max(hits, target.get(number, -2))
Path(sys.argv[2]).write_text(json.dumps(merged))
PY
	php_coverage=("$coverage_dir/php-coverage.json")
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
"$python_binary" tests/tools/report_changed_coverage.py --base "$base_commit" --php-coverage "${php_coverage[@]}" "${clover_args[@]}" "${browser_args[@]}" | tee "$coverage_dir/report.json"
