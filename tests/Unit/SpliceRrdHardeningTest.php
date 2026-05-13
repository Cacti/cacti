<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for cli/splice_rrd.php display_errors hardening.
 *
 * Fix: removed unconditional ini_set('display_errors', 'On') which leaked
 * PHP error output to stdout/stderr in all run modes, not just debug.
 */

$src = file_get_contents(__DIR__ . '/../../cli/splice_rrd.php');

test('splice_rrd does not unconditionally set display_errors to On', function () use ($src) {
    // The removed line must not be present at all, or only inside a debug guard.
    // Simple check: the string must not appear outside a conditional.
    $pos = strpos($src, "ini_set('display_errors', 'On')");
    if ($pos === false) {
        expect(true)->toBeTrue();
        return;
    }
    // If it still exists, it must be inside an if($debug) block.
    $context = substr($src, max(0, $pos - 200), 200);
    expect($context)->toContain('$debug');
});

test('splice_rrd does not expose display_errors On to non-debug runs', function () use ($src) {
    // Confirm the raw unconditional form is absent.
    expect($src)->not->toContain("\nini_set('display_errors', 'On');\n");
});
