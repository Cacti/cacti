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
 * poller_script_exec() starts programs from an argument vector, so shell
 * metacharacters in an argument reach the program as literal text and are
 * never interpreted by a shell.
 */

require_once dirname(__DIR__, 4) . '/lib/poller_script.php';

test('shell metacharacters in an argument are passed through literally', function (string $argument) {
	$marker = sys_get_temp_dir() . '/cacti-poller-script-' . getmypid();

	@unlink($marker);

	$output = poller_script_exec('printf', ['%s', str_replace('MARKER', $marker, $argument)]);

	expect($output)->not->toBeNull()
		->and($output['stdout'])->toBe(str_replace('MARKER', $marker, $argument))
		->and(file_exists($marker))->toBeFalse();
})->with([
	'command substitution' => ['$(touch MARKER)'],
	'backticks'            => ['`touch MARKER`'],
	'sequence'             => ['x; touch MARKER'],
	'pipe'                 => ['x | touch MARKER'],
	'and list'             => ['x && touch MARKER'],
	'redirect'             => ['x > MARKER'],
	'glob and quotes'      => ['* "\' \\ ~ $HOME']
]);

test('a program path cannot be smuggled in through the program name', function () {
	expect(poller_script_exec('/bin/sh', ['-c', 'true']))->toBeNull()
		->and(poller_script_exec('sh -c true'))->toBeNull()
		->and(poller_script_exec('../../bin/sh'))->toBeNull();
});
