<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

// The probe loads production functions into a fixture namespace with a
// recording die_html_input_error(), so it runs in its own process.
test('the typed request accessors validate like get_filter_request_var()', function () {
	expect(cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/RequestAccessorMatrix.php'))->toBe(['passed' => 116]);
});
