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

$repoRoot = dirname(__DIR__, 3);

function _automation_reports_function_body(string $source, string $needle): string {
	$start = strpos($source, $needle);
	expect($start)->not->toBeFalse("Missing expected symbol: $needle");

	$end = strpos($source, "\nfunction ", $start + strlen($needle));

	return substr($source, $start, $end !== false ? $end - $start : strlen($source) - $start);
}

/**
 * Execute a self-contained PHP snippet in an isolated child process so the
 * target checkout's library files are exercised directly without function
 * redeclaration conflicts against any test bootstrap.
 */
function _automation_reports_run_isolated(string $phpBody): array {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);

	$proc = proc_open(array(PHP_BINARY), $descriptors, $pipes);
	expect($proc)->not->toBeFalse('Failed to spawn isolated PHP process');

	fwrite($pipes[0], "<?php\n" . $phpBody);
	fclose($pipes[0]);

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exit = proc_close($proc);

	expect($exit)->toBe(0, "Isolated PHP failed (exit $exit): $stderr $stdout");

	$decoded = json_decode($stdout, true);
	expect($decoded)->toBeArray("Expected JSON output, got: $stdout (stderr: $stderr)");

	return $decoded;
}

describe('SEC2-001: sensitive SNMP host columns blocked in automation', function () use ($repoRoot) {
	$apiSource = file_get_contents($repoRoot . '/lib/api_automation.php');

	it('blocks all eight sensitive SNMP columns in api_automation_column_exists(), get_query_fields(), and build_rule_item_filter()', function () use ($repoRoot) {
		$apiPath = var_export($repoRoot . '/lib/api_automation.php', true);

		$result = _automation_reports_run_isolated(<<<PHP
			define('AUTOMATION_OPER_NULL', 0);
			define('AUTOMATION_OPER_RIGHT_BRACKET', 4);
			define('AUTOMATION_OP_BEGINS_WITH', 1);
			define('AUTOMATION_OP_LT', 10);
			define('AUTOMATION_OP_GE', 14);
			define('POLLER_VERBOSITY_HIGH', 4);
			\$automation_oper = array(0 => '');
			\$automation_op_array = array(
				'op'     => array(1 => 'LIKE'),
				'binary' => array(1 => true),
				'pre'    => array(1 => ''),
				'post'   => array(1 => '%'),
			);
			function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
			function cacti_log(\$m, \$o = false, \$e = '', \$l = 0) {}
			function db_column_exists(\$table, \$col) { return \$table === 'host'; }
			function db_fetch_assoc(\$sql) {
				return array(
					array('Field' => 'id', 'Type' => 'int'),
					array('Field' => 'hostname', 'Type' => 'varchar(100)'),
					array('Field' => 'description', 'Type' => 'varchar(150)'),
					array('Field' => 'snmp_community', 'Type' => 'varchar(100)'),
					array('Field' => 'snmp_username', 'Type' => 'varchar(50)'),
					array('Field' => 'snmp_password', 'Type' => 'varchar(50)'),
					array('Field' => 'snmp_auth_protocol', 'Type' => 'char(6)'),
					array('Field' => 'snmp_priv_passphrase', 'Type' => 'varchar(200)'),
					array('Field' => 'snmp_priv_protocol', 'Type' => 'char(6)'),
					array('Field' => 'snmp_context', 'Type' => 'varchar(64)'),
					array('Field' => 'snmp_engine_id', 'Type' => 'varchar(64)'),
				);
			}
			function array_rekey(\$arr, \$k, \$v) {
				\$out = array();
				foreach (\$arr as \$row) { \$out[\$row[\$k]] = \$row[\$v]; }
				return \$out;
			}
			function sanitize_sql_column(\$c) { return preg_replace('/[^a-zA-Z0-9_.]/', '', (string) \$c); }
			function db_qstr(\$s) { return "'" . addslashes((string) \$s) . "'"; }
			require_once {$apiPath};

			\$tables = array('host', 'host_template');
			\$sensitive = api_automation_sensitive_host_columns();
			\$blockedAll = true;
			foreach (\$sensitive as \$col) {
				if (api_automation_column_exists(\$col, \$tables)
					|| api_automation_column_exists('h.' . \$col, \$tables)
					|| api_automation_column_exists('h.' . strtoupper(\$col), \$tables)) {
					\$blockedAll = false;
				}
			}

			\$validAllowed  = api_automation_column_exists('h.description', \$tables);
			\$wildcardDeny  = api_automation_column_exists('%', \$tables);
			\$sqliDeny      = api_automation_column_exists('h.hostname; DROP TABLE host', \$tables);
			\$queryFields   = array_keys(get_query_fields('host', array('id')));
			\$filterSecret  = build_rule_item_filter(array(array(
				'operation' => AUTOMATION_OPER_NULL,
				'field'     => 'h.snmp_password',
				'operator'  => AUTOMATION_OP_BEGINS_WITH,
				'pattern'   => 'secret',
			)));
			\$filterSafe    = build_rule_item_filter(array(array(
				'operation' => AUTOMATION_OPER_NULL,
				'field'     => 'h.hostname',
				'operator'  => AUTOMATION_OP_BEGINS_WITH,
				'pattern'   => 'core',
			)));

			echo json_encode(array(
				'sensitive_count' => count(\$sensitive),
				'blocked_all'     => \$blockedAll,
				'valid_allowed'   => \$validAllowed,
				'wildcard_deny'   => \$wildcardDeny,
				'sqli_deny'       => \$sqliDeny,
				'query_fields'    => \$queryFields,
				'filter_secret'   => trim(\$filterSecret),
				'filter_safe'     => trim(\$filterSafe),
			));
		PHP);

		expect($result['sensitive_count'])->toBe(8)
			->and($result['blocked_all'])->toBeTrue()
			->and($result['valid_allowed'])->toBeTrue()
			->and($result['wildcard_deny'])->toBeFalse()
			->and($result['sqli_deny'])->toBeFalse()
			->and($result['query_fields'])->toBe(array('h.hostname', 'h.description'))
			->and($result['filter_secret'])->toBe('(1 != 1)')
			->and($result['filter_safe'])->toContain('`h`.`hostname`');
	});

	it('validates column allowlist in create_all_header_nodes()', function () use ($apiSource) {
		$headerNodesBody = _automation_reports_function_body($apiSource, 'function create_all_header_nodes(');
		expect($headerNodesBody)->toContain('api_automation_column_exists(');
	});
});

