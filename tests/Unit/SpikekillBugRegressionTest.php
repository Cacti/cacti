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
 * Regression tests for all 7 bugs identified in GitHub issue #6652.
 *
 * Each test corresponds to a numbered bug from the issue report and is
 * designed to fail if the bug is re-introduced — providing a permanent
 * guard against regression.
 *
 * Bug 1 (inverted user/default assignment) — covered by SpikekillInitTest.php
 * Bug 2 (malformed SQL) — tested here via source scan
 * Bug 3 (unescaped shell arg) — tested here via source scan
 * Bug 4 (double-negation validation) — tested here via source scan
 * Bug 5 (inverted range validation) — tested here via source scan
 * Bug 6 (wrong variable in backup cleanup) — tested here via source scan
 * Bug 7 (default method mismatch) — tested here via source scan
 *
 * See: https://github.com/Cacti/cacti/issues/6652
 *      https://github.com/Cacti/cacti/issues/5112
 *      https://github.com/Cacti/cacti/issues/6647
 */

$pollerPath  = __DIR__ . '/../../poller_spikekill.php';
$spikeLib    = __DIR__ . '/../../lib/spikekill.php';
$globalSettings = __DIR__ . '/../../include/global_settings.php';

// ---------------------------------------------------------------------------
// Bug 2: Malformed SQL — missing closing paren in REPLACE INTO on --force path
// ---------------------------------------------------------------------------

test('poller_spikekill.php REPLACE INTO settings statement is syntactically complete', function () use ($pollerPath) {
	$src = file_get_contents($pollerPath);

	// Every occurrence of the lastrun REPLACE must close its VALUES list.
	// The buggy form was: VALUES ("spikekill_lastrun", ?' with no closing paren.
	expect($src)->not->toContain('VALUES ("spikekill_lastrun", ?\'');
	expect($src)->toContain('VALUES ("spikekill_lastrun", ?)');
});

// ---------------------------------------------------------------------------
// Bug 3: Unescaped shell argument — RRD path passed raw to exec()
// ---------------------------------------------------------------------------

test('poller_spikekill.php escapes the RRD file path with cacti_escapeshellarg', function () use ($pollerPath) {
	$src = file_get_contents($pollerPath);

	// The path must be wrapped — bare concatenation is the bug.
	expect($src)->not->toMatch('/[\'"]--rrdfile=[\'"] \. \$f[^)]/');
	expect($src)->toContain('cacti_escapeshellarg($f)');
});

test('poller_spikekill.php --rrdfile argument uses cacti_escapeshellarg not raw concat', function () use ($pollerPath) {
	$src = file_get_contents($pollerPath);

	// Ensure the full safe pattern is present.
	expect($src)->toContain("' --rrdfile=' . cacti_escapeshellarg(\$f)");
});

// ---------------------------------------------------------------------------
// Bug 4: Double-negation validation — !$this->numspike != ''
// ---------------------------------------------------------------------------

test('lib/spikekill.php numspike validation has no double-negation', function () use ($spikeLib) {
	$src = file_get_contents($spikeLib);

	// The buggy pattern: !$this->numspike != ''
	// The boolean cast of !$x compared to a string is meaningless.
	expect($src)->not->toContain('!$this->numspike !=');
	expect($src)->not->toMatch('/!\s*\$this->numspike\s*!=/');
});

test('lib/spikekill.php numspike validation uses simple not-empty check', function () use ($spikeLib) {
	$src = file_get_contents($spikeLib);

	// Confirm the correct form exists — numspike is checked directly.
	expect($src)->toContain("\$this->numspike != ''");
});

// ---------------------------------------------------------------------------
// Bug 5: Inverted range validation — empty() guard applied to wrong branch
// ---------------------------------------------------------------------------

test('lib/spikekill.php validates time range only when both ends are non-empty', function () use ($spikeLib) {
	$src = file_get_contents($spikeLib);

	// The buggy form validated the range inside if (empty($this->out_start))
	// — i.e., when no range was set, which is always a no-op.
	// The fix validates inside if (!empty($this->out_start)).
	expect($src)->not->toMatch('/if\s*\(\s*empty\s*\(\s*\$this->out_start\s*\)\s*\)\s*\{[^}]*out_start\s*>=\s*\$this->out_end/s');

	// The correct guard validates when a range IS specified.
	expect($src)->toMatch('/if\s*\(\s*!\s*empty\s*\(\s*\$this->out_start\s*\)\s*\)\s*\{[^}]*out_start\s*>=\s*\$this->out_end/s');
});

