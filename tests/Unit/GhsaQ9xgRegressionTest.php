<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-q9xg-p762-9jm3.
 *
 * Fix: ORDER BY sort_column and sort_direction in lib/api_automation.php
 * hardened with cacti_validate_sort_column() allowlist and ASC/DESC clamp.
 * INET_ATON branch preserved using strict equality.
 */

$src = file_get_contents(__DIR__ . '/../../lib/api_automation.php');

test('api_automation ORDER BY uses cacti_validate_sort_column at the missed call site', function () use ($src) {
    // The fixed function is around line 1210. Verify the helper is called.
    expect($src)->toContain("cacti_validate_sort_column(get_request_var('sort_column'), array('h.description', 'h.hostname', 'h.status', 'ht.name'), 'h.description')");
});

test('api_automation sort_direction is clamped to ASC or DESC at the fixed call site', function () use ($src) {
    // Must appear in the block that builds $sql_query after the validate call.
    expect($src)->toContain("strtoupper(get_request_var('sort_direction')) === 'DESC' ? 'DESC' : 'ASC'");
});

test('api_automation INET_ATON branch uses strict equality', function () use ($src) {
    // Fix tightened == to === for the hostname rewrite branch.
    expect($src)->toContain("if (\$sortby === 'h.hostname')");
});

test('api_automation ORDER BY at fixed site does not build sortby from raw request var', function () use ($src) {
    // The fixed pattern must NOT assign $sortby directly from get_request_var without the helper.
    // Old: $sortby = get_request_var('sort_column');
    // New: $sortby = cacti_validate_sort_column(get_request_var(...), ...);
    // Verify the bare assignment form is gone.
    expect($src)->not->toContain('$sortby = get_request_var(\'sort_column\')');
});
