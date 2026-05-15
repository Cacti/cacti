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
 * Source-level test for cmd.php's reindex foreach. Each $reindex item
 * runs through a switch on $index_item['action']; the default branch
 * only logs and does not assign $output. Without an explicit reset the
 * assert comparison further down compares the new index against the
 * previous iteration's $output, leading to spurious assert pass/fail
 * decisions. The fix unsets $output at the top of the body, after
 * $assert_fail is reset, and before the action switch.
 */

$source = file_get_contents(__DIR__ . '/../../cmd.php');

test('cmd.php parses and still contains the reindex foreach', function () use ($source) {
	expect($source)->toContain('foreach ($reindex as $index_item)');
	expect($source)->toContain('switch ($index_item[\'action\'])');
});

test('unset($output) sits inside the reindex foreach before the action switch', function () use ($source) {
	$loopStart   = strpos($source, 'foreach ($reindex as $index_item)');
	expect($loopStart)->not->toBeFalse();

	$switchStart = strpos($source, 'switch ($index_item[\'action\'])', $loopStart);
	expect($switchStart)->not->toBeFalse();

	$unsetPos    = strpos($source, 'unset($output)', $loopStart);
	expect($unsetPos)->not->toBeFalse('unset($output) must be present inside the reindex body');
	expect($unsetPos < $switchStart)->toBeTrue('unset($output) must precede the action switch');

	/* The assert_fail reset must remain. */
	expect($source)->toContain('$assert_fail = false;');
});
