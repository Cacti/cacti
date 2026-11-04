#!/bin/sh
set -eu

repo_path=${1:-$(pwd)}
coverage_path=${CACTI_PR_RAW_COVERAGE:?CACTI_PR_RAW_COVERAGE must name a writable directory}
scenario="$repo_path/tests/performance/boost_archive/e2e_scenario.php"
coverage_helper="$repo_path/tests/Helpers/RequestCoverage.php"
rra_path=$(mktemp -d /tmp/cacti-boost-coverage-rra.XXXXXX)
php_wrapper=$(mktemp /tmp/cacti-boost-coverage-php.XXXXXX)

mkdir -p "$coverage_path"

{
	printf '%s\n' '#!/bin/sh'
	printf 'exec %s -d auto_prepend_file=%s "$@"\n' "$(command -v php)" "$coverage_helper"
} > "$php_wrapper"
chmod 0700 "$php_wrapper"

coverage_php() {
	php -d auto_prepend_file="$coverage_helper" "$@"
}

run_poller() {
	coverage_php "$repo_path/poller_boost.php" --force
}

# Attribute the real RRDtool protocol and filesystem-safety probes to lib/rrd.php. Their Pest
# contracts assert the returned state separately; any probe process failure is fatal here too.
coverage_php "$repo_path/tests/fixtures/Core/BoostRrdAcknowledgementProbe.php" \
	"$(command -v rrdtool)" "$rra_path/coverage-protocol.rrd" > /dev/null
coverage_php "$repo_path/tests/fixtures/Core/BoostStructuredPermissionsProbe.php" \
	"$rra_path/structured-probe" > /dev/null
coverage_php "$repo_path/tests/fixtures/Core/BoostRrdPathValidationProbe.php" \
	"$rra_path/path-probe" > /dev/null

coverage_php "$scenario" --action=setup-isolated-failure --rra-dir="$rra_path" \
	--sources=12 --samples=2 --php-binary="$php_wrapper"
run_poller
coverage_php "$scenario" --action=repair-isolated-failure --rra-dir="$rra_path" \
	--sources=12 --samples=2

coverage_php "$scenario" --action=setup-permanent-failures --rra-dir="$rra_path" \
	--sources=12 --samples=1
run_poller

coverage_php "$scenario" --action=setup-backend-outage --rra-dir="$rra_path" \
	--sources=12 --samples=1
if run_poller; then
	printf '%s\n' 'ERROR: backend-wide outage unexpectedly completed' >&2
	exit 1
fi
coverage_php "$scenario" --action=retry-backend-outage --rra-dir="$rra_path" \
	--sources=12 --samples=1
coverage_php "$scenario" --action=repair-backend-outage --rra-dir="$rra_path" \
	--sources=12 --samples=1
run_poller

coverage_php "$scenario" --action=setup-shared-path --rra-dir="$rra_path" \
	--sources=12 --samples=1
if run_poller; then
	printf '%s\n' 'ERROR: shared-path ownership violation unexpectedly completed' >&2
	exit 1
fi

coverage_php "$scenario" --action=setup-worker-crash --rra-dir="$rra_path" \
	--sources=12 --samples=1
run_poller &
master_pid=$!
coverage_php "$scenario" --action=kill-worker --rra-dir="$rra_path" \
	--sources=12 --samples=1
if wait "$master_pid"; then
	printf '%s\n' 'ERROR: killed worker unexpectedly allowed archive cleanup' >&2
	exit 1
fi
coverage_php "$scenario" --action=repair-backend-outage --rra-dir="$rra_path" \
	--sources=12 --samples=1
run_poller

coverage_php "$scenario" --action=status --rra-dir="$rra_path" --sources=12 --samples=1

printf '%s\n' 'PASS: Boost coverage scenarios exercised success, isolated failure, outage, crash recovery, and ownership refusal'
