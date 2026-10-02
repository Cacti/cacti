<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-4p7f-qcc2-vmx7: the resource-cache PHP syntax check (resource_cache_out()
 * in lib/poller.php) runs the admin-set path_php_binary. It now goes through the
 * shell-free cacti_exec() with a discrete argv, so a configured value carrying
 * extra PHP options (e.g. "php -r <payload>"), a path containing spaces, or shell
 * metacharacters cannot inject an independent command/option at this sink. These
 * tests spawn real processes, mirroring CactiExecExitCodeTest.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/include/global_constants.php';
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
});

/* The reject/stderr paths call cacti_log(); swallow the incidental log-origin
 * notices so the test asserts the return value, not cacti_log's bootstrap needs. */
function _rcsc_quietly(callable $fn) {
	set_error_handler(function () { return true; });

	try {
		return $fn();
	} finally {
		restore_error_handler();
	}
}

test('the shell-free syntax check validates good PHP and flags a syntax error via -l (GHSA-4p7f)', function () {
	$good = tempnam(sys_get_temp_dir(), 'rcsc');
	file_put_contents($good, "<?php \$a = 1;\n");

	$bad = tempnam(sys_get_temp_dir(), 'rcsc');
	file_put_contents($bad, "<?php \$a = ;\n");

	$out = array();

	expect(cacti_exec(PHP_BINARY, array('-l', $good), $out))->toBe(0);
	expect(_rcsc_quietly(function () use ($bad, &$out) { return cacti_exec(PHP_BINARY, array('-l', $bad), $out); }))->not->toBe(0);

	unlink($good);
	unlink($bad);
});

test('an appended-option path_php_binary cannot execute an injected PHP payload (GHSA-4p7f)', function () {
	$sentinel = tempnam(sys_get_temp_dir(), 'rcscpwn');
	unlink($sentinel);

	// Simulate path_php_binary set to "<php> -r '<payload>'". With cacti_exec the whole string is
	// argv[0] (no shell, no splitting), so it is not a resolvable executable and the -r payload
	// must never run.
	$binary = PHP_BINARY . ' -r ' . escapeshellarg('file_put_contents(' . var_export($sentinel, true) . ", 'pwned');");
	$out    = array();

	$rc = _rcsc_quietly(function () use ($binary, &$out) { return cacti_exec($binary, array('-l'), $out); });

	expect($rc)->not->toBe(0);
	expect(file_exists($sentinel))->toBeFalse();

	if (file_exists($sentinel)) {
		unlink($sentinel);
	}
});

test('a path_php_binary that starts with a dash is rejected outright (GHSA-4p7f)', function () {
	$out = array();

	expect(_rcsc_quietly(function () use (&$out) { return cacti_exec('-r', array('-l'), $out); }))->toBe(255);
});
