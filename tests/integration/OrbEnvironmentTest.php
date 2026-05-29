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

$orb_available = static function (): bool {
	// nosemgrep: php.lang.security.exec-use.exec-use - constant command, integration test
	return trim((string)shell_exec('command -v orb 2>/dev/null')) !== '';
};

$has_timeout = static function (): bool {
	// nosemgrep: php.lang.security.exec-use.exec-use - constant command, integration test
	return trim((string)shell_exec('command -v timeout 2>/dev/null')) !== '';
};

it('has all required PHP extensions in the Orb machine', function () use ($orb_available, $has_timeout) {
	if (!$orb_available()) {
		test()->markTestSkipped('orb CLI not available');
	}

	$required_exts = [
		'gd', 'gmp', 'intl', 'ldap', 'mbstring', 'mysqli',
		'pdo_mysql', 'snmp', 'pcntl', 'posix', 'sockets',
		'xml', 'dom', 'sqlite3', 'pdo_sqlite'
	];

	foreach ($required_exts as $ext) {
		// Extension names come from a fixed allowlist; no external input.
		$cmd = ($has_timeout() ? 'timeout 30 ' : '') . 'orb php -m | grep -i ^' . escapeshellarg($ext) . '$';
		// nosemgrep: php.lang.security.exec-use.exec-use - allowlisted ext names, integration test
		$output = shell_exec($cmd);
		expect(trim((string)$output))->toBeIgnoringCase($ext);
	}
});

it('can run a Cacti CLI command in the Orb machine', function () use ($orb_available, $has_timeout) {
	if (!$orb_available()) {
		test()->markTestSkipped('orb CLI not available');
	}

	$cmd = ($has_timeout() ? 'timeout 30 ' : '') . 'orb php cli/check_cli_version.sh';
	// nosemgrep: php.lang.security.exec-use.exec-use - constant command, integration test
	$output = shell_exec($cmd);
	// Just verify it doesn't crash and returns something sensible
	expect($output)->toContain('Cacti');
});
