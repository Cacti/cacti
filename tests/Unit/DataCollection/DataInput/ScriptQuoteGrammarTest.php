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

test('script substitution rejects unsupported dollar quotes and preserves escaped dollars', function () {
	ob_start();

	try {
		require dirname(__DIR__, 3) . '/fixtures/Core/ScriptQuoteGrammar.php';
	} finally {
		$output = ob_get_clean();
	}

	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR)['passed'])->toBe(21);
});
