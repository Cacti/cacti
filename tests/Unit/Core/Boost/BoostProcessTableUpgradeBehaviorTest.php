<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

function boost12TestReset(array $overrides = array()) {
	$GLOBALS['boost12_test_state'] = array_merge(array(
		'table'    => true,
		'run_id'   => true,
		'child_id' => true,
		'key'      => true,
		'rows'     => 3,
		'fail_on'  => '',
		'appear_on_failure' => '',
		'table_cache'       => null,
		'column_cache'      => array(),
		'sql'      => array(),
		'logs'     => array(),
	), $overrides);
}

function boost12TestTableExists($table) {
	$state =& $GLOBALS['boost12_test_state'];

	if ($state['table_cache'] === null) {
		$state['table_cache'] = $state['table'];
	}

	return $state['table_cache'];
}

function boost12TestColumnExists($table, $column) {
	$state =& $GLOBALS['boost12_test_state'];

	if (!array_key_exists($column, $state['column_cache'])) {
		$state['column_cache'][$column] = $state[$column];
	}

	return $state['column_cache'][$column];
}

function boost12TestTableExistsUncached() {
	return $GLOBALS['boost12_test_state']['table'];
}

function boost12TestColumnExistsUncached($column) {
	return $GLOBALS['boost12_test_state'][$column];
}

function boost12TestIndexExists($table, $index) {
	return $GLOBALS['boost12_test_state']['key'];
}

function boost12TestExecute($sql) {
	$state =& $GLOBALS['boost12_test_state'];
	$state['sql'][] = $sql;

	if ($state['fail_on'] !== '' && strpos($sql, $state['fail_on']) !== false) {
		if ($state['appear_on_failure'] === 'table') {
			$state['table'] = true;
		} elseif ($state['appear_on_failure'] === 'run_id') {
			$state['run_id'] = true;
		} elseif ($state['appear_on_failure'] === 'child_id') {
			$state['child_id'] = true;
		} elseif ($state['appear_on_failure'] === 'key') {
			$state['key'] = true;
		}

		return false;
	}

	if (strpos($sql, 'CREATE TABLE') !== false) {
		$state['table'] = $state['run_id'] = $state['child_id'] = $state['key'] = true;
	} elseif (strpos($sql, 'ADD `run_id`') !== false) {
		$state['run_id'] = true;
	} elseif (strpos($sql, 'ADD `child_id`') !== false) {
		$state['child_id'] = true;
	} elseif (strpos($sql, 'TRUNCATE TABLE') !== false) {
		$state['rows'] = 0;
	} elseif (strpos($sql, 'ADD UNIQUE KEY') !== false) {
		$state['key'] = true;
	}

	return true;
}

function boost12TestLog($message, $output = false, $facility = '') {
	$GLOBALS['boost12_test_state']['logs'][] = array($message, $output, $facility);
}

