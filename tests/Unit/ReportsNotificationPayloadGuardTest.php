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

function getReportsNotificationSource(): string {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/reports.php');

	expect($source)->not->toBeFalse('Failed to read lib/reports.php');

	return $source;
}

test('queued report notifications validate the decoded payload before iteration', function () {
	$source = getReportsNotificationSource();

	$decode = strpos($source, '$notifications = json_decode(');
	$guard  = strpos($source, 'if (!is_array($notifications))', $decode);
	$loop   = strpos($source, 'foreach ($notifications as $type => $data)', $decode);

	expect($decode)->not->toBeFalse()
		->and($guard)->not->toBeFalse()
		->and($loop)->not->toBeFalse()
		->and($decode)->toBeLessThan($guard)
		->and($guard)->toBeLessThan($loop);
});

test('each notification entry is validated before dispatch', function () {
	$source = getReportsNotificationSource();

	$loop   = strpos($source, 'foreach ($notifications as $type => $data)');
	$guard  = strpos($source, 'if (!is_array($data))', $loop);
	$branch = strpos($source, 'switch($type)', $loop);

	expect($loop)->not->toBeFalse()
		->and($guard)->not->toBeFalse()
		->and($branch)->not->toBeFalse()
		->and($loop)->toBeLessThan($guard)
		->and($guard)->toBeLessThan($branch);
});
