<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace CactiErrorHandlerSuppressionTest;

const E_NOTICE_LEVEL  = \E_NOTICE;
const E_WARNING_LEVEL = \E_WARNING;

/*
 * The value PHP 8's @ operator leaves in error_reporting() while an error is
 * suppressed: every level except the fatal/parse ones is masked off.  A notice
 * or warning raised under @ is therefore excluded from this mask.
 */
const AT_OPERATOR_MASK = \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR | \E_PARSE;

$GLOBALS['ceh_log_calls']       = [];
$GLOBALS['ceh_backtrace_calls'] = [];
$GLOBALS['phperrors']           = [
	\E_WARNING => 'WARNING',
	\E_NOTICE  => 'NOTICE',
];

/**
 * Stub that never treats the error as ignorable so the mask check is reached.
 *
 * @return bool Always false.
 */
function IgnoreErrorHandler(string $message, string $file = '', int $line = 0) : bool {
	return false;
}

/**
 * Captures calls that would have been logged by the real handler.
 *
 * @return bool Always true.
 */
function cacti_log(string $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
	$GLOBALS['ceh_log_calls'][] = $string;

	return true;
}

/**
 * Records backtrace emissions so the test can assert they did not fire.
 *
 * @return string Always empty.
 */
function cacti_debug_backtrace(string $type = '', bool $html = false, bool $log = true, int $limit = 0, int $skip = 0) : string {
	$GLOBALS['ceh_backtrace_calls'][] = $type;

	return '';
}

// Unreached helpers for the plugin/fatal branch; defined so eval() stays self-contained.
function api_plugin_disable_all(string $plugin) : void {}
function admin_email(string $subject, string $message) : void {}
function __(string $text, mixed ...$args) : string { return $text; }

$source = file_get_contents(dirname(__DIR__, 3) . '/lib/functions.php');
preg_match('/function CactiErrorHandler\(.*?^}\R/ms', $source, $matches);

$handler = $matches[0];

/*
 * These cases exercise the error_reporting() mask and the logging path, not
 * install mode.  This suite shares one PHP process with the rest of Pest, and
 * another test file (tests/integration/BoostProcessTableUpgradeIntegrationTest)
 * defines the global IN_CACTI_INSTALL constant at collection time.  A constant
 * cannot be unset, so strip the install-mode short-circuit from the sandboxed
 * copy; without this the handler would return true before reaching the branch
 * under test whenever that other file has loaded first.
 */
$handler = preg_replace('/if \(defined\(\'IN_CACTI_INSTALL\'\)\) \{\s*return true;\s*\}\s*/', '', $handler, 1);

eval('namespace CactiErrorHandlerSuppressionTest;' . $handler);

beforeEach(function () {
	$GLOBALS['ceh_log_calls']       = [];
	$GLOBALS['ceh_backtrace_calls'] = [];
	$this->previous_reporting       = error_reporting();
});

afterEach(function () {
	error_reporting($this->previous_reporting);
});

test('a level excluded from the error_reporting mask returns before logging', function () {
	// Model a notice raised under the @ operator on PHP 8.
	error_reporting(AT_OPERATOR_MASK);

	$result = CactiErrorHandler(E_NOTICE_LEVEL, 'fwrite(): Write failed with errno=32 Broken pipe', '/lib/vendor/AbstractStream.php', 49);

	expect($result)->toBeTrue()
		->and($GLOBALS['ceh_log_calls'])->toBe([])
		->and($GLOBALS['ceh_backtrace_calls'])->toBe([]);
});

test('a suppressed warning is likewise swallowed under the PHP 8 at-operator mask', function () {
	error_reporting(AT_OPERATOR_MASK);

	$result = CactiErrorHandler(E_WARNING_LEVEL, 'suppressed warning', '/lib/example.php', 10);

	expect($result)->toBeTrue()
		->and($GLOBALS['ceh_log_calls'])->toBe([]);
});

test('an enabled level is logged and reported to PHP as unhandled', function () {
	error_reporting(E_ALL);

	$result = CactiErrorHandler(E_NOTICE_LEVEL, 'genuine notice', '/lib/example.php', 20);

	expect($result)->toBeFalse()
		->and($GLOBALS['ceh_log_calls'])->toContain('PHP NOTICE: genuine notice in file: /lib/example.php  on line: 20')
		->and($GLOBALS['ceh_backtrace_calls'])->toContain('PHP ERROR NOTICE');
});