function boost12LoadEnsureFunction($root) {
	if (function_exists('boost12TestEnsureProcessTable')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function boost_ensure_process_table(');
	expect($start)->not->toBeFalse();

	$body  = strpos($source, '{', $start);
	$depth = 0;
	$end   = false;

	for ($offset = $body, $length = strlen($source); $offset < $length; $offset++) {
		if ($source[$offset] === '{') {
			$depth++;
		} elseif ($source[$offset] === '}') {
			$depth--;

			if ($depth === 0) {
				$end = $offset + 1;
				break;
			}
		}
	}

	expect($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'boost_ensure_process_table',
		'boost_process_table_exists_uncached',
		'boost_process_column_exists_uncached',
		'db_table_exists',
		'db_column_exists',
		'db_index_exists',
		'db_execute',
		'cacti_log',
	), array(
		'boost12TestEnsureProcessTable',
		'boost12TestTableExistsUncached',
		'boost12TestColumnExistsUncached',
		'boost12TestTableExists',
		'boost12TestColumnExists',
		'boost12TestIndexExists',
		'boost12TestExecute',
		'boost12TestLog',
	), $function);

	eval($function);
}

beforeEach(function () use ($root) {
	boost12LoadEnsureFunction($root);
	boost12TestReset();
});

test('runtime recovery creates the complete process table when it is missing', function () {
	boost12TestReset(array('table' => false, 'run_id' => false, 'child_id' => false, 'key' => false));

	expect(boost12TestEnsureProcessTable())->toBeTrue()
		->and($GLOBALS['boost12_test_state']['sql'])->toHaveCount(1)
		->and($GLOBALS['boost12_test_state']['sql'][0])->toContain('UNIQUE KEY `run_child` (`run_id`, `child_id`)')
		->and($GLOBALS['boost12_test_state']['key'])->toBeTrue();
});

test('runtime recovery is a no-op for a complete process table', function () {
	expect(boost12TestEnsureProcessTable(true))->toBeTrue()
		->and($GLOBALS['boost12_test_state']['sql'])->toBe(array())
		->and($GLOBALS['boost12_test_state']['rows'])->toBe(3);
});

test('parent recovery adds missing columns without discarding completion rows', function () {
	boost12TestReset(array('run_id' => false, 'child_id' => false, 'key' => false));

	expect(boost12TestEnsureProcessTable(false))->toBeTrue()
		->and($GLOBALS['boost12_test_state']['run_id'])->toBeTrue()
		->and($GLOBALS['boost12_test_state']['child_id'])->toBeTrue()
		->and($GLOBALS['boost12_test_state']['key'])->toBeFalse()
		->and($GLOBALS['boost12_test_state']['rows'])->toBe(3);
});

test('child recovery truncates incompatible rows before adding the unique key', function () {
	boost12TestReset(array('key' => false));

	expect(boost12TestEnsureProcessTable(true))->toBeTrue()
		->and($GLOBALS['boost12_test_state']['rows'])->toBe(0)
		->and($GLOBALS['boost12_test_state']['key'])->toBeTrue()
		->and(implode("\n", $GLOBALS['boost12_test_state']['sql']))->toContain('TRUNCATE TABLE')
		->and(implode("\n", $GLOBALS['boost12_test_state']['sql']))->toContain('ADD UNIQUE KEY');
});

test('runtime recovery fails closed and logs the first schema error', function () {
	boost12TestReset(array('run_id' => false, 'child_id' => false, 'key' => false, 'fail_on' => 'ADD `child_id`'));

	expect(boost12TestEnsureProcessTable(true))->toBeFalse()
		->and($GLOBALS['boost12_test_state']['key'])->toBeFalse()
		->and($GLOBALS['boost12_test_state']['logs'])->toHaveCount(1)
		->and($GLOBALS['boost12_test_state']['logs'][0][0])->toContain('Unable to add child_id');
});

test('runtime recovery accepts a concurrent table creator after create reports failure', function () {
	boost12TestReset(array(
		'table'             => false,
		'run_id'            => false,
		'child_id'          => false,
		'key'               => false,
		'fail_on'           => 'CREATE TABLE',
		'appear_on_failure' => 'table',
	));

	expect(boost12TestEnsureProcessTable(true))->toBeTrue()
		->and($GLOBALS['boost12_test_state']['logs'])->toBe(array());
});

test('runtime recovery accepts a concurrent run_id column repair', function () {
	boost12TestReset(array(
		'run_id'            => false,
		'fail_on'           => 'ADD `run_id`',
		'appear_on_failure' => 'run_id',
	));

	expect(boost12TestEnsureProcessTable())->toBeTrue()
		->and($GLOBALS['boost12_test_state']['logs'])->toBe(array());
});

test('runtime recovery accepts a concurrent child_id column repair', function () {
	boost12TestReset(array(
		'child_id'          => false,
		'fail_on'           => 'ADD `child_id`',
		'appear_on_failure' => 'child_id',
	));

	expect(boost12TestEnsureProcessTable())->toBeTrue()
		->and($GLOBALS['boost12_test_state']['logs'])->toBe(array());
});

test('runtime recovery accepts a concurrent unique-key repair', function () {
	boost12TestReset(array(
		'key'               => false,
		'fail_on'           => 'ADD UNIQUE KEY',
		'appear_on_failure' => 'key',
	));

	expect(boost12TestEnsureProcessTable(true))->toBeTrue()
		->and($GLOBALS['boost12_test_state']['rows'])->toBe(0)
		->and($GLOBALS['boost12_test_state']['logs'])->toBe(array());
});

test('runtime recovery fails closed when process-row truncation fails', function () {
	boost12TestReset(array('key' => false, 'fail_on' => 'TRUNCATE TABLE'));

	expect(boost12TestEnsureProcessTable(true))->toBeFalse()
		->and($GLOBALS['boost12_test_state']['key'])->toBeFalse()
		->and($GLOBALS['boost12_test_state']['logs'])->toHaveCount(1)
		->and($GLOBALS['boost12_test_state']['logs'][0][0])->toContain('Unable to truncate');
});
