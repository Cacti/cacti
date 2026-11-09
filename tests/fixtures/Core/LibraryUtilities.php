<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryUtilityFixture;

/** Substitute server metadata, while retaining the production recommendation and rendering code. */
function get_mysql_info($poller): array {
	\library_expect($poller, POLLER_ID, 'Recommendations inspect the active collector');

	return $GLOBALS['library_utility_metadata'];
}
/** Return the configured system memory instead of reading the host. */
function utilities_get_system_memory(): array {
	return $GLOBALS['library_utility_memory'];
}
/** Return the configured CPU count after checking the discovery command. */
function system(string $command): string {
	\library_expect($command, 'nproc', 'CPU discovery uses the established command');

	return (string) $GLOBALS['library_utility_cpus'];
}
/** Scripted collector availability so the poller cache buffer can observe a mid-flush outage. */
function remote_poller_up(int $poller_id): bool {
	return array_shift($GLOBALS['library_utility_remote_up']);
}
/** Scripted collector connection; a non-false value is the connection used for the collector copy. */
function poller_push_to_remote_db_connect(int $poller_id, bool $connect = false): mixed {
	return array_shift($GLOBALS['library_utility_remote_connect']);
}

/** Run PHP source in a separate Cacti process and import its coverage; returns [exit, stdout, stderr]. */
function child(string $source, array $arguments = []): array {
	$directory = sys_get_temp_dir() . '/cacti-utility-child-' . bin2hex(random_bytes(8));
	\library_expect(mkdir($directory, 0700), true, 'Child process owns its coverage directory');

	try {
		// The image may prepend RequestCoverage.php; the source requires it once itself.
		$process = new \Symfony\Component\Process\Process(array_merge([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'display_errors=stderr', '-r', "require 'tests/Helpers/RequestCoverage.php';\nrequire 'include/global.php';\n" . $source], $arguments),
			CACTI_PATH_BASE, ['CACTI_PR_RAW_COVERAGE' => $directory, 'XDEBUG_MODE' => 'coverage'], null, 120);
		$process->run();
		$reports = glob($directory . '/*.json');
		\library_expect(count($reports), 1, 'Child process publishes its coverage: ' . $process->getErrorOutput());
		\library_import_coverage($reports);

		return [$process->getExitCode(), $process->getOutput(), $process->getErrorOutput()];
	} finally {
		foreach (glob($directory . '/*') as $path) {
			unlink($path);
		}
		rmdir($directory);
	}
}

