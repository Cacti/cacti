<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../lib/CactiProcess.php';

test('CactiProcess::split handles quoted args', function () {
    $argv = \Cacti\Process\CactiProcess::split("rrdtool dump '/var/lib/cacti/rrd/host.rrd'");
    expect($argv)->toBe(['rrdtool', 'dump', '/var/lib/cacti/rrd/host.rrd']);
});

test('CactiProcess::split handles simple args', function () {
    $argv = \Cacti\Process\CactiProcess::split('mysqladmin -h localhost status');
    expect($argv)->toBe(['mysqladmin', '-h', 'localhost', 'status']);
});

test('CactiProcess::run returns Process object', function () {
    $process = \Cacti\Process\CactiProcess::run(['echo', 'hello']);
    expect($process)->toBeInstanceOf(\Symfony\Component\Process\Process::class);
    expect(trim($process->getOutput()))->toBe('hello');
    expect($process->getExitCode())->toBe(0);
});

test('CactiProcess::run passes env to process', function () {
    $process = \Cacti\Process\CactiProcess::run(['env'], ['CACTI_TEST_VAR' => 'cacti123']);
    expect($process->getOutput())->toContain('CACTI_TEST_VAR=cacti123');
});

test('CactiProcess::run respects timeout', function () {
    $process = \Cacti\Process\CactiProcess::run(['true'], [], 5.0);
    expect($process->getExitCode())->toBe(0);
});
