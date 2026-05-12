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

if (!file_exists(dirname(__DIR__, 2) . '/lib/CactiSettings.php')) {
	test('CactiSettings hand-off: feature not present on this branch', function () {})
		->skip('lib/CactiSettings.php absent — feature PR #7077 not merged into develop yet');
	return;
}

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/CactiSettings.php';

use Symfony\Component\Validator\Constraints as Assert;

// =====================================================================
// Hand-off tests for the Symfony Validator settings pilot.
//
// These tests pin the contract between the four moving parts that
// settings.php::save_settings() chains together:
//
//   $_POST -> gnrv()-built snapshot -> CactiSettings::validate -> raise_message
//
// Each section verifies one boundary so a regression in any single
// stage is caught without needing a full HTTP round-trip.
// =====================================================================

beforeEach(function () {
	// gnrv() reads $_CACTI_REQUEST first, then $_REQUEST. Reset both so
	// state from a prior test cannot leak into the snapshot path.
	$GLOBALS['_CACTI_REQUEST'] = [];
	$_REQUEST                  = [];
	$_POST                     = [];
});

// ---------------------------------------------------------------------
// Stage 1: $_POST -> gnrv()-built snapshot -> validate
// ---------------------------------------------------------------------

test('whitespace-padded posted value round-trips through gnrv into validate', function () {
	// gnrv() does not trim; the snapshot carries the raw string. The
	// hand-off contract is that whatever validate() sees is byte-identical
	// to what arrived in $_POST, so a Length(min:1) constraint sees the
	// padded value exactly as posted.
	$_POST['path_rrdtool']    = '   /usr/bin/rrdtool   ';
	$_REQUEST['path_rrdtool'] = $_POST['path_rrdtool'];

	$snapshot = ['path_rrdtool' => gnrv('path_rrdtool')];

	expect($snapshot['path_rrdtool'])->toBe('   /usr/bin/rrdtool   ');

	$definitions = [
		'path_rrdtool' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Length(min: 1, max: 255)],
		],
	];

	expect(CactiSettings::validate($snapshot, $definitions))->toBe([]);
});

test('array-shaped posted value reaches validate as an array via gnrv', function () {
	// A crafted request like snmp_timeout[]=foo lands in $_REQUEST as an
	// array. gnrv() returns it unchanged. The validator then sees an array
	// where it expects a scalar; Range(min:1,max:600000) rejects it. The
	// pin here is "the snapshot path does not silently coerce arrays to
	// strings", which would otherwise mask injection attempts.
	$_REQUEST['snmp_timeout'] = ['foo'];

	$snapshot = ['snmp_timeout' => gnrv('snmp_timeout')];

	expect($snapshot['snmp_timeout'])->toBe(['foo']);

	$definitions = [
		'snmp_timeout' => [
			'method'      => 'textbox',
			'constraints' => [
				fn () => new Assert\Regex(pattern: '/^\d+$/'),
				fn () => new Assert\Range(min: 1, max: 600000),
			],
		],
	];

	$result = CactiSettings::validate($snapshot, $definitions);

	expect($result)->toHaveKey('snmp_timeout')
		->and($result['snmp_timeout'])->toBeString();
});

// ---------------------------------------------------------------------
// Stage 2: closure-form constraints -> validator instances
// ---------------------------------------------------------------------

test('closure constraint defers instantiation until validate() runs', function () {
	// global_settings.php loads before include/vendor/autoload.php in the
	// real request flow. Constraint instances inside the array literal
	// would fatal at file-load. The closure form lets the file parse
	// regardless; the Assert\* class is only resolved when validate()
	// invokes the closure. This counter proves the closure is NOT called
	// at definition-build time and IS called exactly once per validate().
	$invocations = 0;

	$definitions = [
		'snmp_port' => [
			'method'      => 'textbox',
			'constraints' => [
				function () use (&$invocations) {
					$invocations++;
					return new Assert\Range(min: 1, max: 65535);
				},
			],
		],
	];

	// Building the definitions array did not call the closure.
	expect($invocations)->toBe(0);

	$result = CactiSettings::validate(['snmp_port' => '161'], $definitions);

	expect($invocations)->toBe(1)
		->and($result)->toBe([]);

	// A second validate() call resolves the closure again. Constraints
	// are not memoized inside CactiSettings, which keeps the contract
	// simple: each validate() pass is independent.
	CactiSettings::validate(['snmp_port' => '161'], $definitions);

	expect($invocations)->toBe(2);
});

test('closure returning Range produces violation on out-of-range posted value', function () {
	$definitions = [
		'snmp_port' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Range(min: 1, max: 65535)],
		],
	];

	$result = CactiSettings::validate(['snmp_port' => '70000'], $definitions);

	expect($result)->toHaveKey('snmp_port')
		->and($result['snmp_port'])->toBeString()
		->and($result['snmp_port'])->not->toBe('');
});

