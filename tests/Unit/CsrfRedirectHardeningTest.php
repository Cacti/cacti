<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for csrf.php open redirect fix.
 *
 * Fix: csrf timeout handler replaced sanitize_uri (substring blacklist)
 * with validate_redirect_url (scheme/host validation) on REQUEST_URI
 * before emitting the Location: header.
 */

$src = file_get_contents(__DIR__ . '/../../include/csrf.php');

test('csrf timeout handler uses validate_redirect_url not sanitize_uri for Location header', function () use ($src) {
    $timeoutPos = strpos($src, 'function csrf_timeout_handler(');
    if ($timeoutPos === false) {
        // Alternative: the fix may be in a non-function block
        $timeoutPos = strpos($src, 'csrf_timeout');
    }
    expect($timeoutPos)->not->toBeFalse();

    $body = substr($src, $timeoutPos, 600);
    expect($body)->toContain('validate_redirect_url(');
    expect($body)->not->toContain("sanitize_uri(\$_SERVER['REQUEST_URI'])");
});

test('csrf.php Location header does not use sanitize_uri on REQUEST_URI', function () use ($src) {
    // sanitize_uri on REQUEST_URI must not appear in any header() call.
    preg_match_all("/header\s*\(\s*['\"]Location:[^;]+;/", $src, $matches);
    foreach ($matches[0] as $header) {
        expect($header)->not->toContain('sanitize_uri(');
    }
});

test('csrf.php uses validate_redirect_url in log call too', function () use ($src) {
    expect($src)->toContain('validate_redirect_url($_SERVER[\'REQUEST_URI\'])');
});
