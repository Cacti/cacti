<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

test('GHSA-mx5c-qj6m-2w89: 1.2 branch has no unauthenticated REST API v1 entrypoint', function () {
	$apiEntrypoint = dirname(__DIR__, 2) . '/api/public/index.php';

	if (!file_exists($apiEntrypoint)) {
		expect(true)->toBeTrue();
		return;
	}

	$source = file_get_contents($apiEntrypoint);
	expect($source)->toContain('Authorization');
	expect($source)->toContain("/v1");
	expect($source)->toContain('Unauthorized');
});
