#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
: "${CACTI_PR_COVERAGE_OUTPUT:?Discovery coverage needs an evidence directory}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
CACTI_PR_COVERAGE_OUTPUT="$(cd "$CACTI_PR_COVERAGE_OUTPUT" && pwd)"
export CACTI_PR_COVERAGE_OUTPUT
if [[ "${1:-}" != --native-only ]]; then
  destination="$CACTI_PR_COVERAGE_OUTPUT"
  CACTI_PR_COVERAGE_OUTPUT="$(mktemp -d "$destination/discovery.XXXXXX")"
  export CACTI_PR_COVERAGE_OUTPUT
  python3 tests/tools/verify_discovery_coverage.py --prepare "$CACTI_PR_COVERAGE_OUTPUT"
  CACTI_DISCOVERY_COVERAGE=1 bash tests/e2e/discovery/run.sh
  python3 tests/tools/verify_discovery_coverage.py "$CACTI_PR_COVERAGE_OUTPUT" --proof-output "$CACTI_PR_COVERAGE_OUTPUT/discovery-proof.txt"
  cp "$CACTI_PR_COVERAGE_OUTPUT"/*.json "$destination/"
  exit 0
fi
if [[ ! -f "$CACTI_PR_COVERAGE_OUTPUT/discovery-scenarios.txt" ]]; then
  python3 tests/tools/verify_discovery_coverage.py --prepare "$CACTI_PR_COVERAGE_OUTPUT"
fi
: "${CACTI_MATCH_DB_DSN:?Native discovery coverage needs the disposable database}"
configuration="$(mktemp -d "${TMPDIR:-/tmp}/cacti-discovery-coverage.XXXXXX")"
trap 'rm -rf "$configuration"' EXIT
scan="$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')"
# Inherit the temporary prepend configuration in the real isolated probe children.
# Keep installed PHP settings and the optional task-local Xdebug extension.
printf 'auto_prepend_file="%s/tests/Helpers/RequestCoverage.php"\n' "$ROOT" > "$configuration/discovery.ini"
if [[ -n "${CACTI_PR_XDEBUG_EXTENSION:-}" ]]; then
  printf 'zend_extension="%s"\n' "$CACTI_PR_XDEBUG_EXTENSION" >> "$configuration/discovery.ini"
fi
PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-$scan}:$configuration" \
  CACTI_PR_RAW_COVERAGE="$CACTI_PR_COVERAGE_OUTPUT" XDEBUG_MODE=coverage \
  php -d memory_limit=2G include/vendor/bin/pest --configuration=tests/phpunit-discovery.xml \
  tests/Unit/Core/Automation/AutomationFindOsTest.php \
  tests/Unit/Core/Automation/DiscoveryAddressDatabaseTest.php \
  tests/Unit/Core/Automation/DiscoveryAddressTest.php \
  tests/Unit/Core/Automation/DiscoveryNetworkTest.php \
  tests/Unit/Core/Automation/DiscoveryLaunchTest.php \
  tests/Unit/Core/Automation/DiscoveryWorkerAddressTest.php \
  tests/Coverage/DiscoveryWorkerPingCoverageTest.php \
  --log-junit="$CACTI_PR_COVERAGE_OUTPUT/discovery-native.xml" --fail-on-skipped --colors=never
python3 tests/tools/verify_discovery_coverage.py --native-only "$CACTI_PR_COVERAGE_OUTPUT"
