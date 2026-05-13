<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-72vr-jr4v-55vf.
 *
 * Fix: ORDER BY sort_column and sort_direction in lib/html_reports.php
 * hardened with cacti_validate_sort_column() allowlist and ASC/DESC clamp.
 * Prevents column-name pivot oracle and LIMIT bypass via comment injection.
 */

$src = file_get_contents(__DIR__ . '/../../lib/html_reports.php');

test('html_reports ORDER BY uses cacti_validate_sort_column', function () use ($src) {
    expect($src)->toContain('cacti_validate_sort_column(get_request_var(\'sort_column\')');
});

test('html_reports sort_column allowlist contains expected report columns', function () use ($src) {
    expect($src)->toContain("array('name', 'user_id', 'enabled', 'mailtime', 'lastsent', 'intrvl', 'count')");
});

test('html_reports sort_direction is clamped to ASC or DESC', function () use ($src) {
    expect($src)->toContain("strtoupper(get_request_var('sort_direction')) === 'DESC' ? 'DESC' : 'ASC'");
});

test('html_reports does not concatenate raw sort_column into ORDER BY', function () use ($src) {
    // Neither of these unsafe patterns should appear near an ORDER BY clause.
    // The only get_request_var('sort_column') usage in ORDER BY must go through the helper.
    $orderByPos = strpos($src, 'ORDER BY " .');
    expect($orderByPos)->not->toBeFalse();

    $fragment = substr($src, $orderByPos, 400);
    expect($fragment)->not->toContain("get_request_var('sort_column') . ' '");
});

test('html_reports does not concatenate raw sort_direction into ORDER BY', function () use ($src) {
    $orderByPos = strpos($src, 'ORDER BY " .');
    expect($orderByPos)->not->toBeFalse();

    $fragment = substr($src, $orderByPos, 400);
    expect($fragment)->not->toContain("get_request_var('sort_direction') . ' LIMIT'");
});
