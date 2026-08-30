<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-level regression guards for the cacti_exec() exit-status fix. The exit
 * code must come from proc_close() (reliable after the pipe reads reap the
 * child), and proc_get_status() results must be treated as possibly non-array.
 */

$root = dirname(__DIR__, 2);

test('cacti_exec takes the exit code from proc_close', function () use ($root) {
	$src = file_get_contents($root . '/lib/functions.php');

	expect($src)->toContain('$exit = proc_close($process);');
	// the old, unreliable read must be gone
	expect($src)->not->toContain("\$exit = \$status['exit_code'];");
});

test('cacti_exec guards a non-array proc_get_status result', function () use ($root) {
	$src = file_get_contents($root . '/lib/functions.php');

	expect($src)->toContain("is_array(\$status) && !empty(\$status['running'])");
	expect($src)->toContain("!is_array(\$status) || empty(\$status['running'])");
});
