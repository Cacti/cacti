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

/**
 * Functional unit tests for the OID validation logic in remote_agent.php.
 *
 * The production function is extracted directly from remote_agent.php so
 * any change to the regexes is immediately exercised by these tests.
 */

$_src   = file_get_contents(__DIR__ . '/../../remote_agent.php');
$_start = strpos($_src, 'function remote_agent_validate_oid(');
$_end   = strpos($_src, "\n}\n", $_start) + 3;
eval(substr($_src, $_start, $_end - $_start));
unset($_src, $_start, $_end);

test('accepts dotted-numeric OID with leading dot', function () {
	expect(remote_agent_validate_oid('.1.3.6.1.2.1.1.1.0'))->not->toBeFalse();
});

test('accepts dotted-numeric OID without leading dot', function () {
	expect(remote_agent_validate_oid('1.3.6.1.2.1.1.1.0'))->not->toBeFalse();
});

test('accepts named MIB OID', function () {
	expect(remote_agent_validate_oid('hrSystemProcesses'))->not->toBeFalse();
});

test('accepts named MIB OID with dots', function () {
	expect(remote_agent_validate_oid('SNMPv2-MIB.sysDescr'))->not->toBeFalse();
});

test('rejects empty string', function () {
	expect(remote_agent_validate_oid(''))->toBeFalse();
});

test('rejects semicolon shell injection', function () {
	expect(remote_agent_validate_oid('.1.3.6.1; rm -rf /'))->toBeFalse();
});

test('rejects backtick injection', function () {
	expect(remote_agent_validate_oid('.1.3.6.1`whoami`'))->toBeFalse();
});

test('rejects path traversal', function () {
	expect(remote_agent_validate_oid('../../../etc/passwd'))->toBeFalse();
});

test('rejects null byte', function () {
	expect(remote_agent_validate_oid(".1.3.6.1\0evil"))->toBeFalse();
});

test('rejects pipe character', function () {
	expect(remote_agent_validate_oid('.1.3.6.1|cat /etc/passwd'))->toBeFalse();
});

// Verify the source also contains the validator to keep source and tests in sync
test('remote_agent.php source contains remote_agent_validate_oid', function () {
	$src = file_get_contents(__DIR__ . '/../../remote_agent.php');
	expect($src)->toContain('function remote_agent_validate_oid(string $oid)');
});
