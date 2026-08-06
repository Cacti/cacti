<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (!function_exists('cacti_sizeof')) {
	/**
	 * Returns the size of a countable test value.
	 *
	 * @param mixed $value Value to count.
	 *
	 * @return int Number of elements.
	 */
	function cacti_sizeof($value) {
		return is_countable($value) ? count($value) : 0;
	}
}

require_once dirname(__DIR__, 2) . '/lib/api_automation_tools.php';

/**
 * Runs the prepared-query probe in a clean PHP process.
 *
 * @return array Decoded probe verdict.
 */
function automation_run_prepared_probe() {
	$script  = dirname(__DIR__) . '/fixtures/automation_prepared_in_clause_probe.php';
	$process = proc_open(array(PHP_BINARY, $script), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start automation prepared-query probe');
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	$status = proc_close($process);

	if ($status !== 0) {
		throw new RuntimeException('Automation prepared-query probe failed: ' . trim($stderr));
	}

	$result = json_decode(trim($stdout), true);

	if (!is_array($result)) {
		throw new RuntimeException('Automation prepared-query probe returned invalid JSON');
	}

	return $result;
}

test('ID list normalization is strict and fail closed', function () {
	expect(automation_prepare_id_list(false))->toBe(array('placeholders' => '', 'params' => array()))
		->and(automation_prepare_id_list(array()))->toBe(array('placeholders' => '', 'params' => array()))
		->and(automation_prepare_id_list('7'))->toBe(array('placeholders' => '?', 'params' => array(7)))
		->and(automation_prepare_id_list(array(2, '9')))->toBe(array('placeholders' => '?, ?', 'params' => array(2, 9)))
		->and(automation_prepare_id_list('1e3'))->toBeFalse()
		->and(automation_prepare_id_list('1 OR 1=1'))->toBeFalse()
		->and(automation_prepare_id_list((string) PHP_INT_MAX))->toBe(array('placeholders' => '?', 'params' => array(PHP_INT_MAX)))
		->and(automation_prepare_id_list((string) PHP_INT_MAX . '0'))->toBeFalse()
		->and(automation_prepare_id_list(str_repeat('9', strlen((string) PHP_INT_MAX) + 1)))->toBeFalse()
		->and(automation_prepare_id_list(0))->toBeFalse()
		->and(automation_prepare_id_list(-1))->toBeFalse()
		->and(automation_prepare_id_list(1.5))->toBeFalse();
});

test('all three host-template filters bind dynamic IN clause values', function () {
	$verdict = automation_run_prepared_probe();
	$queries = $verdict['queries'];

	expect($queries)->toHaveCount(3)
		->and($queries[0][0])->toContain('ht.id IN (?, ?)')
		->and($queries[0][1])->toBe(array(1, 2))
		->and($queries[1][0])->toContain('ht.id IN (?)')
		->and($queries[1][1])->toBe(array(3))
		->and($queries[2][0])->toContain('htg.host_template_id IN (?, ?)')
		->and($queries[2][1])->toBe(array(4, 5));
});

test('invalid ID lists never reach the database', function () {
	$verdict = automation_run_prepared_probe();

	expect($verdict['invalid_results'])->toBe(array(false, false, false))
		->and($verdict['invalid_query_count'])->toBe(0);
});
