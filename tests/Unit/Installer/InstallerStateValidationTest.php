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

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';

test('installer validates wizard state, theme names, runtime and setup guidance', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/InstallerStateValidation.php');

	expect($result['stale_cases'])->toBe(6)
		->and($result['theme_cases'])->toBe(6)
		->and($result['runtime'])->toBe('Web')
		->and($result['password_rendered'])->toBeFalse();
});
