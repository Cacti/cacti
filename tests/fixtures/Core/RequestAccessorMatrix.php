<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Drives the unchanged request_*() accessors and the request cache helpers
// they build on. die_html_input_error() is replaced by a recorder that stops
// the call, so a rejected value is observed instead of exiting the probe.

namespace Cacti\Test\RequestAccessors {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
	require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

	class InputRejected extends \RuntimeException {
	}

	function read_config_option(string $name, bool $force = false) : mixed {
		return $name === 'log_validation' ? 'on' : '';
	}

	function html_log_input_error(string $variable) : void {
		$GLOBALS['request_unvalidated'][] = $variable;
	}

	function die_html_input_error(mixed $variable = null, mixed $value = null, string $message = '') : void {
		$GLOBALS['request_rejections'][] = [$variable, $value];

		if (empty($GLOBALS['request_rejection_returns'])) {
			throw new InputRejected((string) $variable);
		}
	}

	$utility = dirname(__DIR__, 3) . '/lib/html_utility.php';

	foreach (['unset_request_var', 'isset_request_var', 'set_request_var', 'get_nfilter_request_var', 'get_request_var', 'request_present_value', 'request_int', 'request_string', 'request_bool', 'request_enum'] as $function) {
		\cacti_fixture_source_function($utility, $function, __NAMESPACE__);
	}

	$GLOBALS['request_count'] = 0;

	function expect_that(bool $condition, string $message) : void {
		if (!$condition) {
			throw new \RuntimeException($message);
		}
		$GLOBALS['request_count']++;
	}

	/**
	 * Run one accessor call against a fresh request.
	 *
	 * @param array<string,mixed> $request The $_REQUEST map.
	 * @param callable            $call    The accessor call.
	 *
	 * @return array{0:string,1:mixed} ['value', result] or ['rejected', submitted value].
	 */
	function run(array $request, callable $call) : array {
		$_GET                           = [];
		$_POST                          = [];
		$_REQUEST                       = $request;
		$GLOBALS['_CACTI_REQUEST']      = [];
		$GLOBALS['request_rejections']  = [];
		$GLOBALS['request_unvalidated'] = [];

		try {
			return ['value', $call()];
		} catch (InputRejected) {
			return ['rejected', $GLOBALS['request_rejections'][0][1]];
		}
	}

	function label(mixed $value) : string {
		return str_replace("\n", '\n', var_export($value, true));
	}

	// Absent, empty and the JavaScript 'undefined' sentinel take the default,
	// as get_filter_request_var() does; null is not set as far as isset() goes.
	// A present empty or 'undefined' value is replaced in the cache by the
	// default, as get_filter_request_var() does, so a later get_request_var()
	// does not report it as unvalidated; an absent value stays absent.
	$defaults = [
		'int'    => [static fn () => request_int('v', 42), 42, 42],
		'string' => [static fn () => request_string('v', 'fallback'), 'fallback', 'fallback'],
		'regex'  => [static fn () => request_string('v', 'fallback', '/^[a-z]+$/'), 'fallback', 'fallback'],
		'true'   => [static fn () => request_bool('v', true), true, 'on'],
		'false'  => [static fn () => request_bool('v'), false, ''],
		'enum'   => [static fn () => request_enum('v', ['', 'undefined', 'a'], 'fallback'), 'fallback', 'fallback'],
	];

	foreach ($defaults as $kind => [$call, $default, $cached]) {
		foreach ([[], ['v' => null]] as $request) {
			expect_that(run($request, $call) === ['value', $default] && !array_key_exists('v', $GLOBALS['_CACTI_REQUEST']), $kind . ' must default without caching for ' . label($request));
		}

		foreach ([['v' => ''], ['v' => 'undefined']] as $request) {
			expect_that(run($request, $call) === ['value', $default], $kind . ' must default for ' . label($request));
			expect_that(($GLOBALS['_CACTI_REQUEST']['v'] ?? null) === $cached && get_request_var('v') === $cached && $GLOBALS['request_unvalidated'] === [], $kind . ' must cache its default for ' . label($request));
			expect_that($call() === $default, $kind . ' must read its cached default again for ' . label($request));
		}

		// Arrays and objects are never converted; even an empty array is refused.
		foreach ([['1'], [], new \stdClass()] as $value) {
			expect_that(run(['v' => $value], $call) === ['rejected', null], $kind . ' must reject ' . label($value));
		}
	}

