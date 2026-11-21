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

// The probe runs in its own process because it loads the external link
// renderer, which the confinement test also loads into this one.
test('the path containment probe passes against a real tree', function () {
	expect(cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/PathContainmentMatrix.php'))
		->toBe(['passed' => 38]);
});
