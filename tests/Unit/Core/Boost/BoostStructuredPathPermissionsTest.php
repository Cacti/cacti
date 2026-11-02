<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$root = dirname(__DIR__, 4);

test('structured RRA paths preserve inherited owner group and setgid permissions', function () use ($root) {
	$result = cacti_test_isolated_probe($root . '/tests/fixtures/Core/BoostStructuredPathPermissions.php');

	expect($result['repaired'])->toBeTrue()
		->and($result['outside'])->toBeFalse()
		->and($result['owners']['/rra/36'])->toBe(33)
		->and($result['groups']['/rra/36'])->toBe(33)
		->and($result['groups']['/rra/36/88'])->toBe(33)
		->and($result['modes']['/rra/36'])->toBe(02775)
		->and($result['modes']['/rra/36/88'])->toBe(02770)
		->and($result['chown'])->toBe(array(array('/rra/36', 33)))
		->and($result['chgrp'])->toBe(array(array('/rra/36/88', 33)));
});

test('normal and Boost RRD creation repair existing structured directories', function () use ($root) {
	$rrd   = file_get_contents($root . '/lib/rrd.php');
	$boost = file_get_contents($root . '/lib/boost.php');

	expect($rrd)->toContain("rrdtool_set_structured_path_permissions(\$structured_path, 'POLLER')")
		->and($boost)->toContain("rrdtool_set_structured_path_permissions(\$structured_path, 'BOOST')")
		->and($rrd)->not->toContain('$pgroup_id = fileowner(')
		->and($boost)->not->toContain('$pgroup_id = fileowner(');
});