/** Exercise whitelist auditing and session start failure in processes whose static state starts empty. */
function processes(): void {
	$whitelist = tempnam(sys_get_temp_dir(), 'cacti-whitelist-');
	$inputs    = \db_fetch_assoc('SELECT id, hash, input_string FROM data_input WHERE input_string != "" ORDER BY id LIMIT 2');
	\library_expect(count($inputs), 2, 'Installed schema provides command-based data input methods');

	try {
		$json = json_encode([$inputs[0]['hash'] => $inputs[0]['input_string'] . ' --tampered'], JSON_THROW_ON_ERROR);
		\library_expect(file_put_contents($whitelist, $json), strlen($json), 'Whitelist fixture is written completely');
		[$exit, $output] = child('require_once CACTI_PATH_LIBRARY . "/utility.php";
define("CACTI_WHITELIST", $argv[1]);
print json_encode([data_input_whitelist_check((int) $argv[2]), data_input_whitelist_check((int) $argv[3])]);', [$whitelist, $inputs[0]['id'], $inputs[1]['id']]);
		\library_expect($exit, 0, 'Whitelist audit completes');
		\library_expect(str_ends_with($output, '[false,true]'), true, 'A changed command is blocked while an unlisted command remains permitted: ' . $output);
	} finally {
		unlink($whitelist);
	}
	[$exit, $output, $error] = child('print "output before session\n";
$config[CACTI_SESSION_NAME] = "CactiCoverage";
$config[COOKIE_OPTIONS]     = [];
$log = cacti_log_file();
clearstatcache(true, $log);
$offset = filesize($log);
var_export(cacti_session_start());
clearstatcache(true, $log);
print "\n" . (str_contains((string) file_get_contents($log, false, null, $offset), "start failed!") ? "logged" : "silent");');
	\library_expect($exit, 0, 'Session start failure does not terminate the process');
	\library_expect(str_ends_with($output, "true\nlogged"), true, 'A session that cannot start after output is logged without failing the caller: ' . $output);
	\library_expect(str_contains($output . $error, 'Session cannot be started after headers have already been sent'), true, 'Session start failure is the PHP header restriction');
}

/** Exercise the poller cache buffer when a collector is lost between marking and pruning items. */
function poller_buffer(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/utility.php', 'poller_update_poller_cache_from_buffer', __NAMESPACE__);
	$poller     = 2147483000;
	$connection = $GLOBALS['database_sessions'][$GLOBALS['database_hostname'] . ':' . $GLOBALS['database_port'] . ':' . $GLOBALS['database_default']];

	try {
		foreach ([[[true, true], [$connection, false]], [[true, false], [$connection]]] as [$up, $connect]) {
			$GLOBALS['library_utility_remote_up']      = $up;
			$GLOBALS['library_utility_remote_connect'] = $connect;
			$_SESSION[SESS_MESSAGES]                   = [];
			$items                                     = [];
			poller_update_poller_cache_from_buffer([2147483000], $items, $poller);
			\library_expect($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]['level'], MESSAGE_LEVEL_WARN, 'A collector lost before pruning requires a full sync');
			\library_expect($GLOBALS['library_utility_remote_up'], [], 'Collector availability is checked before marking and before pruning');
			\library_expect($GLOBALS['library_utility_remote_connect'], [], 'Collector connection is attempted only while it is reported up');
		}
	} finally {
		unset($GLOBALS['library_utility_remote_up'], $GLOBALS['library_utility_remote_connect']);
	}
}

/** Build SNMP and script server data sources and observe the poller items they produce. */
function poller_cache(): void {
	$device    = \library_save_device(['description' => 'Poller cache fixture', 'snmp_version' => 2, 'snmp_community' => 'public']);
	$owned     = ['data_template' => [], 'data_template_data' => [], 'data_local' => [], 'data_template_rrd' => [], 'snmp_query' => [], 'snmp_query_graph' => []];
	$input     = static fn (string $hash): int => (int) \db_fetch_cell_prepared('SELECT id FROM data_input WHERE hash = ?', [$hash]);
	$snmpInput = $input('3eb92bb845b9660a7445cf9740726522');
	$ssInput   = $input('332111d8b54ac8ce939af87a7eac0c06');
	\library_expect($device > 0 && $snmpInput > 0 && $ssInput > 0, true, 'Installed schema provides the SNMP and script server inputs');
	// Disabled devices never reach the poller cache, so enable the fixture device after its save.
	\library_expect(\db_execute_prepared('UPDATE host SET disabled = ? WHERE id = ?', ['', $device]), true, 'Poller cache device is eligible for collection');
	$save = static function (array $row, string $table) use (&$owned): int {
		$id = (int) \sql_save(['id' => 0] + $row, $table);
		\library_expect($id > 0, true, 'Poller cache fixture owns its ' . $table . ' row');
		$owned[$table][] = $id;

		return $id;
	};
	$source = static function (int $inputId, string $name, int $query = 0, string $index = '') use ($save, $device): array {
		$template = $save(['hash' => bin2hex(random_bytes(16)), 'name' => $name], 'data_template');
		$data     = ['data_template_id' => $template, 'data_input_id' => $inputId, 'name' => $name, 'active' => 'on', 'rrd_step' => 300];
		$base     = $save($data + ['local_data_id' => 0], 'data_template_data');
		$baseRrd  = $save(['hash' => bin2hex(random_bytes(16)), 'data_template_id' => $template, 'data_source_name' => 'cpu'], 'data_template_rrd');
		$local    = $save(['data_template_id' => $template, 'host_id' => $device, 'snmp_query_id' => $query, 'snmp_index' => $index], 'data_local');
		$localDtd = $save($data + ['local_data_id' => $local, 'local_data_template_data_id' => $base], 'data_template_data');
		// An empty name makes Cacti derive the RRD item name from the data source name.
		$rrd = $save(['local_data_id' => $local, 'local_data_template_rrd_id' => $baseRrd, 'data_template_id' => $template, 'data_source_name' => ''], 'data_template_rrd');

		return ['template' => $template, 'base' => $base, 'base_rrd' => $baseRrd, 'local' => $local, 'dtd' => $localDtd, 'rrd' => $rrd];
	};

	try {
		$snmp  = $source($snmpInput, 'Coverage SNMP uptime');
		$field = (int) \db_fetch_cell_prepared('SELECT id FROM data_input_fields WHERE hash = ?', ['4276a5ec6e3fe33995129041b1909762']);
		\library_expect(\db_execute_prepared('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, value) VALUES (?, ?, ?)', [$field, $snmp['dtd'], '.1.3.6.1.2.1.1.3.0']), true, 'SNMP data source stores its OID');
		$items = \update_poller_cache($snmp['local']);
		$name  = substr(strtolower(\clean_up_name('Coverage SNMP uptime')), 0, 19 - strlen((string) $snmp['rrd'])) . $snmp['rrd'];
		\library_expect(count($items), 1, 'An SNMP data source produces one poller item');
		\library_expect(str_contains($items[0], "'.1.3.6.1.2.1.1.3.0'"), true, 'SNMP poller item carries the stored OID');
		\library_expect(str_contains($items[0], "'" . $name . "'"), true, 'SNMP poller item derives its RRD name from the data source name');
		\library_expect(strlen($name) <= 19, true, 'Derived RRD names fit RRDtool data source limits');

		// A device field the template leaves to the device replaces a stale per-source value.
		$hostField = (int) \db_fetch_cell_prepared('SELECT id FROM data_input_fields WHERE data_input_id = ? AND type_code = ? AND input_output = ?', [$snmpInput, 'hostname', 'in']);
		\library_expect($hostField > 0, true, 'The SNMP input method takes the device hostname');
		\library_expect(\db_execute_prepared('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, t_value, value) VALUES (?, ?, ?, ?)', [$hostField, $snmp['base'], '', '']), true, 'The data template leaves the hostname to the device');
		\library_expect(\db_execute_prepared('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, value) VALUES (?, ?, ?)', [$hostField, $snmp['dtd'], 'stale.example']), true, 'The data source holds a stale hostname');
		$verbosity = \read_config_option('log_verbosity');
		\set_config_option('log_verbosity', POLLER_VERBOSITY_MEDIUM);

		try {
			$log = \library_log_written_by(static fn () => \push_out_host($device, $snmp['local']));
		} finally {
			\set_config_option('log_verbosity', $verbosity);
		}
		$hostname = \db_fetch_cell_prepared('SELECT hostname FROM host WHERE id = ?', [$device]);
		\library_expect(\db_fetch_cell_prepared('SELECT value FROM data_input_data WHERE data_input_field_id = ? AND data_template_data_id = ?', [$hostField, $snmp['dtd']]), $hostname, 'Pushing out the device replaces the stale hostname');
		\library_expect(str_contains($log, 'Old[stale.example], New[' . $hostname . ']'), true, 'Replacing a stored device value is logged with both values');

		$missing = (int) \db_fetch_cell('SELECT MAX(id) + 1000 FROM data_input');
		\library_expect(\db_execute_prepared('UPDATE data_template_data SET data_input_id = ? WHERE id = ?', [$missing, $snmp['dtd']]), true, 'Data source references a removed input method');
		$log = \library_log_written_by(static function () use ($snmp, &$items): void { $items = \update_poller_cache($snmp['local']); });
		\library_expect($items, [], 'A data source without its input method produces no poller items');
		\library_expect(str_contains($log, 'Data Input Missing for Data Source ' . $snmp['local']), true, 'A missing input method is reported as corruption');

		$query  = $save(['hash' => bin2hex(random_bytes(16)), 'name' => 'Coverage script server', 'xml_path' => '<path_cacti>/resource/script_server/host_cpu.xml', 'data_input_id' => $ssInput], 'snmp_query');
		$script = $source($ssInput, 'Coverage script server', $query, '3');
		$graph  = $save(['hash' => bin2hex(random_bytes(16)), 'snmp_query_id' => $query, 'name' => 'Coverage CPU'], 'snmp_query_graph');
		\library_expect(\db_execute_prepared('INSERT INTO snmp_query_graph_rrd (snmp_query_graph_id, data_template_id, data_template_rrd_id, snmp_field_name) VALUES (?, ?, ?, ?)', [$graph, $script['template'], $script['base_rrd'], 'hrProcessorLoad']), true, 'Script server output maps to its RRD');
		unset($GLOBALS['data_query_xml_arrays'][$query]);
		$items = \update_poller_cache($script['local']);
		\library_expect(count($items), 1, 'A script server output produces one poller item');
		\library_expect(preg_match('/ss_host_cpu\\.php ss_host_cpu .*get.*usage.*3/', $items[0]), 1, 'Script server item requests the mapped field for its index: ' . $items[0]);
	} finally {
		\db_execute_prepared('DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id IN (' . implode(',', array_merge([0], $owned['snmp_query_graph'])) . ')');
		\db_execute_prepared('DELETE FROM data_input_data WHERE data_template_data_id IN (' . implode(',', array_merge([0], $owned['data_template_data'])) . ')');
		\db_execute_prepared('DELETE FROM poller_item WHERE host_id = ?', [$device]);

		foreach ($owned as $table => $ids) {
			if ($ids) {
				\db_execute('DELETE FROM ' . $table . ' WHERE id IN (' . implode(',', $ids) . ')');
			}
		}

		foreach ($owned['snmp_query'] as $id) {
			unset($GLOBALS['data_query_xml_arrays'][$id]);
		}
		\api_device_remove($device);
	}
}

/** Exercise utilities_get_mysql_recommendations() from source with substituted host metadata. */
function run(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/utility.php', 'utilities_get_mysql_recommendations', __NAMESPACE__);
	$GLOBALS['library_utility_cpus']   = 8;
	$GLOBALS['library_utility_memory'] = ['MemTotal' => 1024 * 1024 * 1024];

	try {
		foreach ([
			['MySQL', '8.4', ['innodb_file_per_table' => 'ON'], DB_STATUS_SUCCESS, 'innodb_file_per_table', ''],
			['MySQL', '8.4', ['innodb_file_per_table' => 'OFF'], DB_STATUS_ERROR, 'innodb_file_per_table', 'textError'],
			['MySQL', '8.4', ['max_connections' => '99'], DB_STATUS_ERROR, 'max_connections', 'textError'],
			['MySQL', '8.4', ['max_connections' => '100'], DB_STATUS_SUCCESS, 'max_connections', ''],
			['MySQL', '8.4', ['join_buffer_size' => '262143'], DB_STATUS_WARNING, 'join_buffer_size', 'textWarning'],
			['MySQL', '8.4', ['join_buffer_size' => '262144'], DB_STATUS_SUCCESS, 'join_buffer_size', ''],
			['MySQL', '8.4', ['collation_server' => 'latin1_swedish_ci'], DB_STATUS_ERROR, 'collation_server', 'textError'],
			['MySQL', '5.5', ['collation_server' => 'latin1_swedish_ci'], DB_STATUS_WARNING, 'collation_server', 'textWarning'],
			['MySQL', '5.5', ['innodb_flush_log_at_trx_commit' => '1'], DB_STATUS_ERROR, 'innodb_flush_log_at_trx_commit', 'textError'],
			['MySQL', '5.5', ['innodb_flush_log_at_trx_commit' => '2'], DB_STATUS_SUCCESS, 'innodb_flush_log_at_trx_commit', ''],
			['MySQL', '5.5', ['innodb_additional_mem_pool_size' => '1048576'], DB_STATUS_ERROR, 'innodb_additional_mem_pool_size', 'textError'],
			['MariaDB', '11.8', ['innodb_snapshot_isolation' => 'ON'], DB_STATUS_ERROR, 'innodb_snapshot_isolation', 'textError'],
			['MariaDB', '11.8', ['innodb_snapshot_isolation' => 'OFF'], DB_STATUS_SUCCESS, 'innodb_snapshot_isolation', ''],
			['MariaDB', '10.1', ['innodb_doublewrite' => 'OFF'], DB_STATUS_ERROR, 'innodb_doublewrite', 'textError'],
			['MariaDB', '10.1', ['innodb_doublewrite' => 'ON'], DB_STATUS_SUCCESS, 'innodb_doublewrite', ''],
		] as [$engine, $version, $variables, $expected, $field, $class]) {
			$GLOBALS['library_utility_metadata'] = ['database' => $engine, 'version' => $version, 'link_ver' => $version,
				'variables'                                       => $variables + ['innodb_version' => $version]];
			$result = null;
			$dom    = \library_form_dom(static function () use (&$result): void { $result = utilities_get_mysql_recommendations(); });
			\library_expect($result, $expected, 'Database recommendation status for ' . $engine . ' ' . $version . ' ' . $field);
			$row = $dom->query('//table[@id="mysql"]//tr[td[1]="' . $field . '"]');
			\library_expect($row->length, 1, 'Recommendation renders the requested supported variable');
			\library_expect(trim(str_replace('right', '', $dom->evaluate('string(td[2]/@class)', $row[0]))), $class, 'Recommendation severity reaches the displayed value');
		}

		foreach ([['MemTotal' => 1073741824], ['TotalVisibleMemorySize' => 1073741824], []] as $memory) {
			$GLOBALS['library_utility_memory'] = $memory;

			foreach (['268435455' => DB_STATUS_WARNING, '268435456' => DB_STATUS_SUCCESS] as $bytes => $expected) {
				$GLOBALS['library_utility_metadata'] = ['database' => 'MySQL', 'version' => '8.4', 'link_ver' => '8.4',
					'variables'                                       => ['innodb_version' => '8.4', 'innodb_buffer_pool_size' => (string) $bytes]];
				$result = null;
				$dom    = \library_form_dom(static function () use (&$result): void { $result = utilities_get_mysql_recommendations(); });
				\library_expect($result, $memory ? $expected : DB_STATUS_SUCCESS, 'Memory recommendation uses available platform metadata');
				\library_expect($dom->query('//tr[td[1]="innodb_buffer_pool_size"]')->length, $memory ? 1 : 0, 'Unknown memory does not fabricate a recommendation');
			}
		}
		$GLOBALS['library_utility_memory'] = ['MemTotal' => 1073741824];

		foreach ([1, 8, 128] as $cpus) {
			$GLOBALS['library_utility_cpus']     = $cpus;
			$GLOBALS['library_utility_metadata'] = ['database' => 'MySQL', 'version' => '5.7', 'link_ver' => '5.7',
				'variables'                                       => ['innodb_version' => '5.7', 'innodb_buffer_pool_size' => '17179869184', 'innodb_buffer_pool_instances' => '64']];
			$result = null;
			$dom    = \library_form_dom(static function () use (&$result): void { $result = utilities_get_mysql_recommendations(); });
			\library_expect($result, DB_STATUS_SUCCESS, 'Sufficient buffer pool instances pass');
			\library_expect($dom->evaluate('string(//tr[td[1]="innodb_buffer_pool_instances"]/td[4])'), (string) min($cpus, 64), 'Pool recommendation respects CPU and server limits');
		}

		foreach (['MySQL' => '8.4', 'MariaDB' => '10.9'] as $engine => $version) {
			$GLOBALS['library_utility_metadata'] = ['database' => $engine, 'version' => $version, 'link_ver' => $version, 'variables' => []];
			$result                              = null;
			$dom                                 = \library_form_dom(static function () use (&$result): void { $result = utilities_get_mysql_recommendations(); });
			\library_expect($result, DB_STATUS_WARNING, 'Missing InnoDB metadata warns for engines that expose it');
			\library_expect($dom->evaluate('string(//tr[td[1]="innodb"]/td[2])'), 'UNSET', 'Missing InnoDB metadata is distinguished from OFF');
		}
	} finally {
		unset($GLOBALS['library_utility_metadata'], $GLOBALS['library_utility_memory'], $GLOBALS['library_utility_cpus']);
	}
	processes();
	poller_buffer();
	poller_cache();
}
