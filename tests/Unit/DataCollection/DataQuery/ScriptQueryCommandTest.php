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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('script query base commands are escaped without changing arguments (#7478)', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ScriptQueryCommand.php');

	expect($result['commands'][0])->toBe('/opt/cacti/query.php ')
		->and($result['commands'][1])->toBe('/opt/cacti/label\\;literal ')
		->and($result['arguments'])->toBe("/opt/cacti/query.php 'alpha' 'two words' 'semi;colon'")
		->and($result['rejected'])->toBe(3)
		->and($result['passes'])->toBe(10000);
});
