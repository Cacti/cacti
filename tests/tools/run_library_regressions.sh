#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail

# php -d options on Pest's command line do not reach tests that spawn PHP_BINARY.
# Keep the configuration local to this invocation and its children.
ini_dir=$(mktemp -d)
trap 'rm -rf -- "$ini_dir"' EXIT
{
	printf 'auto_prepend_file="%s/tests/Helpers/LibraryProcessCoverage.php"\n' "$PWD"
	printf 'xdebug.mode=coverage\nmemory_limit=1G\n'
	if [[ -n "${CACTI_PR_XDEBUG_EXTENSION:-}" ]]; then
		printf 'zend_extension="%s"\n' "$CACTI_PR_XDEBUG_EXTENSION"
	fi
} > "$ini_dir/library-tests.ini"
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:$ini_dir"
export CACTI_LIBRARY_COVERAGE=1
export XDEBUG_MODE=coverage

"${CACTI_TEST_PHP:-php}" include/vendor/bin/pest --configuration=tests/phpunit.xml \
	tests/Unit/DataCollection/DataSourceDuplicateFieldListTest.php \
	tests/Unit/DataCollection/Poller/ReplicateTableExcludedColumnsTest.php \
	tests/Unit/Plugin/PluginArchiveRestoreSequenceTest.php \
	tests/Unit/Security/Ldap/LdapPropertyDefaultsTest.php \
	tests/Unit/Security/Session/CookieRuntimeTest.php \
	tests/Unit/Ui/Html/FormColorDropdownEscapeTest.php \
	tests/Unit/Ui/Reports/ReportsAddItemTimespanTest.php
