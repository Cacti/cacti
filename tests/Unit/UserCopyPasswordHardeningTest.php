<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for user_copy() password placeholder hardening.
 *
 * Fix: replaced mt_rand(100000, 10000000) placeholder with a CSPRNG-backed
 * hashed value and sets must_change_password='on' for the copied user.
 */

$src = file_get_contents(__DIR__ . '/../../lib/auth.php');

test('user_copy does not use mt_rand for password placeholder', function () use ($src) {
    $start = strpos($src, 'function user_copy(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 3000);
    expect($body)->not->toContain('mt_rand(');
});

test('user_copy uses random_bytes for CSPRNG password placeholder', function () use ($src) {
    $start = strpos($src, 'function user_copy(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 3000);
    expect($body)->toContain('random_bytes(');
});

test('user_copy hashes the placeholder with compat_password_hash', function () use ($src) {
    $start = strpos($src, 'function user_copy(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 3000);
    expect($body)->toContain('compat_password_hash(');
    expect($body)->toContain('PASSWORD_DEFAULT');
});

test('user_copy sets must_change_password to on', function () use ($src) {
    $start = strpos($src, 'function user_copy(');
    expect($start)->not->toBeFalse();

    $body = substr($src, $start, 3000);
    expect($body)->toContain("'must_change_password'");
    expect($body)->toContain("'on'");
});
