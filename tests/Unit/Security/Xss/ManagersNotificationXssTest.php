<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$managersSource = file_get_contents(__DIR__ . '/../../../../managers.php');

test('snmp notification fallback output is html escaped', function () use ($managersSource) {
	expect($managersSource)->toContain("print '<td>' . html_escape(\$item['notification']) . '</td>';");
});

require_once __DIR__ . '/../../../Helpers/IsolatedProbe.php';

test('notification confirmation posts back plain selections unchanged', function () {
	$verdict = cacti_test_isolated_probe(__DIR__ . '/../../../fixtures/Core/ManagersNotificationConfirmationProbe.php', array('plain'));

	expect($verdict['inputs'])->toBe(1)
		->and($verdict['value'])->toBe(serialize(array('IF-MIB' => array('linkDown' => 1, 'linkUp' => 1))));
});

test('notification confirmation keeps quoted names inside the hidden input', function () {
	$verdict = cacti_test_isolated_probe(__DIR__ . '/../../../fixtures/Core/ManagersNotificationConfirmationProbe.php', array('quoted'));

	expect($verdict['inputs'])->toBe(1)
		->and($verdict['elements'])->toBe(0)
		->and($verdict['value'])->toBe($verdict['expected']);
});

test('notification confirmation preserves quoted action values without adding event attributes', function () {
	$verdict = cacti_test_isolated_probe(__DIR__ . '/../../../fixtures/Core/ManagersNotificationConfirmationProbe.php', array('action-quoted'));

	expect($verdict['inputs'])->toBe(1)
		->and($verdict['action'])->toBe($verdict['expected_action'])
		->and($verdict['event_attributes'])->toBe(0);
});
