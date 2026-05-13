<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for host.php shell command host_id hardening.
 *
 * Fix: bare integer $host_id appended to shell_exec command wrapped with
 * cacti_escapeshellarg((string) $host_id) for convention parity with the
 * rest of the shell invocations in this file.
 */

$src = file_get_contents(__DIR__ . '/../../host.php');

test('host.php uses cacti_escapeshellarg on host_id in shell_exec call', function () use ($src) {
    expect($src)->toContain('cacti_escapeshellarg((string) $host_id)');
});

test('host.php does not append bare $host_id to shell_exec --id argument', function () use ($src) {
    // The old unsafe pattern: . ' --id=' . $host_id);
    // Must not appear (with only whitespace/quote variation).
    expect($src)->not->toMatch('/\'\ --id=\'\ \.\ \$host_id(?!\s*\)?\s*[;,]?\s*cacti_escapeshellarg)/');
    expect($src)->not->toContain("'--id=' . \$host_id)");
    expect($src)->not->toContain('" --id=" . $host_id)');
});

test('host.php shell_exec for poller_reindex uses cacti_escapeshellarg on all arguments', function () use ($src) {
    $pos = strpos($src, 'poller_reindex_hosts.php');
    expect($pos)->not->toBeFalse();

    $fragment = substr($src, max(0, $pos - 50), 350);
    // All variable arguments must be escaped.
    expect($fragment)->toContain('cacti_escapeshellarg(');
    // The host_id specifically must go through the escaper.
    expect($fragment)->toContain('cacti_escapeshellarg((string) $host_id)');
});
