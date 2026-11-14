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

test('theme switching stores an allowlisted theme name', function ($requested) {
	$previous = [$_SESSION, $_REQUEST];

	try {
		$_REQUEST['newtheme'] = $requested;
		cacti_apply_newtheme_request();
		expect($_SESSION['selected_theme'])->toBeString()->not->toContain('..')->not->toContain('/')
			->and(is_dir(CACTI_PATH_INCLUDE . '/themes/' . $_SESSION['selected_theme']))->toBeTrue();
	} finally {
		[$_SESSION, $_REQUEST] = $previous;
	}
})->with(['../themes/modern', '../../../tmp/modern', 'modern', [['modern']]]);