	// The default is always accepted, so a cached default outside the allowed
	// set or the pattern reads back, and a submitted default is cached.
	run(['v' => ''], static fn () => request_string('v', 'N/A', '/^[a-z]+$/'));
	expect_that(request_string('v', 'N/A', '/^[a-z]+$/') === 'N/A', 'A cached default must read back past the pattern');
	expect_that(run(['v' => 'fallback'], static fn () => request_enum('v', ['a'], 'fallback')) === ['value', 'fallback'] && $GLOBALS['_CACTI_REQUEST']['v'] === 'fallback', 'A submitted default must be accepted and cached');

	// Integers follow FILTER_VALIDATE_INT exactly, including signs, padding and '0'.
	foreach (['0', '-5', '+7', ' 12 ', "12\n", 15, '9223372036854775807'] as $value) {
		$expected = filter_var($value, FILTER_VALIDATE_INT);
		expect_that(is_int($expected) && run(['v' => $value], static fn () => request_int('v', 99)) === ['value', $expected], 'int must accept ' . label($value));
	}

	foreach (['1.5', '2.0', '12items', '0x1A', '1e3', '--1', '9223372036854775808', 'abc', 1.5] as $value) {
		expect_that(run(['v' => $value], static fn () => request_int('v', 99)) === ['rejected', (string) $value], 'int must reject ' . label($value));
	}

	// The validated value is cached, so later readers see the integer.
	run(['v' => ' 12 '], static fn () => request_int('v'));
	expect_that(get_nfilter_request_var('v') === 12 && request_int('v') === 12, 'int must cache the validated value');

	// Strings: raw values pass, and a pattern behaves like FILTER_VALIDATE_REGEXP
	// for every value but '0', which get_filter_request_var() never filters.
	foreach (['abc_12' => 'abc_12', '0' => '0', 12 => '12', 'two words' => 'two words'] as $value => $expected) {
		expect_that(run(['v' => $value], static fn () => request_string('v', 'fallback')) === ['value', $expected], 'string must return ' . label($value));
	}

