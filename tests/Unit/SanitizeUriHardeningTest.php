<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for sanitize_uri() drop-list hardening in lib/functions.php.
 *
 * Fix: appended backslash, NUL (\0), carriage return (\r), and newline (\n)
 * to the drop-char list. These characters could be used for header injection
 * and path traversal across all callers of sanitize_uri().
 */

$src = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('sanitize_uri drop-char list contains backslash', function () use ($src) {
    $start = strpos($src, 'function sanitize_uri(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 2000);
    // Stored in source as "\\" (double-quoted two-char escape)
    expect($body)->toContain('"\\' . '\\"');
});

test('sanitize_uri drop-char list contains NUL character', function () use ($src) {
    $start = strpos($src, 'function sanitize_uri(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 2000);
    expect($body)->toContain('"\0"');
});

test('sanitize_uri drop-char list contains carriage return', function () use ($src) {
    $start = strpos($src, 'function sanitize_uri(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 2000);
    expect($body)->toContain('"\r"');
});

test('sanitize_uri drop-char list contains newline', function () use ($src) {
    $start = strpos($src, 'function sanitize_uri(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 2000);
    expect($body)->toContain('"\n"');
});

test('sanitize_uri drop-char and drop-replace arrays have equal length', function () use ($src) {
    $start = strpos($src, 'function sanitize_uri(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 2000);

    // Extract match array: find everything between the first ( and the matching );
    $matchStart = strpos($body, '$drop_char_match');
    $matchOpen  = strpos($body, '(', $matchStart);
    $matchClose = strpos($body, ');', $matchOpen);
    $matchInner = substr($body, $matchOpen + 1, $matchClose - $matchOpen - 1);
    $matchCount = substr_count($matchInner, ',') + 1;

    $replaceStart = strpos($body, '$drop_char_replace');
    $replaceOpen  = strpos($body, '(', $replaceStart);
    $replaceClose = strpos($body, ');', $replaceOpen);
    $replaceInner = substr($body, $replaceOpen + 1, $replaceClose - $replaceOpen - 1);
    $replaceCount = substr_count($replaceInner, ',') + 1;

    expect($matchCount)->toBe($replaceCount);
});
