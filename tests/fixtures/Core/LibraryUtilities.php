<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryUtilityFixture;

/** Substitute server metadata, while retaining the production recommendation and rendering code. */
function get_mysql_info($poller): array {
	\library_expect($poller, POLLER_ID, 'Recommendations inspect the active collector');

	return $GLOBALS['library_utility_metadata'];
}
function utilities_get_system_memory(): array {
	return $GLOBALS['library_utility_memory'];
}
function system(string $command): string {
	\library_expect($command, 'nproc', 'CPU discovery uses the established command');

	return (string) $GLOBALS['library_utility_cpus'];
}
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
}
