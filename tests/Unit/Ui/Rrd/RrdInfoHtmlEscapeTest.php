<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('rrdtool_info2html escapes every value it takes from rrdtool info', function () {
	$verdict = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/RrdInfoHtmlEscape.php');

	expect($verdict['passed'])->toBe(48);
});
