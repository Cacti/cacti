<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiSanitizeSqlColumnAllowlistFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

/*
 * Run the optional allowlist of the real sanitize_sql_column(), loaded
 * unchanged from lib/functions.php, so the changed-line gates see its branch
 * executed. The reports list resolves its sort through reports_sort_column(),
 * which tests/fixtures/Core/ReportsListSortCoverage.php drives.
 */

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

cacti_fixture_source_function($root . '/lib/functions.php', 'sanitize_sql_column', __NAMESPACE__);

$allowed = ['name', 'full_name'];

$cases = [
	// an allowlisted column passes unchanged
	['full_name', 'full_name'],
	// clean under the character-class filter, so only the allowlist rejects it
	['ua.password', 'name'],
	['report.name', 'name'],
	// the match is exact and case-sensitive after the filter strips the rest
	["full_name'", 'full_name'],
	['FULL_NAME', 'name'],
	['name UNION SELECT password FROM user_auth', 'name'],
	['', 'name'],
];

foreach ($cases as [$column, $expected]) {
	$actual = sanitize_sql_column($column, 'name', $allowed);

	if ($actual !== $expected) {
		throw new RuntimeException(sprintf('sanitize_sql_column sorted %s as %s, expected %s', var_export($column, true), $actual, $expected));
	}
}

// without an allowlist the previous behavior is unchanged
if (sanitize_sql_column('ua.password', 'name') !== 'ua.password') {
	throw new RuntimeException('sanitize_sql_column changed its behavior when no allowlist is given');
}

print json_encode(['passed' => count($cases) + 1], JSON_THROW_ON_ERROR) . "\n";
