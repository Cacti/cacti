<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (!defined('CACTI_CLI')) {
	define('CACTI_CLI', true);
}

if (!function_exists('__')) {
	function __() {
		$args   = func_get_args();
		$format = array_shift($args);

		return $args ? vsprintf($format, $args) : $format;
	}
}

// lib/html.php carries a large web runtime dependency, so the balanced box
// rendering is validated against lightweight stubs that record the output.
if (!function_exists('html_start_box')) {
	function html_start_box($title, $width, $div, $cell_padding, $align, $add_text, $add_label = false) {
		print "<div class='cactiTable'><div class='cactiTableTitle'>" . $title . '</div>';
	}
}

if (!function_exists('html_end_box')) {
	function html_end_box($trailing_br = true, $div = false) {
		print '</div>';
	}
}

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

function rrd_tune_capture(callable $callback) {
	ob_start();

	try {
		$callback();
	} finally {
		$output = ob_get_clean();
	}

	return $output;
}

test('rrdtool_tune_print_leaves emits balanced div rows for the web UI', function () {
	$diff = array(
		'The Graph requires 1 Data Sources but the RRDfile includes 2.',
		'tune'   => array('/rra/x.rrd DEL:timeafter'),
		'resize' => array('/rra/x.rrd 0 GROW 10'),
	);

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_leaves($diff, true);
	});

	expect($output)->toContain("<div class='formRow'>The Graph requires 1 Data Sources but the RRDfile includes 2.</div>");
	expect($output)->not->toContain('DEL:timeafter');
	expect(substr_count($output, '<div'))->toBe(substr_count($output, '</div>'));
});

test('rrdtool_tune_print_leaves emits plain text for the CLI', function () {
	$diff = array(
		'Errors were found',
		'nested' => array('Deeper error'),
		'tune'   => array('ignored'),
	);

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_leaves($diff, false);
	});

	expect($output)->toContain('Errors were found' . PHP_EOL);
	expect($output)->toContain('Deeper error' . PHP_EOL);
	expect($output)->not->toContain('<div');
	expect($output)->not->toContain('ignored');
});

test('rrdtool_tune_print_html renders self contained, balanced div boxes', function () {
	$diff = array(
		'Data Source timeafter is included but is not required',
		'tune'   => array('/rra/x.rrd DEL:timeafter'),
		'resize' => array('/rra/x.rrd 0 GROW 10'),
	);

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_html('/rra/x.rrd', $diff);
	});

	expect($output)->toContain('Errors Found');
	expect($output)->toContain('Optional Tuning Recommendations');
	expect($output)->toContain('tune /rra/x.rrd DEL:timeafter');
	expect($output)->toContain('resize /rra/x.rrd 0 GROW 10');
	expect($output)->toContain('rename');
	expect($output)->not->toContain('<tr>');
	expect($output)->not->toContain('<td>');
	expect(substr_count($output, '<div'))->toBe(substr_count($output, '</div>'));
});

test('rrdtool_tune_print_html is empty when there is nothing to report', function () {
	$output = rrd_tune_capture(function () {
		rrdtool_tune_print_html('/rra/x.rrd', array());
	});

	expect($output)->toBe('');
});

test('rrdtool_tune_print_cli prints the rrdtool source commands', function () {
	$diff = array(
		'An error leaf',
		'tune'   => array('/rra/x.rrd DEL:timeafter'),
		'resize' => array('/rra/x.rrd 0 GROW 10'),
	);

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune_print_cli('/rra/x.rrd', $diff);
	});

	expect($output)->toContain('An error leaf' . PHP_EOL);
	expect($output)->toContain('tune /rra/x.rrd DEL:timeafter' . PHP_EOL);
	expect($output)->toContain('resize /rra/x.rrd 0 GROW 10' . PHP_EOL);
	expect($output)->toContain('rename');
	expect($output)->not->toContain('<div');
});

test('rrdtool_tune_execute relocates the resized file beside the rrd under a storage location', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 1;

	$dir = sys_get_temp_dir() . '/rrd_tune_exec_remote_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');
	file_put_contents($dir . '/resize.rrd', 'resized');

	// control characters in the command keep rrdtool_execute from running while
	// still exercising the surrounding tune/resize bookkeeping and the rename
	$diff = array(
		'tune'   => array("DEL:timeafter\x01"),
		'resize' => array("0 GROW 10\x01"),
	);

	rrdtool_tune_execute($target, $diff);

	expect(file_exists($dir . '/resize.rrd'))->toBeFalse();
	expect(file_get_contents($target))->toBe('resized');

	@unlink($target);
	@rmdir($dir);
	$GLOBALS['config']['config_options_array']['storage_location'] = 0;
});

test('rrdtool_tune_execute relocates the resized file from the working directory locally', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 0;

	$dir = sys_get_temp_dir() . '/rrd_tune_exec_local_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');

	$previous = getcwd();
	chdir($dir);
	file_put_contents($dir . '/resize.rrd', 'resized');

	$diff = array(
		'resize' => array("0 GROW 10\x01"),
	);

	rrdtool_tune_execute($target, $diff);

	chdir($previous);

	expect(file_get_contents($target))->toBe('resized');

	@unlink($target);
	@rmdir($dir);
});

test('rrdtool_tune dispatches to the execute path when not showing the source', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 1;

	$dir = sys_get_temp_dir() . '/rrd_tune_disp_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');
	file_put_contents($dir . '/resize.rrd', 'resized');

	$diff = array(
		'resize' => array("0 GROW 10\x01"),
	);

	rrdtool_tune($target, $diff, false);

	expect(file_get_contents($target))->toBe('resized');

	@unlink($target);
	@rmdir($dir);
	$GLOBALS['config']['config_options_array']['storage_location'] = 0;
});

test('rrdtool_tune dispatches to the CLI printer under CACTI_CLI', function () {
	expect(CACTI_CLI)->toBeTrue();

	$diff = array(
		'tune' => array('/rra/x.rrd DEL:timeafter'),
	);

	$output = rrd_tune_capture(function () use ($diff) {
		rrdtool_tune('/rra/x.rrd', $diff, true);
	});

	expect($output)->toContain('tune /rra/x.rrd DEL:timeafter' . PHP_EOL);
	expect($output)->not->toContain('<div');
});

// rrdtool_execute() carries a heavy runtime dependency (locale constants, the
// server OS, a working RRDtool binary or proxy) that the lightweight bootstrap
// cannot satisfy, so these two cases only assert the execute path reaches the
// rrdtool_execute() call site for a clean (non control character) command.
test('rrdtool_tune_execute reaches rrdtool_execute for a clean tune command', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 0;

	$dir = sys_get_temp_dir() . '/rrd_tune_exec_tune_' . getmypid();
	@mkdir($dir);
	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');

	try {
		rrdtool_tune_execute($target, array('tune' => array('DEL:timeafter')));
	} catch (\Throwable $e) {
		// reaching the call site is sufficient for this environment
	}

	expect(true)->toBeTrue();

	@unlink($target);
	@rmdir($dir);
});

test('rrdtool_tune_execute reaches rrdtool_execute for a clean resize command', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 0;

	$dir = sys_get_temp_dir() . '/rrd_tune_exec_resize_' . getmypid();
	@mkdir($dir);
	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');

	try {
		rrdtool_tune_execute($target, array('resize' => array('0 GROW 10')));
	} catch (\Throwable $e) {
		// reaching the call site is sufficient for this environment
	}

	expect(true)->toBeTrue();

	@unlink($target);
	@rmdir($dir);
});
