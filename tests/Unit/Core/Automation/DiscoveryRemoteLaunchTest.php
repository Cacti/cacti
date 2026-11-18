<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('a manual discovery on a remote collector keeps its dry run and debug modes', function (bool $debug, bool $dryrun) : void {
	$probe = dirname(__DIR__, 3) . '/fixtures/discovery_remote_launch_probe.php';
	$main  = cacti_test_isolated_probe($probe, ['main', json_encode(compact('debug', 'dryrun'), JSON_THROW_ON_ERROR)]);

	// The main server must not scan a remote collector's network itself.
	expect($main['launches'])->toBe([])
		->and($main['urls'])->toHaveCount(1)
		->and($main['urls'][0][0])->toBe(2);

	$remote = cacti_test_isolated_probe($probe, ['remote', $main['urls'][0][1]]);

	expect($remote['launches'])->toHaveCount(1);

	[$php, $args] = $remote['launches'][0];

	expect($php)->toBe('/usr/bin/php')
		->and($args)->toStartWith("-q '/var/www/cacti/poller_automation.php' --poller='2' --network='7' --force")
		->and(str_contains($args, ' --debug'))->toBe($debug)
		->and(str_contains($args, ' --dryrun'))->toBe($dryrun);
})->with([false, true])->with([false, true]);