describe('ALGO2-03: DNS PTR packet parsing and timeout handling', function () use ($repoRoot) {
	$apiSource = file_get_contents($repoRoot . '/lib/api_automation.php');

	it('parses uncompressed and compressed DNS names and stops on cyclic or truncated packets', function () use ($repoRoot) {
		$apiPath = var_export($repoRoot . '/lib/api_automation.php', true);

		$result = _automation_reports_run_isolated(<<<PHP
			require_once {$apiPath};

			echo json_encode(array(
				'plain'      => automation_parse_dns_name("\\x03www\\x07example\\x03com\\x00", 0),
				'compressed' => automation_parse_dns_name("\\x07example\\x03com\\x00\\x04host\\xC0\\x00", 13),
				'self_loop'  => automation_parse_dns_name("\\xC0\\x00", 0),
				'two_cycle'  => automation_parse_dns_name("\\xC0\\x02\\xC0\\x00", 0),
				'truncated'  => automation_parse_dns_name("\\x0Ashort", 0),
				'short_ptr'  => automation_parse_dns_name("\\xC0", 0),
				'bad_flags'  => automation_parse_dns_name("\\x40bad", 0),
				'empty_name' => automation_parse_dns_name("\\x00", 0),
			));
		PHP);

		expect($result['plain'])->toBe('WWW.EXAMPLE.COM')
			->and($result['compressed'])->toBe('HOST.EXAMPLE.COM')
			->and($result['self_loop'])->toBeFalse()
			->and($result['two_cycle'])->toBeFalse()
			->and($result['truncated'])->toBeFalse()
			->and($result['short_ptr'])->toBeFalse()
			->and($result['bad_flags'])->toBeFalse()
			->and($result['empty_name'])->toBeFalse();
	});

	it('checks !empty($info[\'timed_out\']) and uses unsigned unpack(\'C\') in automation_get_dns_from_ip()', function () use ($apiSource) {
		$dnsBody = _automation_reports_function_body($apiSource, 'function automation_get_dns_from_ip(');
		expect($dnsBody)->toContain("!empty(\$info['timed_out'])")
			->and($dnsBody)->not->toContain("isset(\$info['timed_out'])")
			->and($dnsBody)->toContain('automation_parse_dns_name(');

		$parseBody = _automation_reports_function_body($apiSource, 'function automation_parse_dns_name(');
		expect($parseBody)->toContain("unpack('C'")
			->and($parseBody)->not->toContain("unpack('c'");
	});
});

