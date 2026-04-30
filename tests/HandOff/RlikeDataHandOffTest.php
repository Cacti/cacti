<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

if (!file_exists(__DIR__ . '/../../lib/database.php')) {
    test('RLIKE HandOff [skip: lib/database.php not present]', function () {
        expect(true)->toBeTrue();
    })->skip('lib/database.php not present');
    return;
}

if (!function_exists('db_qstr')) {
    function db_qstr($s, $db_conn = false): string {
        return "'" . addslashes((string)$s) . "'";
    }
}
if (!class_exists('CactiSecureType')) {
    class CactiSecureType {
        public static function toString(mixed $v): string { return (string)$v; }
    }
}

require_once __DIR__ . '/../../lib/database.php';

test('HandOff: html_graph uses db_qstr_rlike not bare RLIKE', function () {
    $contents = file_get_contents(__DIR__ . '/../../lib/html_graph.php');
    expect($contents)->toContain('db_qstr_rlike(');
    expect($contents)->not->toMatch("/RLIKE '\" \./");
});

test('HandOff: html_tree uses db_qstr_rlike not bare RLIKE', function () {
    $contents = file_get_contents(__DIR__ . '/../../lib/html_tree.php');
    expect($contents)->toContain('db_qstr_rlike(');
});

test('HandOff: aggregate_graphs uses db_qstr_rlike', function () {
    $contents = file_get_contents(__DIR__ . '/../../aggregate_graphs.php');
    expect($contents)->toContain('db_qstr_rlike(');
});

test('HandOff: aggregate local_graph_ids cast to int array', function () {
    $contents = file_get_contents(__DIR__ . '/../../aggregate_graphs.php');
    expect($contents)->toContain('CactiSecureType::toInt(');
    expect($contents)->not->toContain("IN(' . grv('local_graph_ids') . ')'");
});

test('HandOff: data passes through db_qstr_rlike sanitization', function () {
    $malicious = "value | DROP TABLE;";
    $result = db_qstr_rlike($malicious);
    expect($result)->not->toContain('|');
    expect($result)->not->toContain('DROP');
    expect($result)->toStartWith('RLIKE ');
});
