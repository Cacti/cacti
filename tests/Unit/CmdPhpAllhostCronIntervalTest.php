<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for issue #7525 (cmd.php --allhost invocation ignored
 * cron_interval when resetting rrd_next_step).
 *
 * The ranged (--first/--last) branch already changed the reset baseline
 * (3rd UPDATE param) from 0 to $poller_interval when cron_interval !=
 * poller_interval. The $allhost branch hardcoded 0 unconditionally. The fix
 * applies the same cron_interval-aware conditional to $allhost's $params3.
 *
 * The $allhost branch is pulled directly out of cmd.php and wrapped in a
 * throwaway function so the test exercises the shipped code rather than a
 * re-typed copy of it, matching the extract-and-eval pattern used in
 * SsNimbleAlletraVolumesWordAssemblyTest.php and
 * Issue7070PercentileContractTest.php.
 */

$cmdPhpSource = file_get_contents(dirname(__DIR__, 2) . '/cmd.php');

preg_match('/^if \(\$allhost\) \{.*?^\}\n/ms', $cmdPhpSource, $allhostBlock);

test('the $allhost branch is present in cmd.php', function () use ($allhostBlock) {
	expect($allhostBlock)->not->toBeEmpty();
});

// eval() here only wraps PHP regex-extracted from this repo's own cmd.php
// (not external/user input) into a throwaway function. Test-only. Guarded
// by function_exists() so re-running this file within the same process is safe.
if (!function_exists('cmd_php_allhost_params3')) {
	eval('function cmd_php_allhost_params3(int $poller_interval, int $cron_interval, int $poller_id) {
		$allhost = true;
		' . $allhostBlock[0] . '
		return $params3;
	}');
}

test('cron_interval == poller_interval resets rrd_next_step to 0', function () {
	$params3 = cmd_php_allhost_params3(300, 300, 1);

	expect($params3)->toBe([300, 300, 0, 300, 1]);
});

test('cron_interval != poller_interval resets rrd_next_step to poller_interval, matching the ranged branch', function () {
	$params3 = cmd_php_allhost_params3(60, 300, 1);

	// before the fix this was hardcoded to 0 regardless of cron_interval
	expect($params3)->toBe([60, 60, 60, 60, 1])
		->and($params3[2])->not->toBe(0);
});

// the extracted block above is the full if ($allhost) {...} else {...}
// structure (the non-greedy match only stops at the bare top-level "}" that
// closes the whole statement, since the if-branch's own closing brace is
// immediately followed by " else {" on the same line, not a newline) -- so
// the same extraction can drive the ranged branch too, just by flipping the
// runtime $allhost flag the extracted code branches on.
if (!function_exists('cmd_php_ranged_params3')) {
	eval('function cmd_php_ranged_params3(int $poller_interval, int $cron_interval, int $poller_id, $first, $last) {
		$allhost = false;
		' . $allhostBlock[0] . '
		return $params3;
	}');
}

test('the $allhost and ranged branches compute the same $params3 for a given cron_interval/poller_interval pair', function () {
	$allhostParams = cmd_php_allhost_params3(60, 300, 1);
	$rangedParams  = cmd_php_ranged_params3(60, 300, 1, 5, 10);

	// ranged appends $first/$last; the first 5 elements (the ones this fix
	// touches) must stay in parity between both branches
	expect(array_slice($rangedParams, 0, 5))->toBe($allhostParams);
});
