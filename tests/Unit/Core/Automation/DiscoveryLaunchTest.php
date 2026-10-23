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

test('discovery child arguments preserve scan mode at each process boundary', function (int $thread, bool $force, bool $debug, bool $dryrun) : void {
	$args = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_launch_probe.php', [json_encode(compact('thread', 'force', 'debug', 'dryrun'), JSON_THROW_ON_ERROR)]);

	expect(array_slice($args, 0, 4))->toBe(['-q', '/tmp/cacti discovery/poller_automation.php', '--poller=2', '--network=7'])
		->and(in_array('--thread=3', $args, true))->toBe($thread === 3)
		->and(in_array('--force', $args, true))->toBe($force)
		->and(in_array('--debug', $args, true))->toBe($debug)
		->and(in_array('--dryrun', $args, true))->toBe($dryrun)
		->and(count($args))->toBe(4 + (int) ($thread > 0) + (int) $force + (int) $debug + (int) $dryrun);
})->with([0, 3])->with([false, true])->with([false, true])->with([false, true]);
