<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('graph item lists render CDEF and VDEF names as text', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/GraphItemsListMarkup.php');
	$label  = '<img src=x onerror=alert(1)>';

	expect($result['images'])->toBe(0)
		->and($result['rows'])->toBe([[$label, $label], ['-', '-']]);
});
