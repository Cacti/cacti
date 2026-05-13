<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for graph_view.php JS-context XSS fix.
 *
 * Fix: refreshPage assignment used a single-quoted JS string with
 * sanitize_uri output, allowing backslash breakout. Replaced with
 * validate_redirect_url + json_encode for JS-safe quoting.
 */

$src = file_get_contents(__DIR__ . '/../../graph_view.php');

test('graph_view refreshPage assignment uses json_encode for JS-safe output', function () use ($src) {
    expect($src)->toContain('json_encode(str_replace(');
});

test('graph_view refreshPage uses validate_redirect_url on REQUEST_URI', function () use ($src) {
    $pos = strpos($src, 'refreshPage');
    expect($pos)->not->toBeFalse();

    $fragment = substr($src, $pos, 300);
    expect($fragment)->toContain('validate_redirect_url(');
});

test('graph_view refreshPage does not use single-quoted JS string with sanitize_uri', function () use ($src) {
    // Old pattern embedded a raw PHP print inside a JS single-quoted string.
    // The fix uses json_encode instead, so sanitize_uri must not appear in the refreshPage line.
    $pos = strpos($src, 'refreshPage');
    expect($pos)->not->toBeFalse();
    $fragment = substr($src, $pos, 300);
    expect($fragment)->not->toContain('sanitize_uri(');
});

test('graph_view refreshPage does not use bare sanitize_uri for REQUEST_URI in JS context', function () use ($src) {
    $pos = strpos($src, 'refreshPage');
    expect($pos)->not->toBeFalse();

    $fragment = substr($src, $pos, 300);
    expect($fragment)->not->toContain("sanitize_uri(\$_SERVER['REQUEST_URI']");
});
