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
$_next  = strpos($_src, "\nfunction ", $_start + 1);
eval(substr($_src, $_start, $_next - $_start));
unset($_src, $_start, $_next);

if (!function_exists('remote_agent_validate_oid')) {
	throw new \RuntimeException('eval() did not define remote_agent_validate_oid — check source extraction');
}

test('accepts dotted-numeric OID with leading dot', function () {
	expect(remote_agent_validate_oid('.1.3.6.1.2.1.1.1.0'))->toBe('.1.3.6.1.2.1.1.1.0');
});

test('accepts dotted-numeric OID without leading dot', function () {
	expect(remote_agent_validate_oid('1.3.6.1.2.1.1.1.0'))->toBe('1.3.6.1.2.1.1.1.0');
});

test('accepts named MIB OID', function () {
	expect(remote_agent_validate_oid('hrSystemProcesses'))->toBe('hrSystemProcesses');
});

test('accepts named MIB OID with dots', function () {
	expect(remote_agent_validate_oid('SNMPv2-MIB.sysDescr'))->toBe('SNMPv2-MIB.sysDescr');
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

test('rejects OID with trailing dot', function () {
	expect(remote_agent_validate_oid('1.3.6.1.'))->toBeFalse();
});

test('rejects OID with multiple leading dots', function () {
	expect(remote_agent_validate_oid('..1.3.6.1'))->toBeFalse();
});

test('rejects OID with consecutive dots', function () {
	expect(remote_agent_validate_oid('1..3.6'))->toBeFalse();
});

test('handles extremely long valid OID without timeout', function () {
	// 501-char dotted-numeric OID; verifies no catastrophic regex backtracking
	$oid = str_repeat('1.', 250) . '0';
	expect(remote_agent_validate_oid($oid))->not->toBeFalse();
});

// Verify the source also contains the validator to keep source and tests in sync
test('remote_agent.php source contains remote_agent_validate_oid', function () {
	$src = file_get_contents(__DIR__ . '/../../remote_agent.php');
	expect($src)->toContain('function remote_agent_validate_oid(string $oid)');
});
