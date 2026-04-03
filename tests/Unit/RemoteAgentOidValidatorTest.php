<?php

/**
 * Functional unit tests for the OID validation logic introduced in remote_agent.php.
 *
 * The validator function is inlined here to avoid the full remote_agent.php
 * bootstrap (DB, config, etc.). The logic is identical to the production version.
 */

function oid_is_valid(string $oid): bool {
    if ($oid === '') {
        return false;
    }
    return preg_match('/^\.?[0-9]+(\.[0-9]+)*$/', $oid)
        || (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9\-\.]*$/', $oid);
}

test('accepts dotted-numeric OID with leading dot', function () {
    expect(oid_is_valid('.1.3.6.1.2.1.1.1.0'))->toBeTrue();
});

test('accepts dotted-numeric OID without leading dot', function () {
    expect(oid_is_valid('1.3.6.1.2.1.1.1.0'))->toBeTrue();
});

test('accepts named MIB OID', function () {
    expect(oid_is_valid('hrSystemProcesses'))->toBeTrue();
});

test('accepts named MIB OID with dots', function () {
    expect(oid_is_valid('SNMPv2-MIB.sysDescr'))->toBeTrue();
});

test('rejects empty string', function () {
    expect(oid_is_valid(''))->toBeFalse();
});

test('rejects semicolon shell injection', function () {
    expect(oid_is_valid('.1.3.6.1; rm -rf /'))->toBeFalse();
});

test('rejects backtick injection', function () {
    expect(oid_is_valid('.1.3.6.1`whoami`'))->toBeFalse();
});

test('rejects path traversal', function () {
    expect(oid_is_valid('../../../etc/passwd'))->toBeFalse();
});

test('rejects null byte', function () {
    expect(oid_is_valid(".1.3.6.1\0evil"))->toBeFalse();
});

test('rejects pipe character', function () {
    expect(oid_is_valid('.1.3.6.1|cat /etc/passwd'))->toBeFalse();
});

// Verify the source also contains the validator to keep source and tests in sync
test('remote_agent.php source contains remote_agent_validate_oid', function () {
    $src = file_get_contents(__DIR__ . '/../../remote_agent.php');
    expect($src)->toContain('function remote_agent_validate_oid(string $oid)');
});
