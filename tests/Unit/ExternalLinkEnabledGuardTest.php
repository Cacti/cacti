<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('link.php filters external_links by enabled status in SQL and PHP', function () : void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/link.php');
	expect($source)->not->toBeFalse();
	expect($source)->toContain("AND enabled = 'on'");
	expect($source)->toContain("if (!cacti_sizeof(\$page) || (\$page['enabled'] ?? '') !== 'on')");
});

test('link.php refuses to render or execute a disabled external link', function () : void {
	$root = dirname(__DIR__, 2);
	$work = sys_get_temp_dir() . '/cacti_link_enabled_' . bin2hex(random_bytes(6));
	mkdir($work . '/include/content', 0700, true);
	file_put_contents($work . '/include/global.php', "<?php\n");
	file_put_contents($work . '/include/content/marker.php', "<?php print 'EXECUTED_LOCAL_CONTENT';\n");

	$runner = <<<'PHP'
<?php
$root    = $argv[1];
$work    = $argv[2];
$enabled = $argv[3];
$content = $argv[4];

define('CACTI_PATH_INCLUDE', $work . '/include');
define('CACTI_PATH_URL', '/cacti/');
define('MESSAGE_LEVEL_ERROR', 2);

$GLOBALS['messages'] = [];

function gfrv(string $name) : int {
	return 8;
}

function grv(string $name) : int {
	return 8;
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function db_fetch_row_prepared(string $query, array $params) : array {
	if (!str_contains($query, "enabled = 'on'")) {
		fwrite(STDERR, 'MISSING_ENABLED_SQL_FILTER');
		exit(2);
	}

	return [
		'id'          => 8,
		'title'       => 'External Tool',
		'style'       => 'TAB',
		'enabled'     => $GLOBALS['enabled'],
		'refresh'     => 0,
		'contentfile' => $GLOBALS['content'],
	];
}

function is_realm_allowed(int $realm) : bool {
	return true;
}

function htmle(string $value) : string {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function general_header() : void {
}

function top_header() : void {
}

function bottom_footer() : void {
}

function raise_message(string $name) : void {
	$GLOBALS['messages'][] = $name;
}

register_shutdown_function(static function () : void {
	print "\nMESSAGES=" . implode(',', $GLOBALS['messages']);
});

$_SESSION = [];
chdir($work);
require $root . '/link.php';
PHP;

	$runner_file = $work . '/run.php';
	file_put_contents($runner_file, $runner);

	try {
		// Disabled external link must not execute local content or emit iframe
		$disabled_out = [];
		$disabled_rc  = 0;
		exec(
			escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner_file) . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($work) . " '' 'marker.php'",
			$disabled_out,
			$disabled_rc
		);
		$disabled_text = implode("\n", $disabled_out);
		expect($disabled_rc)->toBe(0);
		expect($disabled_text)->not->toContain('EXECUTED_LOCAL_CONTENT');
		expect($disabled_text)->toContain('MESSAGES=page_not_defined');

		// Enabled external link renders cleanly
		$enabled_out = [];
		$enabled_rc  = 0;
		exec(
			escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner_file) . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($work) . " 'on' 'marker.php'",
			$enabled_out,
			$enabled_rc
		);
		$enabled_text = implode("\n", $enabled_out);
		expect($enabled_rc)->toBe(0);
		expect($enabled_text)->toContain('EXECUTED_LOCAL_CONTENT');
		expect($enabled_text)->toContain('MESSAGES=');
		expect($enabled_text)->not->toContain('page_not_defined');
	} finally {
		@unlink($runner_file);
		@unlink($work . '/include/content/marker.php');
		@unlink($work . '/include/global.php');
		@rmdir($work . '/include/content');
		@rmdir($work . '/include');
		@rmdir($work);
	}
});