describe('ALGO2-16, ALGO2-17, ALGO2-18, ALGO2-34, ALGO2-35: lib/api_automation.php fixes', function () use ($repoRoot) {
	$apiSource = file_get_contents($repoRoot . '/lib/api_automation.php');

	it('handles recur_every <= 0, invalid day_of_week, and stale weekly schedules in api_automation_is_time_to_start()', function () use ($repoRoot) {
		$apiPath = var_export($repoRoot . '/lib/api_automation.php', true);

		$result = _automation_reports_run_isolated(<<<PHP
			\$GLOBALS['mock_net'] = array();
			\$GLOBALS['updates']  = array();
			function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
			function db_fetch_row_prepared(\$sql, \$params) { return \$GLOBALS['mock_net']; }
			function db_execute_prepared(\$sql, \$params) { \$GLOBALS['updates'][] = \$params; }
			require_once {$apiPath};

			\$now = time();
			\$pastDaily = date('Y-m-d H:i:s', \$now - 86400 * 10);
			\$GLOBALS['mock_net'] = array(
				'sched_type'  => '2',
				'recur_every' => 0,
				'start_at'    => \$pastDaily,
				'next_start'  => \$pastDaily,
			);
			\$dailyZeroRecur = api_automation_is_time_to_start(1);
			\$dailyNext      = strtotime(\$GLOBALS['updates'][0][0]);

			\$GLOBALS['updates']  = array();
			\$GLOBALS['mock_net'] = array(
				'sched_type'  => '3',
				'recur_every' => 0,
				'start_at'    => \$pastDaily,
				'next_start'  => \$pastDaily,
				'day_of_week' => '0,8,invalid',
			);
			\$weeklyInvalidDays = api_automation_is_time_to_start(2);

			\$pastWeekly = date('Y-m-d H:i:s', \$now - 86400 * 45);
			\$GLOBALS['updates']  = array();
			\$GLOBALS['mock_net'] = array(
				'sched_type'  => '3',
				'recur_every' => 2,
				'start_at'    => \$pastWeekly,
				'next_start'  => \$pastWeekly,
				'day_of_week' => '2,4',
			);
			\$weeklyStale = api_automation_is_time_to_start(3);
			\$weeklyNext  = strtotime(\$GLOBALS['updates'][0][0]);

			echo json_encode(array(
				'daily_zero_recur'       => \$dailyZeroRecur,
				'daily_next_in_future'   => \$dailyNext > \$now,
				'weekly_invalid_days'    => \$weeklyInvalidDays,
				'weekly_stale'           => \$weeklyStale,
				'weekly_next_in_future'  => \$weeklyNext > \$now,
			));
		PHP);

		expect($result['daily_zero_recur'])->toBeTrue()
			->and($result['daily_next_in_future'])->toBeTrue()
			->and($result['weekly_invalid_days'])->toBeFalse()
			->and($result['weekly_stale'])->toBeTrue()
			->and($result['weekly_next_in_future'])->toBeTrue();
	});

	it('passes populated $suggested_values in automation_execute_graph_template()', function () use ($apiSource) {
		$body = _automation_reports_function_body($apiSource, 'function automation_execute_graph_template(');

		expect($body)->not->toContain('$suggested_vals')
			->and($body)->toContain("\$suggested_values[\$graph_template_id]['custom_data']")
			->and($body)->toContain('create_complete_graph_from_template($graph_template_id, $host_id, array(), $suggested_values)');
	});

	it('scopes automation_match_rule_items cleanup by rule_type in automation_change_tree_rule_leaf_type()', function () use ($apiSource) {
		$body = _automation_reports_function_body($apiSource, 'function automation_change_tree_rule_leaf_type(');

		expect($body)->toContain('AND rule_type = ?')
			->and($body)->toContain('AUTOMATION_RULE_TYPE_TREE_MATCH');
	});

	it('does not wrap MySQL REGEXP patterns in literal slashes in automation_find_os()', function () use ($apiSource) {
		$body = _automation_reports_function_body($apiSource, 'function automation_find_os(');

		expect($body)->not->toContain("CONCAT('/', sysDescr, '/')")
			->and($body)->not->toContain("CONCAT('/', sysOid, '/')")
			->and($body)->not->toContain("CONCAT('/', sysName, '/')")
			->and($body)->toContain("sysDescr != '' AND ? REGEXP sysDescr")
			->and($body)->toContain("sysOid != '' AND ? REGEXP sysOid")
			->and($body)->toContain("sysName != '' AND ? REGEXP sysName");
	});

	it('uses SQL COUNT(*) instead of full-table PHP fetches and removes the dead host fetch', function () use ($apiSource) {
		$hostsBody = _automation_reports_function_body($apiSource, 'function display_matching_hosts(');
		expect($hostsBody)->not->toContain('cacti_sizeof(db_fetch_assoc($rows_query')
			->and($hostsBody)->not->toContain("db_fetch_assoc(\$sql_query . 'WHERE h.deleted = \"\"')")
			->and($hostsBody)->toContain('SELECT COUNT(*)');

		$objectsBody = _automation_reports_function_body($apiSource, 'function display_new_graphs(');
		expect($objectsBody)->not->toContain('cacti_sizeof(db_fetch_assoc($rows_query')
			->and($objectsBody)->not->toContain("get_request_var('rows')*(get_request_var('page')-1)")
			->and($objectsBody)->toContain('SELECT COUNT(*) FROM ($rows_query) AS b');

		$treesBody = _automation_reports_function_body($apiSource, 'function display_matching_trees (');
		expect($treesBody)->not->toContain('cacti_sizeof(db_fetch_assoc($rows_query')
			->and($treesBody)->toContain('SELECT COUNT(*)');
	});
});

