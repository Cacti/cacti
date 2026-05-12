<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression guards: ensure no backsliding on security hardening.
 * These run on every PR merge to develop.
 */

test('Regression: lib/ping.php does not use bare shell_exec with string concat', function () {
    $contents = file_get_contents(__DIR__ . '/../../lib/ping.php');
    expect($contents)->not->toContain("shell_exec(cacti_escapeshellarg(\$fping) . '-q'");
});

test('Regression: scripts/sql.php does not pass password in argv', function () {
    $contents = file_get_contents(__DIR__ . '/../../scripts/sql.php');
    expect($contents)->not->toContain("'-p' . cacti_escapeshellarg(\$database_password)");
    expect($contents)->not->toContain("'-p' . \$database_password");
});

test('Regression: lib/html_tree.php does not use bare RLIKE string concat', function () {
    $contents = file_get_contents(__DIR__ . '/../../lib/html_tree.php');
    expect($contents)->not->toMatch("/RLIKE '\" \. grv/");
    expect($contents)->not->toMatch('/RLIKE \'" \. grv/');
})->skip('hardening PR not yet merged into develop');

test('Regression: aggregate_graphs.php wraps all header redirects', function () {
    $contents = file_get_contents(__DIR__ . '/../../aggregate_graphs.php');
    // All Location headers should use validate_redirect_url
    preg_match_all("/header\('Location: '/", $contents, $raw);
    preg_match_all("/header\('Location: ' \. validate_redirect_url/", $contents, $validated);
    // Should have zero raw (non-validated) Location headers
    expect(count($raw[0]))->toBe(0);
})->skip('redirect-validation hardening PR not yet merged into develop');

test('Regression: auth_changepassword.php uses validate_redirect_url', function () {
    $contents = file_get_contents(__DIR__ . '/../../auth_changepassword.php');
    expect($contents)->toContain('validate_redirect_url(');
    expect($contents)->not->toContain("? \$_ref : 'index.php'");
});
