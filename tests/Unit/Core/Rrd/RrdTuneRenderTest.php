<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';
require_once CACTI_PATH_LIBRARY . '/html.php';

function rrd_tune_capture(callable $callback) : string {
	ob_start();

	try {
		$callback();
	} finally {
		$output = ob_get_clean();
	}

	return $output;
}

test('rrdtool_tune_print_leaves emits balanced div rows for the web UI', function () {
	$diff = [
		'The Graph requires 1 Data Sources but the RRDfile includes 2.',
		'tune'   => ['/rra/x.rrd DEL:timeafter'],
		'resize' => ['/rra/x.rrd 0 GROW 10'],
	];

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_leaves($diff, true);
	});

	expect($output)->toContain("<div class='formRow'>The Graph requires 1 Data Sources but the RRDfile includes 2.</div>");
	// the tune and resize branches are intentionally skipped by the leaf printer
	expect($output)->not->toContain('DEL:timeafter');
	expect(substr_count($output, '<div'))->toBe(substr_count($output, '</div>'));
});

test('rrdtool_tune_print_leaves emits plain text for the CLI', function () {
	$diff = [
		'Errors were found',
		'nested' => ['Deeper error'],
		'tune'   => ['ignored'],
	];

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_leaves($diff, false);
	});

	expect($output)->toContain('Errors were found' . PHP_EOL);
	expect($output)->toContain('Deeper error' . PHP_EOL);
	expect($output)->not->toContain('<div');
	expect($output)->not->toContain('ignored');
});

test('rrdtool_tune_print_html renders self contained, balanced div boxes', function () {
	$diff = [
		'Data Source timeafter is included but is not required',
		'tune'   => ['/rra/x.rrd DEL:timeafter'],
		'resize' => ['/rra/x.rrd 0 GROW 10'],
	];

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_html('/rra/x.rrd', $diff);
	});

	expect($output)->toContain('Errors Found');
	expect($output)->toContain('Optional Tuning Recommendations');
	expect($output)->toContain('tune /rra/x.rrd DEL:timeafter');
	expect($output)->toContain('resize /rra/x.rrd 0 GROW 10');
	expect($output)->toContain('rename');
	// the panel must not emit raw table markup any more
	expect($output)->not->toContain('<tr>');
	expect($output)->not->toContain('<td>');
	// every div that opens must close
	expect(substr_count($output, '<div'))->toBe(substr_count($output, '</div>'));
});

test('rrdtool_tune_print_html is empty when there is nothing to report', function () {
	$output = rrd_tune_capture(function () {
		rrdtool_tune_print_html('/rra/x.rrd', []);
	});

	expect($output)->toBe('');
});

test('rrdtool_tune_print_cli prints the rrdtool source commands', function () {
	$diff = [
		'An error leaf',
		'tune'   => ['/rra/x.rrd DEL:timeafter'],
		'resize' => ['/rra/x.rrd 0 GROW 10'],
	];

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_cli('/rra/x.rrd', $diff);
	});

	expect($output)->toContain('An error leaf' . PHP_EOL);
	expect($output)->toContain('tune /rra/x.rrd DEL:timeafter' . PHP_EOL);
	expect($output)->toContain('resize /rra/x.rrd 0 GROW 10' . PHP_EOL);
	expect($output)->toContain('rename');
	expect($output)->not->toContain('<div');
});

test('rrdtool_tune_execute runs the tune and resize commands and relocates the resized file', function () {
	$dir = sys_get_temp_dir() . '/rrd_tune_exec_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');

	$diff = [
		'tune'   => [cacti_escapeshellarg($target) . ' DEL:timeafter'],
		'resize' => [cacti_escapeshellarg($target) . ' 0 GROW 10'],
	];

	// a recording executor stands in for rrdtool and, like the real resize,
	// only produces resize.rrd when the resize command actually runs
	$commands = [];
	$executor = function ($command) use (&$commands, $dir) {
		$commands[] = $command;

		if (strpos($command, 'resize ') === 0) {
			file_put_contents($dir . '/resize.rrd', 'resized');
		}

		return true;
	};

	rrdtool_tune_execute($target, $diff, $executor);

	// both commands must have been executed, not just the rename
	expect($commands)->toHaveCount(2);
	expect($commands[0])->toBe('tune ' . cacti_escapeshellarg($target) . ' DEL:timeafter');
	expect($commands[1])->toBe('resize ' . cacti_escapeshellarg($target) . ' 0 GROW 10');

	// the resize step renames resize.rrd over the target
	expect(file_exists($dir . '/resize.rrd'))->toBeFalse();
	expect(file_get_contents($target))->toBe('resized');

	@unlink($target);
	@rmdir($dir);
});

test('rrdtool_tune_execute skips the resize rename when no resize is produced', function () {
	$dir = sys_get_temp_dir() . '/rrd_tune_noresize_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');

	$commands  = [];
	$executor  = function ($command) use (&$commands) {
		$commands[] = $command;

		return true;
	};

	rrdtool_tune_execute($target, ['tune' => [cacti_escapeshellarg($target) . ' DEL:timeafter']], $executor);

	expect($commands)->toBe(['tune ' . cacti_escapeshellarg($target) . ' DEL:timeafter']);
	expect(file_get_contents($target))->toBe('target');

	@unlink($target);
	@rmdir($dir);
});

test('rrdtool_tune dispatches to the execute path when not showing the source', function () {
	$dir = sys_get_temp_dir() . '/rrd_tune_disp_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');
	file_put_contents($dir . '/resize.rrd', 'resized');

	$diff = [
		'resize' => [cacti_escapeshellarg($target) . ' 0 GROW 10'],
	];

	// no executor is supplied, so the real rrdtool_execute is invoked; it returns
	// without side effects in this environment while the resize rename still runs
	rrdtool_tune($target, $diff, false);

	expect(file_get_contents($target))->toBe('resized');

	@unlink($target);
	@rmdir($dir);
});

test('rrdtool_tune dispatches to the CLI printer under CACTI_CLI', function () {
	expect(CACTI_CLI)->toBeTrue();

	$diff = [
		'tune' => ['/rra/x.rrd DEL:timeafter'],
	];

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune('/rra/x.rrd', $diff, true);
	});

	expect($output)->toContain('tune /rra/x.rrd DEL:timeafter' . PHP_EOL);
	expect($output)->not->toContain('<div');
});