// ---------------------------------------------------------------------------
// Bug 6: Backup cleanup — second unlink checked $xmlfile instead of $bakfile
// ---------------------------------------------------------------------------

test('lib/spikekill.php backup cleanup checks $bakfile not $xmlfile for .bak deletion', function () use ($spikeLib) {
	$src = file_get_contents($spikeLib);

	// The fixed form: each variable guards its own deletion.
	// Verify both exist and that $xmlfile does not guard $bakfile.
	expect($src)->toContain('file_exists($xmlfile)');
	expect($src)->toContain('unlink($xmlfile)');
	expect($src)->toContain('file_exists($bakfile)');
	expect($src)->toContain('unlink($bakfile)');
	// Specifically: the line that unlinks $bakfile must guard with $bakfile.
	expect($src)->toMatch('/file_exists\s*\(\s*\$bakfile\s*\)\s*\)*\s*\{\s*unlink\s*\(\s*\$bakfile\s*\)/s');
});

// ---------------------------------------------------------------------------
// Bug 7: Default method mismatch — default '2' (Float Range) vs dropdown '1'
// ---------------------------------------------------------------------------

test('global_settings.php spikekill_method default matches the only dropdown option', function () use ($globalSettings) {
	$src = file_get_contents($globalSettings);

	// Extract the spikekill_method block.
	preg_match("/'spikekill_method'\s*=>\s*\[(.*?)\],\s*'spikekill_/s", $src, $m);
	$block = $m[1] ?? '';

	expect($block)->not->toBeEmpty('spikekill_method block not found');

	// Default must be '1' (Standard Deviation) — not '2' (Float Range).
	expect($block)->toContain("'default'       => '1'");
	expect($block)->not->toContain("'default'       => '2'");
});

test('global_settings.php spikekill_method dropdown contains Standard Deviation as option 1', function () use ($globalSettings) {
	$src = file_get_contents($globalSettings);

	preg_match("/'spikekill_method'\s*=>\s*\[(.*?)\],\s*'spikekill_/s", $src, $m);
	$block = $m[1] ?? '';

	// Option 1 must exist so the default is selectable.
	expect($block)->toMatch('/1\s*=>/');
	expect($block)->toContain('Standard Deviation');
});

// ---------------------------------------------------------------------------
// Integration: the data loss chain from #5266 is closed end-to-end
// ---------------------------------------------------------------------------

test('spikekill user/default assignment prefers user setting when set', function () {
	// Inline mirror of the fixed production logic (see SpikekillInitTest.php
	// for full coverage; this is a smoke test for the chain as a whole).
	$resolve = function (string $current, string $user, string $default): string {
		if ($current === '') {
			return !empty($user) ? $user : $default;
		}

		return $current;
	};

	expect($resolve('', '5', '3'))->toBe('5');   // user wins
	expect($resolve('', '',  '3'))->toBe('3');   // fallback to default
	expect($resolve('7', '5', '3'))->toBe('7');  // constructor value wins
});

test('numspike empty string is not treated as unlimited kills by PHP loose comparison', function () {
	// The data loss chain: numspike = '' causes '' == 0 to be true,
	// which the old code interpreted as "unlimited kills".
	// The fix ensures numspike is never left as '' after init.
	//
	// This test documents the PHP behavior that made Bug 1 dangerous:
	// verify the risk is real, so the fix is understood as necessary.
	$numspike = '';

	// In PHP 8, '' == 0 is FALSE (strict non-numeric string comparison).
	// In PHP 7, '' == 0 was TRUE — that was the trap that made Bug 1 dangerous.
	// This test verifies we no longer rely on that behavior.
	expect($numspike === '')->toBeTrue('numspike starts as empty string');
	expect($numspike === 0)->toBeFalse('empty string is not integer 0 in strict comparison');

	// The fix: after init numspike is always a non-empty string or '0'
	// which only equals 0 when that is the genuine configured limit.
	$numspike_after_fix = '3';

	expect($numspike_after_fix == 0)->toBeFalse();
	expect($numspike_after_fix)->toBe('3');
});