	foreach (['abc', 'ABC', 'abc1', "abc\n", "abc\ndef", ' abc', 'abc '] as $value) {
		$valid  = filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-z]+$/']]) !== false;
		$result = run(['v' => $value], static fn () => request_string('v', 'fallback', '/^[a-z]+$/'));
		expect_that($result === ($valid ? ['value', $value] : ['rejected', $value]), 'pattern must match FILTER_VALIDATE_REGEXP for ' . label($value));
	}
	expect_that(run(['v' => '0'], static fn () => request_string('v', 'fallback', '/^[a-z]+$/')) === ['rejected', '0'], "pattern must also apply to '0'");
	expect_that(run(['v' => '0'], static fn () => request_string('v', 'fallback', '/^[0-9]+$/')) === ['value', '0'], "pattern must accept a matching '0'");

	try {
		run(['v' => 'abc'], static fn () => request_string('v', 'fallback', '/['));
		$thrown = false;
	} catch (\InvalidArgumentException) {
		$thrown = true;
	}
	expect_that($thrown && $GLOBALS['request_rejections'] === [], 'A malformed pattern must throw instead of rejecting the input');

	// Booleans: the FILTER_VALIDATE_BOOLEAN words in any case; anything else is
	// refused. The result is cached as a checkbox value, 'on' or '', so readers
	// comparing get_request_var() against 'on' agree with it.
	foreach (['1', 'true', 'on', 'yes', 'TRUE', 'On', ' yes ', 1] as $value) {
		expect_that(run(['v' => $value], static fn () => request_bool('v')) === ['value', true], 'bool must read true from ' . label($value));
		expect_that(get_request_var('v') === 'on', 'bool must cache true from ' . label($value) . " as 'on'");
	}

	foreach (['0', 'false', 'off', 'no', 'OFF', 0] as $value) {
		expect_that(run(['v' => $value], static fn () => request_bool('v', true)) === ['value', false], 'bool must read false from ' . label($value));
		expect_that(get_request_var('v') === '', 'bool must cache false from ' . label($value) . " as ''");
	}

	foreach (['2', '-1', 'y', 'n', 'enabled', 'onn'] as $value) {
		expect_that(run(['v' => $value], static fn () => request_bool('v')) === ['rejected', $value], 'bool must reject ' . label($value));
	}

	// A cached false is '', which must still read as false on a second call
	// rather than as an empty value that takes a true default.
	run(['v' => 'off'], static fn () => request_bool('v', true));
	expect_that(request_bool('v', true) === false, 'bool must read its cached false again');
	run(['v' => 'yes'], static fn () => request_bool('v'));
	expect_that(request_bool('v') === true, 'bool must read its cached true again');

	// Enums compare exact strings, so integer action keys match their decimal form only.
	foreach ([['2', [1, 2, 3]], ['0', [0]], ['actions', ['actions']], ['a b', ['x', 'a b']]] as [$value, $allowed]) {
		expect_that(run(['v' => $value], static fn () => request_enum('v', $allowed, 'fallback')) === ['value', $value], 'enum must accept ' . label($value));
	}

	foreach ([['02', [1, 2, 3]], [' 2', [1, 2, 3]], ['2.0', [1, 2, 3]], ['ACTIONS', ['actions']], ['actions ', ['actions']], ['1', []]] as [$value, $allowed]) {
		expect_that(run(['v' => $value], static fn () => request_enum('v', $allowed, 'fallback')) === ['rejected', $value], 'enum must reject ' . label($value));
	}

	// The accessors read Cacti's request cache: a removed variable stays removed
	// and a value set by set_request_var() wins over the submitted one.
	run(['v' => 'submitted', 'gone' => 'value'], static function () : string {
		unset_request_var('gone');
		set_request_var('v', 'updated');
		expect_that(request_string('gone', 'fallback') === 'fallback', 'A removed variable must take the default');

		return '';
	});
	$GLOBALS['_CACTI_REQUEST']['v'] = 'cached';
	expect_that(request_enum('v', ['updated', 'cached']) === 'cached', 'The request cache must win over $_REQUEST');

	// die_html_input_error() exits, but if it ever returned, the caller must get
	// its default and the rejected value must not be cached as validated.
	$GLOBALS['request_rejection_returns'] = true;

	foreach ([
		'array'  => [['v' => ['1']], static fn () => request_int('v', 42), 42],
		'int'    => [['v' => '1.5'], static fn () => request_int('v', 42), 42],
		'string' => [['v' => 'A'], static fn () => request_string('v', 'fallback', '/^[a-z]+$/'), 'fallback'],
		'bool'   => [['v' => 'maybe'], static fn () => request_bool('v', true), true],
		'enum'   => [['v' => 'other'], static fn () => request_enum('v', ['a'], 'fallback'), 'fallback'],
	] as $kind => [$request, $call, $default]) {
		expect_that(run($request, $call) === ['value', $default] && count($GLOBALS['request_rejections']) === 1, $kind . ' must return its default after a rejection');
		expect_that(!isset($GLOBALS['_CACTI_REQUEST']['v']), $kind . ' must not cache a rejected value');
	}
	$GLOBALS['request_rejection_returns'] = false;

	print json_encode(['passed' => $GLOBALS['request_count']], JSON_THROW_ON_ERROR) . "\n";
}
