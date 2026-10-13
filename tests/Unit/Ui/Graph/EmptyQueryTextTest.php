<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('graph query text clears known empty values without changing suggested titles (#2815)', function () {
	$probe  = dirname(__DIR__, 3) . '/fixtures/EmptyQueryTextProbe.php';
	$output = [];
	$status = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1', $output, $status);
	expect($status)->toBe(0, implode("\n", $output));
	expect(json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR))->toBe([
		'Interface Description: ', ' / Ethernet 1', ' ', '', '0', '', '|query_unknown|', '', '|query_IfAlias|', 'Ethe',
	]);
});
