<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

if (!file_exists(__DIR__ . '/../../lib/CactiProcess.php')) {
    test('CactiProcess HandOff [skip: lib not present]', function () {
        expect(true)->toBeTrue();
    })->skip('lib/CactiProcess.php not present');
    return;
}

require_once __DIR__ . '/../../lib/CactiProcess.php';

test('HandOff: sql.php passes MYSQL_PWD via env not argv', function () {
    $contents = file_get_contents(__DIR__ . '/../../scripts/sql.php');
    expect($contents)->toContain('MYSQL_PWD');
    expect($contents)->not->toContain("'-p' . \$database_password");
    expect($contents)->not->toContain("'--password=' . \$database_password");
});

test('HandOff: ss_sql.php uses CactiProcess not shell_exec', function () {
    $contents = file_get_contents(__DIR__ . '/../../scripts/ss_sql.php');
    expect($contents)->toContain('CactiProcess');
    expect($contents)->not->toContain('shell_exec(');
});

test('HandOff: ping.php uses argv array not string interpolation', function () {
    $contents = file_get_contents(__DIR__ . '/../../lib/ping.php');
    expect($contents)->toContain('CactiProcess::run(');
    expect($contents)->not->toContain("shell_exec(cacti_escapeshellarg(\$fping)");
});

test('HandOff: poller_realtime uses CactiProcess not shell_exec', function () {
    $contents = file_get_contents(__DIR__ . '/../../poller_realtime.php');
    expect($contents)->toContain('CactiProcess::run(');
    expect($contents)->not->toContain('shell_exec("$command_string');
});

test('HandOff: CactiProcess::run data flows - argv array preserved as-is', function () {
    $argv = ['echo', 'hello world'];
    $process = \Cacti\Process\CactiProcess::run($argv);
    expect(trim($process->getOutput()))->toBe('hello world');
});
