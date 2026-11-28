<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The typed settings accessors run against the real read_config_option(). A
 * value placed in the per-request option cache is what read_config_option()
 * returns without $force, and a forced read with no database falls back to the
 * $settings default, so neither path needs a database.
 */

require_once dirname(__DIR__, 3) . '/lib/config_api.php';
require_once dirname(__DIR__, 3) . '/lib/spikekill.php';

/**
 * Place a value in the option cache read_config_option() consults first.
 *
 * @param string $name  The setting name.
 * @param mixed  $value The cached value.
 *
 * @return void
 */
function config_api_test_cache(string $name, mixed $value) : void {
	$GLOBALS['config'][OPTIONS_CLI][$name] = $value;
	$_SESSION[OPTIONS_WEB][$name]          = $value;
}

beforeEach(function () {
	$this->savedCli = $GLOBALS['config'][OPTIONS_CLI] ?? null;
	$this->savedWeb = $_SESSION[OPTIONS_WEB] ?? null;
});

afterEach(function () {
	if ($this->savedCli === null) {
		unset($GLOBALS['config'][OPTIONS_CLI]);
	} else {
		$GLOBALS['config'][OPTIONS_CLI] = $this->savedCli;
	}

	if ($this->savedWeb === null) {
		unset($_SESSION[OPTIONS_WEB]);
	} else {
		$_SESSION[OPTIONS_WEB] = $this->savedWeb;
	}
});

test('integer settings coerce numeric strings and fall back on anything else', function (mixed $stored, int $expected) {
	config_api_test_cache('config_api_test_int', $stored);

	expect(cacti_setting_int('config_api_test_int', -1))->toBe($expected);
})->with([
	'numeric string'    => ['42', 42],
	'padded numeric'    => [' 7 ', 7],
	'negative'          => ['-3', -3],
	'zero'              => ['0', 0],
	'native int'        => [9, 9],
	'empty string'      => ['', -1],
	'non-numeric'       => ['abc', -1],
	'null'              => [null, -1],
	'array'             => [['5'], -1],
]);

test('boolean settings accept only the recognised textual forms', function (mixed $stored, bool $default, bool $expected) {
	config_api_test_cache('config_api_test_bool', $stored);

	expect(cacti_setting_bool('config_api_test_bool', $default))->toBe($expected);
})->with([
	'on'             => ['on', false, true],
	'one'            => ['1', false, true],
	'true mixed'     => [' TRUE ', false, true],
	'empty is off'   => ['', true, false],
	'zero'           => ['0', true, false],
	'false'          => ['false', true, false],
	'off'            => ['off', true, false],
	'native bool'    => [true, false, true],
	'unrecognised'   => ['yes', true, true],
	'unrecognised 2' => ['yes', false, false],
	'null'           => [null, true, true],
	'array'          => [['on'], false, false],
]);

test('string settings keep a configured empty string and reject non-scalars', function (mixed $stored, string $expected) {
	config_api_test_cache('config_api_test_string', $stored);

	expect(cacti_setting_string('config_api_test_string', 'fallback'))->toBe($expected);
})->with([
	'text'         => ['traffic_in', 'traffic_in'],
	'empty string' => ['', ''],
	'number'       => [12, '12'],
	'null'         => [null, 'fallback'],
	'array'        => [['x'], 'fallback'],
]);

test('enum settings return only an allowed value', function (mixed $stored, string $expected) {
	config_api_test_cache('config_api_test_enum', $stored);

	expect(cacti_setting_enum('config_api_test_enum', ['avg', 'nan', 'last'], 'last'))->toBe($expected);
})->with([
	'allowed'        => ['avg', 'avg'],
	'case differs'   => ['AVG', 'last'],
	'not offered'    => ['bogus', 'last'],
	'empty'          => ['', 'last'],
	'null'           => [null, 'last'],
	'array'          => [['avg'], 'last'],
]);

test('a forced read replaces a cached value with the stored default', function () {
	global $settings;
	$saved    = $settings ?? null;
	$settings = ['probe' => ['config_api_test_forced' => ['default' => '15']]];

	try {
		config_api_test_cache('config_api_test_forced', 'stale');

		expect(cacti_setting_int('config_api_test_forced', 0))->toBe(0)
			->and(cacti_setting_int('config_api_test_forced', 0, true))->toBe(15);
	} finally {
		$settings = $saved;
	}
});

test('spikekill reads its defaults through the typed accessors', function () {
	global $settings;
	$saved    = $settings ?? null;
	$settings = ['spikes' => [
		'spikekill_method'     => ['default' => '3'],
		'spikekill_avgnan'     => ['default' => 'bogus'],
		'spikekill_dsfilter'   => ['default' => 'traffic_in'],
		'spikekill_number'     => ['default' => '7'],
		'spikekill_deviations' => ['default' => 'not-a-number'],
		'spikekill_absmax'     => ['default' => '99'],
	]];
	$savedUser = $_SESSION[SESS_USER_ID] ?? null;
	unset($_SESSION[SESS_USER_ID]);

	try {
		$spiker = new spikekill();
		$read   = static fn (string $property) : mixed => (new ReflectionProperty(spikekill::class, $property))->getValue($spiker);

		expect($read('dmethod'))->toBe(3)
			->and($read('davgnan'))->toBe('last')
			->and($read('ddsfilter'))->toBe('traffic_in')
			->and($read('dnumspike'))->toBe(7)
			->and($read('dstddev'))->toBe(10)
			->and($read('dabsmax'))->toBe(99.0);
	} finally {
		$settings = $saved;

		if ($savedUser !== null) {
			$_SESSION[SESS_USER_ID] = $savedUser;
		}
	}
});

test('spikekill call-site fallbacks match the registered setting defaults', function () {
	global $settings;

	// read_config_option() already returns the registered default for a missing
	// setting; the call-site fallbacks only replace an invalid stored value. They
	// still duplicate include/global_settings.php, so pin them to it.
	$source  = (string) file_get_contents(dirname(__DIR__, 3) . '/include/global_settings.php');
	$columns = [
		'dmethod'   => 'spikekill_method',
		'davgnan'   => 'spikekill_avgnan',
		'ddsfilter' => 'spikekill_dsfilter',
		'dnumspike' => 'spikekill_number',
		'dstddev'   => 'spikekill_deviations',
		'dabsmax'   => 'spikekill_absmax',
	];

	$saved     = $settings ?? null;
	$settings  = [];
	$savedUser = $_SESSION[SESS_USER_ID] ?? null;
	unset($_SESSION[SESS_USER_ID]);

	try {
		$spiker = new spikekill();

		foreach ($columns as $property => $name) {
			expect(preg_match("/'" . $name . "' => \\[.*?'default'\\s*=> '([^']*)'/s", $source, $match))->toBe(1);

			$fallback = (new ReflectionProperty(spikekill::class, $property))->getValue($spiker);

			expect((string) $fallback)->toBe($match[1], $name);
		}
	} finally {
		$settings = $saved;

		if ($savedUser !== null) {
			$_SESSION[SESS_USER_ID] = $savedUser;
		}
	}
});
