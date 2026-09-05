<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-level regression guards for the cacti_exec() exit-status fix. A valid
 * exit code observed when the child stops must survive later status reads, with
 * proc_close() retained as the fallback when no usable status was observed.
 */

$root = dirname(__DIR__, 2);

test('cacti_exec preserves an observed exit code and falls back to proc_close', function () use ($root) {
	$src = file_get_contents($root . '/lib/functions.php');
	$loopStart = strpos($src, 'while ($remaining > 0)');
	$loopEnd   = strpos($src, 'fclose($pipes[1]);', $loopStart);
	$loopBody  = substr($src, $loopStart, $loopEnd - $loopStart);

	expect($loopBody)->toContain("isset(\$status['exitcode']) && \$status['exitcode'] >= 0")
		->and($loopBody)->toContain("\$exit = (int) \$status['exitcode'];")
		->and($src)->toContain("isset(\$status['exitcode']) && \$status['exitcode'] >= 0")
		->and($src)->toContain('$close_exit = proc_close($process);')
		->and($src)->toContain('if ($exit === null) {')
		->and($src)->toContain('$exit = $close_exit;');
	// the old, unreliable read must be gone
	expect($src)->not->toContain("\$exit = \$status['exit_code'];");
});

test('cacti_exec guards a non-array proc_get_status result', function () use ($root) {
	$src = file_get_contents($root . '/lib/functions.php');

	expect($src)->toContain("is_array(\$status) && !empty(\$status['running'])");
	expect($src)->toContain("!is_array(\$status) || empty(\$status['running'])");
});
