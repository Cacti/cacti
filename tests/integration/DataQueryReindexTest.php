<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Test\DataQuery;

require_once dirname(__DIR__) . '/Helpers/DataQueryHarness.php';

beforeEach(function () {
	$GLOBALS['dq_session_before_test'] = $_SESSION ?? null;
	if (!getenv('CACTI_DQ_DB_PORT')) {
		$this->markTestSkipped('Set CACTI_DQ_DB_PORT for an isolated cacti_dq_test MariaDB database');
	}
	reset_fixture();
});

afterEach(function () {
	if ($GLOBALS['dq_session_before_test'] === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $GLOBALS['dq_session_before_test'];
	}
});

function cache_snapshot($table = 'host_snmp_cache') {
	$columns = $table === 'host_snmp_cache' ? 'host_id,snmp_query_id,field_name,field_value,snmp_index,oid,present' : 'host_id,data_query_id,action,op,assert_value,arg1,present';
	return sql('SELECT ' . $columns . ' FROM ' . $table . ' ORDER BY 1,2,3,4,5,6')->fetchAll(\PDO::FETCH_ASSOC);
}

test('a complete unchanged query preserves all data-source identities', function () {
	expect(run_data_query(1, 1))->toBeTrue()
		->and(sql('SELECT id,snmp_index,orphan FROM data_local ORDER BY id')->fetchAll(\PDO::FETCH_NUM))->toBe(array(array(1, '1', 0), array(2, '2', 0)))
		->and($GLOBALS['pushes'])->toBe(array())
		->and($GLOBALS['poller_updates'])->toBe(array());
});

test('a sort-field migration is independent of source row order', function ($reverse_rows) {
	sql("UPDATE host_snmp_query SET sort_field='ifDescr'");
	sql("UPDATE data_input_data SET value='ifDescr' WHERE data_template_data_id=2 AND data_input_field_id=2");
	sql("UPDATE data_input_data SET value='description2' WHERE data_template_data_id=2 AND data_input_field_id=1");
	$GLOBALS['reverse'] = $reverse_rows;
	expect(run_data_query(1, 1))->toBeTrue()
		->and(sql('SELECT value FROM data_input_data WHERE data_template_data_id=2 ORDER BY data_input_field_id')->fetchAll(\PDO::FETCH_COLUMN))->toBe(array('port2', 'ifName'));
})->with(array(false, true));

test('an orphan returning at its original index resumes polling', function () {
	$GLOBALS['xml']['index_orphan_removal'] = 'true';
	$complete = $GLOBALS['script'];
	$GLOBALS['script'] = array('index' => array('1'), 'count' => array('1'), 'query ifName' => array('1!port1'), 'query ifDescr' => array('1!description1'));
	expect(run_data_query(1, 1))->toBeTrue()
		->and((int) sql('SELECT orphan FROM data_local WHERE id=2')->fetchColumn())->toBe(1)
		->and((int) sql('SELECT COUNT(*) FROM poller_item WHERE local_data_id=2')->fetchColumn())->toBe(0);
	$GLOBALS['script'] = $complete;
	expect(run_data_query(1, 1))->toBeTrue()
		->and((int) sql('SELECT orphan FROM data_local WHERE id=2')->fetchColumn())->toBe(0)
		->and($GLOBALS['pushes'])->toBe(array(array(1, 2, 0)))
		->and((int) sql('SELECT COUNT(*) FROM poller_item WHERE local_data_id=2')->fetchColumn())->toBe(1);
});

test('an incomplete identity field retains the complete previous cache', function ($values) {
	$before = cache_snapshot();
	$GLOBALS['xml']['index_orphan_removal'] = 'true';
	$GLOBALS['script']['query ifName'] = $values;
	expect(run_data_query(1, 1))->toBeFalse()
		->and(cache_snapshot())->toBe($before)
		->and((int) sql('SELECT COUNT(*) FROM poller_item')->fetchColumn())->toBe(2)
		->and((int) sql('SELECT SUM(orphan) FROM data_local')->fetchColumn())->toBe(0);
})->with(array('empty' => array(array()), 'partial' => array(array('1!port1'))));

test('a previously collected field cannot hide a later partial script result', function () {
	expect(query_script_host(1, 1))->toBeTrue();
	$before = cache_snapshot();
	$GLOBALS['script']['query ifName'] = array('1!port1');
	expect(query_script_host(1, 1))->toBeFalse()->and(cache_snapshot())->toBe($before);
});

