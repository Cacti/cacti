<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once __DIR__ . '/IsolatedProbe.php';

if (!function_exists('cacti_import_export_probe')) {
	/**
	 * Run a scenario in tests/fixtures/Core/ImportExportProbe.php and return its verdict.
	 *
	 * @param array $scenario The functions to load, the call and the data it runs against.
	 *
	 * @return array What the code returned, called, wrote and printed.
	 */
	function cacti_import_export_probe(array $scenario): array {
		return cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/Core/ImportExportProbe.php', array(json_encode($scenario, JSON_THROW_ON_ERROR)));
	}

	/**
	 * The names of the fake functions the probe called, in order.
	 */
	function cacti_import_export_called(array $verdict): array {
		return array_column($verdict['calls'], 0);
	}
}
