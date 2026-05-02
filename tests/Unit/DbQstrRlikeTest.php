<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

// Minimal stub so database.php can be loaded in isolation
if (!function_exists('db_qstr')) {
	function db_qstr($s, $db_conn = false): string {
		return "'" . addslashes((string)$s) . "'";
	}
}
if (!class_exists('CactiSecureType')) {
	class CactiSecureType {
		public static function toString(mixed $v): string { return (string)$v; }
	}
}

require_once __DIR__ . '/../../lib/database.php';

test('db_qstr_rlike strips RLIKE metacharacters', function () {
	$result = db_qstr_rlike('test|value');
	expect($result)->not->toContain('|');
	expect($result)->toContain('RLIKE');
});

test('db_qstr_rlike strips NUL byte', function () {
	$result = db_qstr_rlike("evil\x00value");
	expect($result)->not->toContain("\x00");
});

test('db_qstr_rlike strips braces and brackets', function () {
	$result = db_qstr_rlike('{bad}[code](here)');
	expect($result)->not->toMatch('/[{}()\[\]]/');
});

test('db_qstr_rlike caps length at 255 bytes', function () {
	$long = str_repeat('a', 300);
	$result = db_qstr_rlike($long);
	// The actual string inside quotes should be <= 255
	preg_match("/'(.+)'/", $result, $m);
	expect(strlen($m[1] ?? ''))->toBeLessThanOrEqual(255);
});

test('db_qstr_rlike returns RLIKE clause', function () {
	$result = db_qstr_rlike('hostname');
	expect($result)->toStartWith('RLIKE ');
	expect($result)->toContain("'hostname'");
});

test('db_qstr_rlike safe with empty string', function () {
	$result = db_qstr_rlike('');
	expect($result)->toStartWith('RLIKE ');
});