test('initial discovery may choose a supported alternate identity field', function () {
	sql('DELETE FROM data_local');
	$GLOBALS['script']['query ifName'] = array();
	expect(run_data_query(1, 1))->toBeTrue()
		->and(sql('SELECT sort_field FROM host_snmp_query')->fetchColumn())->toBe('ifDescr');
});

test('cache replacement rolls back each failed stage', function ($stage) {
	$before = cache_snapshot();
	$GLOBALS['script']['query ifName'] = array('1!changed1', '2!changed2');
	if ($stage === 'begin' || $stage === 'commit') {
		$GLOBALS['dq_' . $stage . '_failure'] = true;
	} else {
		$GLOBALS['dq_failure'] = $stage;
	}
	expect(query_script_host(1, 1))->toBeFalse()
		->and(cache_snapshot())->toBe($before)
		->and($GLOBALS['db']->inTransaction())->toBeFalse();
})->with(array('begin', '/SELECT host_id FROM host_snmp_query/', '/UPDATE host_snmp_cache/', '/INSERT INTO host_snmp_cache/', '/DELETE FROM host_snmp_cache/', 'commit'));

test('a real MariaDB insertion error rolls back the mark phase', function () {
	$before = cache_snapshot();
	sql("CREATE TRIGGER dq_reject_insert BEFORE INSERT ON host_snmp_cache FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture insertion failure'");
	try {
		expect(query_script_host(1, 1))->toBeFalse()->and(cache_snapshot())->toBe($before);
	} finally {
		sql('DROP TRIGGER dq_reject_insert');
	}
});

test('a real lock timeout retains the cache and the next attempt succeeds', function () {
	$before = cache_snapshot();
	connect_fixture();
	$blocker = $GLOBALS['db'];
	connect_fixture();
	sql('SET SESSION innodb_lock_wait_timeout=1');
	$blocker->beginTransaction();
	$blocker->query('SELECT host_id FROM host_snmp_query WHERE host_id=1 AND snmp_query_id=1 FOR UPDATE')->fetchAll();
	try {
		expect(query_script_host(1, 1))->toBeFalse()
			->and(cache_snapshot())->toBe($before)
			->and($GLOBALS['db']->inTransaction())->toBeFalse();
	} finally {
		$blocker->rollBack();
	}
	expect(query_script_host(1, 1))->toBeTrue()->and(cache_snapshot())->toBe($before);
});

test('an incomplete forced identity also preserves the previous generation', function () {
	sql("UPDATE data_input_data SET value='ifDescr' WHERE data_input_field_id=2 AND data_template_data_id=2");
	$before = cache_snapshot();
	$GLOBALS['script']['query ifDescr'] = array('1!description1');
	expect(query_script_host(1, 1))->toBeFalse()->and(cache_snapshot())->toBe($before);
});

test('a missing query association does not delete cached rows', function () {
	$before = cache_snapshot();
	sql('DELETE FROM host_snmp_query');
	$records = array();
	$empty = array();
	expect(data_query_update_host_cache_from_buffer(1, 1, $records, $empty))->toBeFalse()
		->and(cache_snapshot())->toBe($before);
});

test('a failure after a successful insert batch also rolls back that batch', function () {
	$before = cache_snapshot();
	$records = array();
	for ($i = 1; $i <= 2000; $i++) {
		$records[] = data_query_format_record(1, 1, 'ifName', null, str_repeat('a', 400), (string) $i, '');
	}
	$empty = array();
	$GLOBALS['dq_failure'] = '/INSERT INTO host_snmp_cache/';
	$GLOBALS['dq_failure_at'] = 2;
	expect(data_query_update_host_cache_from_buffer(1, 1, $records, $empty))->toBeFalse()
		->and($GLOBALS['dq_failure_hits'])->toBe(2)
		->and(cache_snapshot())->toBe($before);
});

test('reindex baseline failures retain the old baseline and do not replicate it', function ($failure) {
	sql("INSERT INTO poller_reindex (host_id,data_query_id,action,op,assert_value,arg1,present) VALUES (1,1,1,'=','2','count',1)");
	$before = cache_snapshot('poller_reindex');
	$stack = array("(1,1,1,'=','3','count',1)");
	$GLOBALS['dq_failure'] = $failure;
	expect(poller_update_poller_reindex_from_buffer(1, 1, $stack))->toBeFalse()
		->and(cache_snapshot('poller_reindex'))->toBe($before)
		->and($GLOBALS['dq_replicated'])->toBe(array());
})->with(array('/UPDATE poller_reindex/', '/INSERT INTO poller_reindex/', '/DELETE FROM poller_reindex/'));

