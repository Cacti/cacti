<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('RRA containment accepts missing directories and rejects symlink pivots', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/RraContainmentProbe.php';
	$output  = array();
	$status  = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture), $output, $status);
	expect($status)->toBe(0);
	$cases = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
	expect($cases)->toHaveCount(13);

	foreach ($cases as $name => $allowed) {
		expect($allowed)->toBe(in_array($name, array('existing file', 'missing leaf', 'missing directories'), true));
	}
});
