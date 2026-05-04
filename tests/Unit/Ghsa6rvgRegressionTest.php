<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$includeAuthSource = file_get_contents(__DIR__ . '/../../include/auth.php');

test('GHSA-6rvg-2vm8-5wrf: guest session wipe is gated by get_guest_account comparison', function () use ($includeAuthSource) {
	// The wipe block only runs when the current session belongs to the
	// guest account; otherwise a regular user session would be killed.
	expect($includeAuthSource)->toContain("if (get_guest_account() === \$_SESSION['sess_user_id']) {");
});

test('GHSA-6rvg-2vm8-5wrf: guest wipe chain is kill -> destroy -> start', function () use ($includeAuthSource) {
	// Locate the exact guest-gated block and confirm the three calls
	// appear in the required order inside it.
	$gate = "if (get_guest_account() === \$_SESSION['sess_user_id']) {";
	$pos  = strpos($includeAuthSource, $gate);
	expect($pos)->not->toBeFalse();

	$block = substr($includeAuthSource, $pos, 400);
	expect($block)->toContain("kill_session_var('sess_user_id')");
	expect($block)->toContain('cacti_session_destroy();');
	expect($block)->toContain('cacti_session_start(true);');

	$killPos    = strpos($block, "kill_session_var('sess_user_id')");
	$destroyPos = strpos($block, 'cacti_session_destroy();');
	$startPos   = strpos($block, 'cacti_session_start(true);');

	expect($killPos)->toBeLessThan($destroyPos);
	expect($destroyPos)->toBeLessThan($startPos);
});

test('GHSA-6rvg-2vm8-5wrf: guest wipe only fires when no guest_account flag is set', function () use ($includeAuthSource) {
	// The outer guard ensures the wipe does not trigger on pages that
	// legitimately run as the guest account.
	expect($includeAuthSource)->toContain("if (!isset(\$guest_account) && isset(\$_SESSION['sess_user_id'])) {");
});
