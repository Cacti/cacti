<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiBoostMariaDbFixture;

require_once __DIR__ . '/SourceSlice.php';

function boost_get_arch_table_names() {
	return \boostMariaDbArchiveTableNames();
}

function boost_is_valid_archive_table($table) {
	return \boostMariaDbValidArchiveTable($table);
}

function db_execute_prepared($sql, $params = array(), $log = true) {
	return \boostMariaDbExecutePrepared($sql, $params, $log);
}

function cacti_log($message, $output = false, $facility = '') {
	\boostMariaDbLog($message, $output, $facility);
}

\cacti_fixture_source_function(
	dirname(__DIR__, 2) . '/poller_boost.php',
	'boost_requeue_unprocessed_rows',
	__NAMESPACE__
);
