<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs the argument handling blocks of cli/import_template.php and
 * cli/import_package.php from the checked-in source with stand-ins for the
 * database and the importer.
 */

namespace Cacti\Test\CliImportArguments;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		fwrite(STDERR, 'FAIL: ' . $message . "\n");

		exit(1);
	}
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	if (str_contains($sql, 'WHERE id = ?')) {
		return in_array((int) $params[0], [1, 7], true) ? $params[0] : false;
	}

	return '1';
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function import_package(string $xmlfile, int $profile_id = 1, bool $remove_orphans = false,
	bool $replace_svalues = false, bool $preview = false, bool $info_only = false) : mixed {
	return $info_only ? ['name' => 'Sample'] : false;
}

function display_help() : void {
}

$mode = $argv[1] ?? '';

switch ($mode) {
	case 'template-profile':
		$block = '~\n(\tif \(\$profile_id > 0\) \{.*?\n\tif \(empty\(\$id\)\) \{.*?\n\t\})~s';

		ob_start();
		$locals = \cacti_fixture_source_block($root . '/cli/import_template.php', __NAMESPACE__, $block, ['profile_id' => 7, 'with_profile' => true]);
		$output = (string) ob_get_clean();

		check((string) $locals['id'] === '7', 'The requested profile is used when --with-profile is also given');
		check(str_contains($output, "Ignoring '--with-profile'"), 'The conflicting option is reported');

		ob_start();
		$locals = \cacti_fixture_source_block($root . '/cli/import_template.php', __NAMESPACE__, $block, ['profile_id' => 7, 'with_profile' => false]);
		ob_end_clean();

		check((string) $locals['id'] === '7', 'The requested profile is used');

		ob_start();
		$locals = \cacti_fixture_source_block($root . '/cli/import_template.php', __NAMESPACE__, $block, ['profile_id' => 9, 'with_profile' => false]);
		$output = (string) ob_get_clean();

		check((string) $locals['id'] === '1' && str_contains($output, 'not found'), 'An unknown profile falls back to the default');

		ob_start();
		\cacti_fixture_source_block($root . '/cli/import_template.php', __NAMESPACE__, '~\n\t\t\tdefault:\n(\t\t\t\tprint .*?Invalid Argument.*?;)~s', ['arg' => '--bogus']);
		$output = (string) ob_get_clean();

		check(str_contains($output, '(--bogus)'), 'The invalid argument is named');

		break;
	case 'package-info':
		$package = tempnam(sys_get_temp_dir(), 'cacti-package-');

		register_shutdown_function(static function () use ($package) : void {
			$output = (string) ob_get_clean();

			unlink($package);

			check($output === '{"name":"Sample"}', 'The Package information is printed without a profile');

			print "PASS: package-info\n";
		});

		ob_start();
		\cacti_fixture_source_block($root . '/cli/import_package.php', __NAMESPACE__, '~\n(\tif \(\$info_only\) \{.*?\n\t\})~s',
			['info_only' => true, 'filename' => $package, 'profile_id' => '', 'remove_orphans' => false, 'replace_svalues' => false, 'preview_only' => false]);

		check(false, 'The information request ends the run');

		break;
	default:
		fwrite(STDERR, "Unknown mode\n");

		exit(2);
}

print "PASS: $mode\n";
