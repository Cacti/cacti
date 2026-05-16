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
 * Source-level test for the cmd_realtime.php poller loop. The realtime
 * runner switches on $item['action']; default and unknown actions never
 * write $output, so an unguarded $output from a prior iteration would
 * leak into the next local_data_id's poller_output_realtime insert.
 * The fix is an unset($output) at the top of each foreach iteration,
 * placed strictly before the switch statement so every branch starts
 * from a clean local.
 */

$source = file_get_contents(__DIR__ . '/../../cmd_realtime.php');

test('cmd_realtime.php parses and still contains the realtime foreach', function () use ($source) {
	expect($source)->toContain('foreach($poller_items as $item)');
	expect($source)->toContain('switch ($item[\'action\'])');
});

test('unset($output) sits inside the foreach body before the action switch', function () use ($source) {
	$loopStart   = strpos($source, 'foreach($poller_items as $item)');
	expect($loopStart)->not->toBeFalse();

	$switchStart = strpos($source, 'switch ($item[\'action\'])', $loopStart);
	expect($switchStart)->not->toBeFalse();

	$unsetPos    = strpos($source, 'unset($output)', $loopStart);
	expect($unsetPos)->not->toBeFalse('unset($output) must be present inside the foreach body');
	expect($unsetPos < $switchStart)->toBeTrue('unset($output) must run before the action switch');
	expect($unsetPos > $loopStart)->toBeTrue('unset($output) must live inside the foreach body');
});
