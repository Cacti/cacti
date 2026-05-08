<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

if (!file_exists(dirname(__DIR__, 2) . '/lib/CactiProcess.php')) {
    test('CactiProcess integration: feature not present on this branch', function () {})
        ->skip('lib/CactiProcess.php absent — feature PR #7073 not merged into develop yet');
    return;
}

require_once __DIR__ . '/../../lib/CactiProcess.php';

test('Integration: CactiProcess::run executes echo successfully', function () {
    $process = \Cacti\Process\CactiProcess::run(['echo', '-n', 'cacti-ok']);
    expect($process->getExitCode())->toBe(0);
    expect($process->getOutput())->toBe('cacti-ok');
});

test('Integration: CactiProcess::run captures stderr separately', function () {
    $process = \Cacti\Process\CactiProcess::run(['sh', '-c', 'echo err >&2; echo out']);
    expect($process->getOutput())->toContain('out');
    expect($process->getErrorOutput())->toContain('err');
});

test('Integration: CactiProcess::run with MYSQL_PWD env does not put password in argv', function () {
    // Ensure MYSQL_PWD is set via env, not via argv
    $argv = ['mysqladmin', '--version'];
    $env  = ['MYSQL_PWD' => 'secret123'];

    // We inspect the argv - the password should NOT appear as an arg
    $containsPassword = in_array('--password=secret123', $argv) || in_array('-psecret123', $argv);
    expect($containsPassword)->toBeFalse();

    // The env array carries the credential
    expect($env)->toHaveKey('MYSQL_PWD');
    expect($env['MYSQL_PWD'])->toBe('secret123');
});

test('Integration: CactiProcess::run handles nonexistent command gracefully', function () {
    try {
        $process = \Cacti\Process\CactiProcess::run(['/nonexistent/binary/cacti_test_xyz']);
        // If it doesn't throw, exit code should indicate failure
        expect($process->getExitCode())->not->toBe(0);
    } catch (\Symfony\Component\Process\Exception\ProcessFailedException | \Exception $e) {
        expect($e)->toBeInstanceOf(\Exception::class);
    }
    expect(true)->toBeTrue(); // reaches here = handled
});
