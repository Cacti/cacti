<?php

/**
 * Source-scan tests for the data query XML path traversal hardening.
 *
 * Verifies that get_data_query_array() validates xml_path against CACTI_PATH_BASE
 * using realpath() before loading the file, closing a path traversal vector where
 * a DB-stored xml_path such as '<path_cacti>/../../etc/passwd' would resolve to an
 * arbitrary filesystem path after the token replacement step.
 */

$src = file_get_contents(__DIR__ . '/../../lib/data_query.php');

test('get_data_query_array calls realpath on the resolved xml_file_path', function () use ($src) {
    expect($src)->toContain('realpath($xml_file_path)');
});

test('get_data_query_array calls realpath on CACTI_PATH_BASE for boundary anchor', function () use ($src) {
    expect($src)->toContain('realpath(CACTI_PATH_BASE)');
});

test('realpath boundary check uses str_starts_with with DIRECTORY_SEPARATOR', function () use ($src) {
    expect($src)->toContain('str_starts_with($resolved . DIRECTORY_SEPARATOR, $allowed_base . DIRECTORY_SEPARATOR)');
});

test('get_data_query_array returns empty array when xml_path resolves outside base', function () use ($src) {
    // The guard must return early before the file() call.
    $guard_pos = strpos($src, '!str_starts_with($resolved . DIRECTORY_SEPARATOR, $allowed_base . DIRECTORY_SEPARATOR)');
    $file_pos  = strpos($src, "implode('',file(\$xml_file_path))");
    expect($guard_pos)->not->toBeFalse()
        ->and($file_pos)->not->toBeFalse()
        ->and($guard_pos)->toBeLessThan($file_pos);
});

test('boundary check logs a SECURITY message on path violation', function () use ($src) {
    expect($src)->toContain("'SECURITY: data query XML path outside Cacti base:");
});
