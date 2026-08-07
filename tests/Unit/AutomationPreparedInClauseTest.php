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

if (!function_exists('cacti_sizeof')) {
	/**
	 * Returns the size of an array test value.
	 *
	 * @param mixed $value Value to count.
	 *
	 * @return int Number of elements.
	 */
	function cacti_sizeof(mixed $value) : int {
		return is_array($value) ? count($value) : 0;
	}
}

require_once dirname(__DIR__, 2) . '/lib/api_automation_tools.php';
require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

test('ID list normalization is strict and fail closed', function () : void {
	expect(cacti_sizeof(new ArrayObject([1])))->toBe(0)
		->and(automation_prepare_id_list(false))->toBe(['placeholders' => '', 'params' => []])
		->and(automation_prepare_id_list([]))->toBe(['placeholders' => '', 'params' => []])
		->and(automation_prepare_id_list('7'))->toBe(['placeholders' => '?', 'params' => [7]])
		->and(automation_prepare_id_list([2, '9']))->toBe(['placeholders' => '?, ?', 'params' => [2, 9]])
		->and(automation_prepare_id_list('1e3'))->toBeFalse()
		->and(automation_prepare_id_list('1 OR 1=1'))->toBeFalse()
		->and(automation_prepare_id_list((string) PHP_INT_MAX))->toBe(['placeholders' => '?', 'params' => [PHP_INT_MAX]])
		->and(automation_prepare_id_list((string) PHP_INT_MAX . '0'))->toBeFalse()
		->and(automation_prepare_id_list(str_repeat('9', strlen((string) PHP_INT_MAX) + 1)))->toBeFalse()
		->and(automation_prepare_id_list(0))->toBeFalse()
		->and(automation_prepare_id_list(-1))->toBeFalse()
		->and(automation_prepare_id_list(1.5))->toBeFalse();
});

test('all three host-template filters bind dynamic IN clause values', function () : void {
	$verdict = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_prepared_in_clause_probe.php');
	$queries = $verdict['queries'];

	expect($queries)->toHaveCount(3)
		->and($queries[0][0])->toContain('ht.id IN (?, ?)')
		->and($queries[0][1])->toBe([1, 2])
		->and($queries[1][0])->toContain('ht.id IN (?)')
		->and($queries[1][1])->toBe([3])
		->and($queries[2][0])->toContain('htg.host_template_id IN (?, ?)')
		->and($queries[2][1])->toBe([4, 5]);
});

test('invalid ID lists never reach the database', function () : void {
	$verdict = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_prepared_in_clause_probe.php');

	expect($verdict['invalid_results'])->toBe([false, false, false])
		->and($verdict['invalid_query_count'])->toBe(0);
});