describe('ALGO1-18, ALGO1-19, ALGO1-28: poller_automation.php fixes', function () use ($repoRoot) {
	$pollerSource = file_get_contents($repoRoot . '/poller_automation.php');

	it('uses db_fetch_row_prepared() when loading primary admin details for notifications', function () use ($pollerSource) {
		$body = _automation_reports_function_body($pollerSource, 'function reportNetworkStatus(');

		expect($body)->toContain("db_fetch_row_prepared('SELECT email_address AS notification_email, full_name")
			->and($body)->not->toContain("db_fetch_cell_prepared('SELECT email_address AS notification_email, full_name");
	});

	it('scopes clearTask() automation_ips deletion by both pid and network_id', function () use ($pollerSource) {
		$body = _automation_reports_function_body($pollerSource, 'function clearTask(');

		expect($body)->toContain('FROM automation_ips')
			->and($body)->toContain('WHERE pid = ?')
			->and($body)->toContain('AND network_id = ?')
			->and($body)->toContain('array($pid, $network_id)');
	});

	it('removes off-by-one dot truncation and preserves NetBIOS hostname in discoverDevices()', function () use ($pollerSource) {
		$body = _automation_reports_function_body($pollerSource, 'function discoverDevices(');

		expect($body)->not->toContain("strpos(\$hostname, '.') - 1")
			->and($body)->toContain('substr($hostname, 0, $dot_pos)')
			->and($body)->toContain('AND (hostname = ? OR hostname = ? OR hostname LIKE ?)')
			->and($body)->toContain("array(\$netbios, \$device['ip_address'])")
			->and($body)->toContain("\$device['hostname']      = \$netbios;");
	});
});