test('successful baseline replacement replicates only committed rows', function () {
	$stack = array("(1,1,1,'=','3','count',1)");
	expect(poller_update_poller_reindex_from_buffer(1, 1, $stack))->toBeTrue()
		->and($GLOBALS['db']->inTransaction())->toBeFalse()
		->and($GLOBALS['dq_replicated'])->toBe(array(array(1, 1)))
		->and(sql('SELECT assert_value FROM poller_reindex')->fetchColumn())->toBe('3');
});

test('a baseline failure still propagates changed indexes before returning failure', function () {
	sql('UPDATE host_snmp_query SET reindex_method=2');
	$GLOBALS['script']['index'] = array('11', '12');
	$GLOBALS['script']['query ifName'] = array('11!port1', '12!port2');
	$GLOBALS['script']['query ifDescr'] = array('11!description1', '12!description2');
	$GLOBALS['dq_failure'] = '/INSERT INTO poller_reindex/';
	expect(run_data_query(1, 1))->toBeFalse()
		->and($GLOBALS['poller_updates'])->toBe(array(array(1, 2)));
});

test('cache refresh does not commit or roll back its callers transaction', function () {
	$before = cache_snapshot();
	$GLOBALS['db']->beginTransaction();
	try {
		expect(query_script_host(1, 1))->toBeFalse()
			->and($GLOBALS['db']->inTransaction())->toBeTrue()
			->and(cache_snapshot())->toBe($before);
	} finally {
		$GLOBALS['db']->rollBack();
	}
});

test('batched identity matching preserves database collation and overrides', function () {
	sql("UPDATE data_input_data SET value='PORT1   ' WHERE data_template_data_id=1 AND data_input_field_id=1");
	sql("UPDATE data_input_data SET value='ifDescr' WHERE data_template_data_id=2 AND data_input_field_id=2");
	sql("UPDATE data_input_data SET value='DESCRIPTION2' WHERE data_template_data_id=2 AND data_input_field_id=1");
	$rows = data_query_local_data(1, 1, 'ifName', 'ifDescr');
	expect(array_column($rows, 'current_index', 'local_data_id'))->toBe(array(1 => '1', 2 => '2'))
		->and(array_column($rows, 'index_type', 'local_data_id'))->toBe(array(1 => 'ifName', 2 => 'ifDescr'));
});

/**
 * Converts host_snmp_cache and data_input_data to different collations of one character set,
 * as a partially converted upgrade leaves them, runs the callback, and drops both tables so the
 * next fixture reset recreates them from cacti.sql.
 *
 * @param callable $callback The assertions to run against the mixed-collation schema.
 *
 * @return void
 */
