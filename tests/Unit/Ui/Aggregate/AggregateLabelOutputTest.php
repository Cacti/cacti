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

test('aggregate GPRINT, CDEF and VDEF names render as text (#7463)', function (string $label) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/aggregate_label_render_probe.php', ['label', $label]);

	expect($result['names'])->toBe([$label, $label, $label])
		->and($result['elements'])->toBe(0);
})->with(['Traffic & bandwidth', '<em>Literal label</em>', 'Quoted "label"']);

test('aggregate items without GPRINT, CDEF or VDEF names render empty cells', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/aggregate_label_render_probe.php', ['empty']);

	expect($result['names'])->toBe(['', '', '']);
});