describe('SEC1-05, ALGO2-19, ALGO2-20, ALGO2-21, ALGO2-36: lib/reports.php fixes', function () use ($repoRoot) {
	$reportsSource = file_get_contents($repoRoot . '/lib/reports.php');

	it('blocks unauthorized graphs in reports_add_graphs() before querying graph_local or inserting', function () use ($repoRoot) {
		$reportsPath = var_export($repoRoot . '/lib/reports.php', true);

		$result = _automation_reports_run_isolated(<<<PHP
			\$_SESSION['sess_user_id'] = 7;
			\$GLOBALS['messages'] = array();
			\$GLOBALS['queries']  = array();
			\$GLOBALS['allowed']  = array(101 => true, 202 => false);

			function cacti_sizeof(\$v) { return is_array(\$v) ? count(\$v) : 0; }
			function cacti_authorize_resource(\$u, \$id, \$type) { return true; }
			function is_graph_allowed(\$gid, \$uid = 0) { return !empty(\$GLOBALS['allowed'][\$gid]); }
			function raise_message(\$m) { \$GLOBALS['messages'][] = \$m; }
			function db_fetch_cell_prepared(\$sql, \$params) {
				\$GLOBALS['queries'][] = \$sql;
				if (strpos(\$sql, 'MAX(sequence)') !== false) { return 3; }
				if (strpos(\$sql, 'host_template_id') !== false) { return 5; }
				return 0;
			}
			function db_fetch_row_prepared(\$sql, \$params) {
				\$GLOBALS['queries'][] = \$sql;
				return array('id' => \$params[0], 'host_id' => 11, 'graph_template_id' => 9);
			}
			function db_execute_prepared(\$sql, \$params) {
				\$GLOBALS['queries'][] = \$sql;
			}
			require_once {$reportsPath};

			\$denied = reports_add_graphs(1, 202, 7, 1);
			\$queriesAfterDenied = count(\$GLOBALS['queries']);
			\$allowed = reports_add_graphs(1, 101, 7, 1);
			\$queriesAfterAllowed = count(\$GLOBALS['queries']);

			echo json_encode(array(
				'denied'                => \$denied,
				'queries_after_denied'  => \$queriesAfterDenied,
				'allowed'               => \$allowed,
				'queries_after_allowed' => \$queriesAfterAllowed,
				'messages'              => \$GLOBALS['messages'],
			));
		PHP);

		expect($result['denied'])->toBeFalse()
			->and($result['queries_after_denied'])->toBe(0)
			->and($result['allowed'])->toBeTrue()
			->and($result['queries_after_allowed'])->toBeGreaterThan(0)
			->and($result['messages'])->toContain('reports_graph_not_found');
	});

	it('checks is_device_allowed() instead of is_tree_allowed() for REPORTS_ITEM_HOST', function () use ($reportsSource) {
		$body = _automation_reports_function_body($reportsSource, 'function reports_generate_html(');

		expect($body)->toContain("is_device_allowed(\$item['host_id'], \$report['user_id'])")
			->and($body)->not->toContain("is_tree_allowed(\$item['host_id']");
	});

	it('parses arbitrary-length <GRAPH:id:timespan> tags and computes monthly-weekday and stale intervals accurately', function () use ($repoRoot) {
		$reportsPath = var_export($repoRoot . '/lib/reports.php', true);

		$result = _automation_reports_run_isolated(<<<PHP
			define('POLLER_VERBOSITY_NONE', 1);
			define('POLLER_VERBOSITY_MEDIUM', 3);
			define('REPORTS_DEBUG', 0);
			define('REPORTS_SCHED_INTVL_MINUTE', 10);
			define('REPORTS_SCHED_INTVL_HOUR', 11);
			define('REPORTS_SCHED_INTVL_DAY', 1);
			define('REPORTS_SCHED_INTVL_WEEK', 2);
			define('REPORTS_SCHED_INTVL_MONTH_DAY', 3);
			define('REPORTS_SCHED_INTVL_MONTH_WEEKDAY', 4);
			define('REPORTS_SCHED_INTVL_YEAR', 5);
			date_default_timezone_set('UTC');
			require_once {$reportsPath};

			\$parsed = reports_parse_graph_tags('<GRAPH:1234567890:15> text <GRAPH:7:1> <GRAPH:9999999:24> <GRAPH:broken');

			\$jan31  = mktime(9, 0, 0, 1, 31, 2030);
			\$nowJan = mktime(8, 0, 0, 1, 1, 2030);
			\$febNext = reports_interval_start(REPORTS_SCHED_INTVL_MONTH_WEEKDAY, 1, 120, \$jan31, \$nowJan);

			\$stale   = mktime(8, 30, 0, 1, 1, 2030);
			\$nowStale = mktime(14, 15, 0, 1, 5, 2030);
			\$staleNext = reports_interval_start(REPORTS_SCHED_INTVL_DAY, 1, 0, \$stale, \$nowStale);

			echo json_encode(array(
				'parsed'     => \$parsed,
				'feb_next'   => date('Y-m-d H:i:s', \$febNext),
				'stale_next' => date('Y-m-d H:i:s', \$staleNext),
				'stale_gt'   => \$staleNext > \$nowStale,
			));
		PHP);

		expect($result['parsed'])->toBe(array(
			'1234567890:15' => '1234567890',
			'7:1'           => '7',
			'9999999:24'    => '9999999',
		))
			->and($result['feb_next'])->toBe('2030-02-28 09:02:00')
			->and($result['stale_next'])->toBe('2030-01-06 08:30:00')
			->and($result['stale_gt'])->toBeTrue();
	});

	it('fixes $graph_templates[0], hoisted $sql_where, $snmp_index_to_graph reset, and double-rendering', function () use ($reportsSource) {
		$deviceBody = _automation_reports_function_body($reportsSource, 'function reports_expand_device(');
		expect($deviceBody)->toContain("\$graph_templates[0] = __('(No Graph Template)')")
			->and($deviceBody)->not->toContain('array_push($graph_templates')
			->and($deviceBody)->not->toContain("\$sql_where .= ' AND title_cache '");

		$treeBody = _automation_reports_function_body($reportsSource, 'function reports_expand_tree(');
		$key0Pos  = strpos($treeBody, "\$graph_templates[0] = __('(No Graph Template)')");
		$permPos  = strpos($treeBody, 'is_graph_template_allowed($id, $user)');

		expect($key0Pos)->not->toBeFalse()
			->and($permPos)->not->toBeFalse()
			->and($key0Pos < $permPos)->toBeTrue()
			->and($treeBody)->not->toContain('array_push($graph_templates')
			->and($treeBody)->toContain('$snmp_index_to_graph = array();')
			->and($treeBody)->toContain('$nested_graphs = array();')
			->and($treeBody)->toContain('reports_graph_area($nested_graphs,');
	});
});

describe('ALGO2-37: lib/html_reports.php fixes', function () use ($repoRoot) {
	$htmlReportsSource = file_get_contents($repoRoot . '/lib/html_reports.php');

	it('filters graph_local by host_id instead of id when validating graph_template_id', function () use ($htmlReportsSource) {
		expect($htmlReportsSource)->toContain("WHERE graph_template_id = ?\n\t\t\t\t\t\t\tAND host_id = ?");
	});

	it('includes ? query delimiter in html_nav_bar() pagination URL', function () use ($htmlReportsSource) {
		expect($htmlReportsSource)->toContain("html_nav_bar(get_reports_page() . '?filter='")
			->and($htmlReportsSource)->not->toContain("html_nav_bar(get_reports_page() . 'filter='");
	});

	it('allows realm 21 reports administrators in reports_send()', function () use ($htmlReportsSource) {
		$body = _automation_reports_function_body($htmlReportsSource, 'function reports_send(');

		expect($body)->toContain('is_realm_allowed(21)');
	});
});
