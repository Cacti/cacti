<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * The import results list each dependency by its friendly name, which
 * hash_to_friendly_name() reads from the table of the dependency's type.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/import.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->session = "$database_hostname:$database_port:$database_default";
	$this->prior   = $database_sessions[$this->session] ?? null;
	$this->codes   = [$GLOBALS['hash_type_codes'] ?? null, $GLOBALS['cacti_version_codes'] ?? null];

	$conn = new FakeMySQLPDO();
	$conn->exec('CREATE TABLE data_source_profiles (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec("INSERT INTO data_source_profiles VALUES (1, '" . str_repeat('e', 32) . "', 'Five Minute <Collection>')");

	$database_sessions[$this->session] = $conn;
	$GLOBALS['hash_type_codes']        = ['data_source_profile' => '18'];
	$GLOBALS['cacti_version_codes']    = [CACTI_VERSION => '0101'];
});

afterEach(function () {
	global $database_sessions;

	if ($this->prior === null) {
		unset($database_sessions[$this->session]);
	} else {
		$database_sessions[$this->session] = $this->prior;
	}

	[$GLOBALS['hash_type_codes'], $GLOBALS['cacti_version_codes']] = $this->codes;
});

test('a data source profile dependency is named from the profile table', function () {
	expect(hash_to_friendly_name('hash_180101' . str_repeat('e', 32), false))->toBe('Five Minute &lt;Collection&gt;');
});