// ---------------------------------------------------------------------
// Stage 3: ConstraintViolationList -> first message -> raise_message key
// ---------------------------------------------------------------------

test('two violations on one setting collapse to the first message only', function () {
	// raise_message() renders one line per setting. validate() must
	// pick exactly one message per setting to match that UX. The first
	// violation in document order wins; subsequent violations are
	// dropped so the user sees the most-immediate failure rather than
	// a wall of cascading messages.
	$definitions = [
		'snmp_timeout' => [
			'method'      => 'textbox',
			'constraints' => [
				fn () => new Assert\Regex(pattern: '/^\d+$/', message: 'must be numeric'),
				fn () => new Assert\Range(min: 1, max: 600000, notInRangeMessage: 'must be in range'),
			],
		],
	];

	$result = CactiSettings::validate(['snmp_timeout' => 'abc'], $definitions);

	expect($result)->toHaveKey('snmp_timeout')
		->and($result['snmp_timeout'])->toBe('must be numeric')
		->and($result)->toHaveCount(1);
});

test('violation message wrapped through __() round-trips for i18n', function () {
	// Constraint messages are passed through __() at definition-build
	// time so the active locale's translation is what reaches
	// raise_message. With no translation registered, __() returns the
	// English source text unchanged; the test pins that the wrap is
	// transparent and does not mangle the message.
	$message     = __('must be a positive integer (milliseconds).');
	$definitions = [
		'snmp_timeout' => [
			'method'      => 'textbox',
			'constraints' => [
				fn () => new Assert\Regex(pattern: '/^\d+$/', message: __('must be a positive integer (milliseconds).')),
			],
		],
	];

	$result = CactiSettings::validate(['snmp_timeout' => 'abc'], $definitions);

	expect($result)->toHaveKey('snmp_timeout')
		->and($result['snmp_timeout'])->toBe($message);
});

// ---------------------------------------------------------------------
// Stage 4: canonical-array choice derivation -> validation
// ---------------------------------------------------------------------

test('Choice derived from $GLOBALS[poller_intervals] keys rejects unknown value', function () {
	// poller_interval's allowed values are derived dynamically from
	// $GLOBALS['poller_intervals'] keys. The closure form is what makes
	// this safe: the global is read at validate() time, after
	// global_arrays.php has populated it.
	$GLOBALS['poller_intervals'] = [
		60  => 'Every Minute',
		300 => 'Every 5 Minutes',
	];

	$definitions = [
		'poller_interval' => [
			'method'      => 'drop_array',
			'constraints' => [
				fn () => new Assert\Choice(choices: array_merge(
					array_keys($GLOBALS['poller_intervals']),
					array_map('strval', array_keys($GLOBALS['poller_intervals']))
				)),
			],
		],
	];

	$result = CactiSettings::validate(['poller_interval' => '42'], $definitions);

	expect($result)->toHaveKey('poller_interval');
});

test('Choice derived from $GLOBALS[poller_intervals] keys accepts canonical value', function () {
	// $_POST values arrive as strings; the canonical keys are int. The
	// constraint includes both forms via array_merge(array_keys, strval)
	// so the posted '300' validates against either int 300 or string '300'.
	$GLOBALS['poller_intervals'] = [
		60  => 'Every Minute',
		300 => 'Every 5 Minutes',
	];

	$definitions = [
		'poller_interval' => [
			'method'      => 'drop_array',
			'constraints' => [
				fn () => new Assert\Choice(choices: array_merge(
					array_keys($GLOBALS['poller_intervals']),
					array_map('strval', array_keys($GLOBALS['poller_intervals']))
				)),
			],
		],
	];

	expect(CactiSettings::validate(['poller_interval' => '300'], $definitions))->toBe([]);
});

test('mutating $GLOBALS[poller_intervals] between validate calls reshapes the choice set', function () {
	// Confirms the closure reads the global on every invocation, not at
	// definition time. A removed key on the second pass causes a value
	// that previously passed to fail.
	$definitions = [
		'poller_interval' => [
			'method'      => 'drop_array',
			'constraints' => [
				fn () => new Assert\Choice(choices: array_merge(
					array_keys($GLOBALS['poller_intervals']),
					array_map('strval', array_keys($GLOBALS['poller_intervals']))
				)),
			],
		],
	];

	$GLOBALS['poller_intervals'] = [60 => 'a', 300 => 'b'];
	expect(CactiSettings::validate(['poller_interval' => '300'], $definitions))->toBe([]);

	$GLOBALS['poller_intervals'] = [60 => 'a'];
	expect(CactiSettings::validate(['poller_interval' => '300'], $definitions))->toHaveKey('poller_interval');
});
