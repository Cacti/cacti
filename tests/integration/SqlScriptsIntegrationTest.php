<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

if (!file_exists(dirname(__DIR__, 2) . '/lib/CactiProcess.php')) {
    test('SQL scripts integration: CactiProcess feature not present on this branch', function () {})
        ->skip('lib/CactiProcess.php absent — feature PR #7073 not merged into develop yet');
    return;
}

test('Integration: scripts/sql.php file contains no shell_exec calls', function () {
    $path = __DIR__ . '/../../scripts/sql.php';
    if (!file_exists($path)) {
        expect(true)->toBeTrue();
        return;
    }
    $contents = file_get_contents($path);
    expect($contents)->not->toContain('shell_exec(');
    expect($contents)->toContain('CactiProcess');
});

test('Integration: scripts/ss_sql.php file contains no shell_exec calls', function () {
    $path = __DIR__ . '/../../scripts/ss_sql.php';
    if (!file_exists($path)) {
        expect(true)->toBeTrue();
        return;
    }
    $contents = file_get_contents($path);
    expect($contents)->not->toContain('shell_exec(');
    expect($contents)->toContain('CactiProcess');
});

test('Integration: lib/ping.php passes argv array not concatenated string', function () {
    $path = __DIR__ . '/../../lib/ping.php';
    if (!file_exists($path)) {
        expect(true)->toBeTrue();
        return;
    }
    $contents = file_get_contents($path);
    // Old pattern used string concatenation with cacti_escapeshellarg
    expect($contents)->not->toContain("shell_exec(cacti_escapeshellarg(\$fping)");
    expect($contents)->toContain('CactiProcess::run(');
});

test('Integration: poller_realtime.php uses CactiProcess not shell_exec', function () {
    $path = __DIR__ . '/../../poller_realtime.php';
    if (!file_exists($path)) {
        expect(true)->toBeTrue();
        return;
    }
    $contents = file_get_contents($path);
    expect($contents)->not->toContain('shell_exec("$command_string');
    expect($contents)->toContain('CactiProcess::run(');
});
