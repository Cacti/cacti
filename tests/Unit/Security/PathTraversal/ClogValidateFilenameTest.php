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

require_once dirname(__DIR__, 4) . '/lib/clog_webapi.php';

/*
 * On Windows a trailing dot or space, or a drive/stream colon, makes a file
 * name resolve back to the active log while still comparing unequal to it, so
 * clog_validate_filename() refuses those forms.
 */

test('log names with a trailing dot or space or a colon are refused', function (string $name) {
	$file = $name;
	$path = '';
	$base = '';

	expect(@clog_validate_filename($file, $path, $base))->toBeFalse()
		->and($path)->toBe('')
		->and($base)->toBe('');
})->with([
	'trailing dot'   => ['cacti.log.'],
	'trailing space' => ['cacti.log '],
	'colon stream'   => ['cacti.log:stream'],
	'drive colon'    => ['cacti.log:$DATA'],
]);

test('the active and rotated log names are still accepted', function (string $name) {
	$file = $name;
	$path = '';
	$base = '';

	expect(@clog_validate_filename($file, $path, $base))->toBeTrue()
		->and($base)->toBe('cacti.log');
})->with(['cacti.log', 'cacti.log-20260101', 'cacti.log.1']);
