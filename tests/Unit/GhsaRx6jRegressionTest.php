<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$includeAuthSource = file_get_contents(__DIR__ . '/../../include/auth.php');

test('GHSA-rx6j-2pxr-p6gj: guest-to-auth transition destroys then restarts the session', function () use ($includeAuthSource) {
	// The guest-to-auth cleanup block must destroy the existing session
	// and start a fresh one so the guest cookie cannot be reused post
	// login.
	expect($includeAuthSource)->toContain('cacti_session_destroy();');
	expect($includeAuthSource)->toContain('cacti_session_start(true);');

	$destroyPos = strpos($includeAuthSource, 'cacti_session_destroy();');
	$restartPos = strpos($includeAuthSource, 'cacti_session_start(true);');
	expect($destroyPos)->not->toBeFalse();
	expect($restartPos)->not->toBeFalse();
	expect($destroyPos)->toBeLessThan($restartPos);
});

test('GHSA-rx6j-2pxr-p6gj: sess_user_id is killed before destroy', function () use ($includeAuthSource) {
	$killPos    = strpos($includeAuthSource, "kill_session_var('sess_user_id')");
	$destroyPos = strpos($includeAuthSource, 'cacti_session_destroy();');

	expect($killPos)->not->toBeFalse();
	expect($destroyPos)->not->toBeFalse();
	expect($killPos)->toBeLessThan($destroyPos);
});