function with_mixed_collations($callback) {
	try {
		sql('ALTER TABLE host_snmp_cache CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
		sql('ALTER TABLE data_input_data CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
		$callback();
	} finally {
		sql('DROP TABLE IF EXISTS host_snmp_cache, data_input_data');
	}
}

test('identity matching works when the cache and input tables use different collations', function () {
	with_mixed_collations(function () {
		sql("UPDATE data_input_data SET value='PORT1   ' WHERE data_template_data_id=1 AND data_input_field_id=1");
		$rows = data_query_local_data(1, 1, 'ifName', 'ifName');
		expect($rows)->toBeArray()
			->and(array_column($rows, 'current_index', 'local_data_id'))->toBe(array(1 => '1', 2 => '2'));
		sql("UPDATE data_input_data SET value='port1' WHERE data_template_data_id=1 AND data_input_field_id=1");
		expect(run_data_query(1, 1))->toBeTrue()
			->and(sql('SELECT id,snmp_index,orphan FROM data_local ORDER BY id')->fetchAll(\PDO::FETCH_NUM))->toBe(array(array(1, '1', 0), array(2, '2', 0)));
	});
});

test('identity matching works when the field_value index is missing', function () {
	try {
		sql('ALTER TABLE host_snmp_cache DROP INDEX field_value');
		$rows = data_query_local_data(1, 1, 'ifName', 'ifName');
		expect($rows)->toBeArray()
			->and(array_column($rows, 'current_index', 'local_data_id'))->toBe(array(1 => '1', 2 => '2'));
	} finally {
		sql('DROP TABLE IF EXISTS host_snmp_cache');
	}
});

test('an unreadable cache collation falls back to the plain comparison', function () {
	expect(data_query_cache_compare('did.value', 'field_value', array()))->toBe('did.value')
		->and(data_query_cache_compare('did.value', 'field_value', array('field_value' => array('charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'))))
		->toBe('CONVERT(did.value USING utf8mb4) COLLATE utf8mb4_bin');
	$GLOBALS['dq_failure'] = '/information_schema/';
	expect(data_query_cache_collations())->toBe(array())
		->and(data_query_local_data(1, 1, 'ifName', 'ifName'))->toHaveCount(2);
});

test('a new index without an identity value does not block discovery', function () {
	$GLOBALS['script']['index'][] = '3';
	$GLOBALS['script']['count'] = array('3');
	$GLOBALS['script']['query ifDescr'][] = '3!description3';
	expect(run_data_query(1, 1))->toBeTrue()
		->and((int) sql("SELECT COUNT(*) FROM host_snmp_cache WHERE snmp_index='3'")->fetchColumn())->toBe(1)
		->and(sql('SELECT id,snmp_index,orphan FROM data_local ORDER BY id')->fetchAll(\PDO::FETCH_NUM))->toBe(array(array(1, '1', 0), array(2, '2', 0)))
		->and($GLOBALS['dq_logs'])->not->toContain('ERROR: Device[1] DQ[1] identity field [ifName] is missing for index [3]. Retaining the previous data query cache.');
});

test('a field the data query no longer collects does not block discovery', function () {
	sql("UPDATE data_input_data SET value='ifAlias' WHERE data_template_data_id=2 AND data_input_field_id=2");
	expect(query_script_host(1, 1))->toBeTrue();
});

test('an orphaned source does not block discovery of its old index', function () {
	sql('UPDATE data_local SET orphan=1 WHERE id=2');
	$GLOBALS['script']['query ifName'] = array('1!port1');
	expect(query_script_host(1, 1))->toBeTrue();
});

test('removing a data query from a device drops its queued reindex', function () {
	sql("INSERT INTO poller_command (poller_id,time,action,command) VALUES (1,NOW(),1,'1:1'),(1,NOW(),1,'1:2'),(1,NOW(),2,'1')");
	api_device_dq_remove(1, 1);
	expect(sql('SELECT action,command FROM poller_command ORDER BY action,command')->fetchAll(\PDO::FETCH_NUM))->toBe(array(array(1, '1:2'), array(2, '1')))
		->and((int) sql('SELECT COUNT(*) FROM host_snmp_query')->fetchColumn())->toBe(0);
});

test('unchanged reindex database calls stay bounded as sources increase', function () {
	reset_fixture(1000);
	expect(run_data_query(1, 1))->toBeTrue()->and($GLOBALS['dq_calls'])->toBeLessThan(40);
});

test('bulk identity matching uses bounded indexed reads', function () {
	reset_fixture(1000);
	$before = (int) sql("SHOW SESSION STATUS LIKE 'Handler_read_next'")->fetch(\PDO::FETCH_NUM)[1];
	$rows = data_query_local_data(1, 1, 'ifName', 'ifName');
	$after = (int) sql("SHOW SESSION STATUS LIKE 'Handler_read_next'")->fetch(\PDO::FETCH_NUM)[1];
	expect($rows)->toHaveCount(1000)->and($after - $before)->toBeLessThan(30000);
});

test('duplicate identities produce one deterministic match per source', function () {
	sql("UPDATE host_snmp_cache SET field_value='port1' WHERE field_name='ifName'");
	$rows = data_query_local_data(1, 1, 'ifName', 'ifName');
	expect($rows)->toHaveCount(2)
		->and(array_column($rows, 'current_index', 'local_data_id'))->toBe(array(1 => '1', 2 => ''));
});

test('long identity values are never truncated into false matches', function () {
	$value = str_repeat('x', 512);
	sql("UPDATE host_snmp_cache SET field_value=? WHERE field_name='ifName' AND snmp_index='1'", array($value));
	sql('UPDATE data_input_data SET value=? WHERE data_input_field_id=1 AND data_template_data_id=1', array($value . 'y'));
	$rows = data_query_local_data(1, 1, 'ifName', 'ifName');
	expect(array_column($rows, 'current_index', 'local_data_id'))->toBe(array(1 => '', 2 => '2'));
});

test('an unavailable host keeps its reindex request until it can run', function ($disabled) {
	sql('UPDATE host SET status=?,disabled=?', array($disabled ? HOST_UP : HOST_DOWN, $disabled ? 'on' : ''));
	sql("INSERT INTO poller_command (poller_id,time,action,command) VALUES (1,NOW(),1,'1:1')");
	worker_fixture();
	expect((int) sql('SELECT COUNT(*) FROM poller_command')->fetchColumn())->toBe(1);
	sql("UPDATE host SET status=3,disabled=''");
	worker_fixture();
	expect((int) sql('SELECT COUNT(*) FROM poller_command')->fetchColumn())->toBe(0);
})->with(array(false, true));

test('remote discovery acknowledges only a successful response', function ($response, $expected) {
	sql('UPDATE host SET poller_id=2');
	$GLOBALS['dq_remote_response'] = $response;
	$automated = 0;
	$GLOBALS['dq_automation_callback'] = function () use (&$automated) { $automated++; };
	expect(run_data_query(1, 1))->toBe($expected)->and($automated)->toBe($expected ? 1 : 0);
})->with(array(
	'failure' => array('{"result":false,"data_query":"failed"}', false),
	'success' => array('{"result":true,"data_query":"complete"}', true),
	'missing log' => array('{"result":true}', true),
	'malformed' => array('{', false),
	'missing result' => array('{}', false),
	'empty' => array('', false),
));

test('automatic walks keep the selected successful session and close every trial', function ($failure) {
	sql('UPDATE host SET bulk_walk_size=0');
	$GLOBALS['xml'] = array('oid_index' => '.1.2.1', 'index_order' => 'ifName:ifIndex', 'fields' => array(
		'ifIndex' => array('direction' => 'input', 'source' => 'index'),
		'ifName' => array('direction' => 'input', 'source' => 'value', 'method' => 'walk', 'oid' => '.1.2.2'),
	));
	$GLOBALS['clock_samples'] = array(0.0, 0.001, 1.0, 1.01);
	$GLOBALS['fail_second_walk'] = $failure === 'walk';
	if ($failure === 'session') $GLOBALS['dq_session_failure_size'] = 5;
	expect(query_snmp_host(1, 1))->toBeTrue()
		->and((int) sql('SELECT bulk_walk_size FROM host')->fetchColumn())->toBe(1)
		->and(end($GLOBALS['walks']))->toBe(array('oid' => '.1.2.2', 'size' => 1));
	foreach ($GLOBALS['dq_sessions'] as $session) expect($session->closed)->toBeTrue();
})->with(array('slower', 'walk', 'session'));

test('a completely failed automatic discovery leaves cache and settings intact', function () {
	sql('UPDATE host SET bulk_walk_size=0');
	$before = cache_snapshot();
	$GLOBALS['xml'] = array('oid_index' => '.1.2.1', 'fields' => array());
	$GLOBALS['dq_session_failure_size'] = 1;
	expect(query_snmp_host(1, 1))->toBeFalse()
		->and(cache_snapshot())->toBe($before)
		->and((int) sql('SELECT bulk_walk_size FROM host')->fetchColumn())->toBe(0);
});

test('filtered walks preserve matching indexes for every field source', function ($source, $expected) {
	$GLOBALS['xml'] = array('oid_index' => '.1.2.1', 'index_order' => 'ifName',
		'oid_index_parse' => 'OID/REGEXP:.*\.([0-9]+)$', 'value_index_parse' => 'VALUE/REGEXP:^([12])$',
		'fields' => array('ifName' => array('direction' => 'input', 'method' => 'walk', 'source' => $source, 'oid' => '.1.2.2')));
	$GLOBALS['benchmark_walks'] = array(
		'.1.2.1' => array('.1.2.1.1' => '1', '.1.2.1.2' => '2', '.1.2.1.3' => '3'),
		'.1.2.2' => array('.1.2.2.0' => 'noise', '.1.2.2.1' => 'port1', '.1.2.2.2' => 'port2', '.1.2.2.3' => 'noise'),
	);
	expect(query_snmp_host(1, 1))->toBeTrue()
		->and(sql('SELECT snmp_index FROM host_snmp_cache ORDER BY snmp_index')->fetchAll(\PDO::FETCH_COLUMN))->toBe(array('1', '2'))
		->and(sql('SELECT field_value FROM host_snmp_cache ORDER BY snmp_index')->fetchAll(\PDO::FETCH_COLUMN))->toBe($expected);
})->with(array(
	'value' => array('value', array('port1', 'port2')),
	'oid' => array('OID/REGEXP:.*\.([0-9]+)$', array('1', '2')),
	'hex' => array('OID2HEX/REGEXP:.*\.([0-9]+)$', array('01', '02')),
	'oid value' => array('OIDVALUE/REGEXP:.*\.([0-9]+)$:$1', array('1', '2')),
));

test('a failed reindex command remains queued and is acknowledged after recovery', function () {
	sql("INSERT INTO poller_command (poller_id,time,action,command) VALUES (1,NOW(),1,'1:1')");
	$complete = $GLOBALS['script'];
	$GLOBALS['script']['index'] = array();
	worker_fixture();
	expect((int) sql('SELECT COUNT(*) FROM poller_command')->fetchColumn())->toBe(1);
	$GLOBALS['script'] = $complete;
	worker_fixture();
	expect((int) sql('SELECT COUNT(*) FROM poller_command')->fetchColumn())->toBe(0);
});

test('a worker deadline leaves unprocessed requests in the queue', function () {
	sql("INSERT INTO poller_command (poller_id,time,action,command) VALUES (1,NOW(),1,'1:1'),(1,NOW(),1,'1:2')");
	$GLOBALS['clock_samples'] = array(1000.0);
	worker_fixture(0);
	expect(sql('SELECT command FROM poller_command')->fetchAll(\PDO::FETCH_COLUMN))->toBe(array('1:2'));
});

test('acknowledgement preserves a request with a newer timestamp', function () {
	sql("INSERT INTO poller_command (poller_id,time,action,command) VALUES (1,NOW(),1,'1:1')");
	$GLOBALS['dq_automation_callback'] = function () {
		sql('UPDATE poller_command SET time=DATE_ADD(time, INTERVAL 1 SECOND), last_updated=DATE_ADD(last_updated, INTERVAL 1 SECOND)');
	};
	worker_fixture();
	expect((int) sql('SELECT COUNT(*) FROM poller_command')->fetchColumn())->toBe(1);
});

function refresh_process($mode) {
	$process = proc_open(array(PHP_BINARY, dirname(__DIR__) . '/fixtures/Core/DataQueryConcurrentRefresh.php', $mode),
		array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
	if (!is_resource($process)) throw new \RuntimeException('Could not start the concurrent refresh');
	stream_set_timeout($pipes[1], 10);
	return array($process, $pipes);
}

test('concurrent refreshes serialize and readers never see the mark phase', function () {
	$before = cache_snapshot();
	list($first, $first_pipes) = refresh_process('pause');
	$second = null;
	$released = false;
	try {
		expect((int) fgets($first_pipes[1]))->toBeGreaterThan(0);
		expect(trim(fgets($first_pipes[1])))->toBe('marked');
		expect(cache_snapshot())->toBe($before);
		list($second, $second_pipes) = refresh_process('run');
		$connection = (int) fgets($second_pipes[1]);
		$waiting = false;
		$deadline = microtime(true) + 2;
		do {
			$waiting = sql('SELECT trx_state FROM information_schema.innodb_trx WHERE trx_mysql_thread_id=?', array($connection))->fetchColumn() === 'LOCK WAIT';
			// MariaDB caches the InnoDB diagnostic snapshot between close reads.
			if (!$waiting) usleep(200000);
		} while (!$waiting && microtime(true) < $deadline);
		if (!$waiting) {
			stream_set_blocking($second_pipes[2], false);
			throw new \RuntimeException(json_encode(array('connection' => $connection, 'status' => proc_get_status($second), 'stderr' => stream_get_contents($second_pipes[2]), 'transactions' => sql('SELECT trx_mysql_thread_id,trx_state FROM information_schema.innodb_trx')->fetchAll())));
		}
		expect($waiting)->toBeTrue()->and(cache_snapshot())->toBe($before);
		fwrite($first_pipes[0], "continue\n");
		$released = true;
		expect(json_decode(fgets($first_pipes[1]), true))->toBe(array('result' => true, 'transaction' => false));
		expect(json_decode(fgets($second_pipes[1]), true))->toBe(array('result' => true, 'transaction' => false));
		expect((int) sql('SELECT COUNT(*) FROM host_snmp_cache')->fetchColumn())->toBe(100)
			->and((int) sql("SELECT COUNT(*) FROM host_snmp_cache WHERE field_value LIKE 'second%' AND present=1")->fetchColumn())->toBe(100);
	} finally {
		if (!$released) fwrite($first_pipes[0], "continue\n");
		foreach ($first_pipes as $pipe) fclose($pipe);
		proc_close($first);
		if (is_resource($second)) {
			foreach ($second_pipes as $pipe) fclose($pipe);
			proc_close($second);
		}
	}
})->repeat(10);
