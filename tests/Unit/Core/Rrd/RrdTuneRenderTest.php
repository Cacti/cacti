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

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

/**
 * Recording box renderers injected into rrdtool_tune_print_html() so the
 * balanced-box rendering can be validated without the large web runtime
 * dependency carried by the real html_start_box()/html_end_box() helpers.
 */
function rrd_tune_box_recorders(array &$titles, &$opened, &$closed) {
	$open = function ($title) use (&$titles, &$opened) {
		$titles[] = $title;
		$opened++;
		print "<div class='cactiTable'><div class='cactiTableTitle'>" . $title . '</div>';
	};

	$close = function () use (&$closed) {
		$closed++;
		print '</div>';
	};

	return array($open, $close);
}

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

	$titles = array();
	$opened = 0;
	$closed = 0;
	list($open, $close) = rrd_tune_box_recorders($titles, $opened, $closed);

	$output = rrd_tune_capture(function () use ($diff, $open, $close) {
		rrdtool_tune_print_html('/rra/x.rrd', $diff, $open, $close);
	});

	// each section is a self contained, balanced box
	expect($titles)->toBe(array('Errors Found', 'Optional Tuning Recommendations'));
	expect($opened)->toBe(2);
	expect($closed)->toBe(2);

	expect($output)->toContain('tune /rra/x.rrd DEL:timeafter');
	expect($output)->toContain('resize /rra/x.rrd 0 GROW 10');
	expect($output)->toContain('rename');
	expect($output)->not->toContain('<tr>');
	expect($output)->not->toContain('<td>');
	expect(substr_count($output, '<div'))->toBe(substr_count($output, '</div>'));
});

test('rrdtool_tune_print_html is empty when there is nothing to report', function () {
	$titles = array();
	$opened = 0;
	$closed = 0;
	list($open, $close) = rrd_tune_box_recorders($titles, $opened, $closed);

	$output = rrd_tune_capture(function () use ($open, $close) {
		rrdtool_tune_print_html('/rra/x.rrd', array(), $open, $close);
	});

	expect($output)->toBe('');
	expect($opened)->toBe(0);
	expect($closed)->toBe(0);
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

test('rrdtool_tune_execute runs the commands and relocates the resized file beside the rrd under a storage location', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 1;

	$dir = sys_get_temp_dir() . '/rrd_tune_exec_remote_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');

	// a recording executor stands in for rrdtool and, like the real resize, only
	// produces resize.rrd beside the rrd when the resize command actually runs
	$commands = array();
	$executor = function ($command) use (&$commands, $dir) {
		$commands[] = $command;

		if (strpos($command, 'resize ') === 0) {
			file_put_contents($dir . '/resize.rrd', 'resized');
		}

		return true;
	};

	$diff = array(
		'tune'   => array(cacti_escapeshellarg($target) . ' DEL:timeafter'),
		'resize' => array(cacti_escapeshellarg($target) . ' 0 GROW 10'),
	);

	rrdtool_tune_execute($target, $diff, $executor);

	expect($commands)->toHaveCount(2);
	expect($commands[0])->toBe('tune ' . cacti_escapeshellarg($target) . ' DEL:timeafter');
	expect($commands[1])->toBe('resize ' . cacti_escapeshellarg($target) . ' 0 GROW 10');

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

	$commands = array();
	$executor = function ($command) use (&$commands) {
		$commands[] = $command;

		if (strpos($command, 'resize ') === 0) {
			file_put_contents('resize.rrd', 'resized');
		}

		return true;
	};

	rrdtool_tune_execute($target, array('resize' => array(cacti_escapeshellarg($target) . ' 0 GROW 10')), $executor);

	chdir($previous);

	expect($commands)->toBe(array('resize ' . cacti_escapeshellarg($target) . ' 0 GROW 10'));
	expect(file_get_contents($target))->toBe('resized');

	@unlink($target);
	@rmdir($dir);
});

test('rrdtool_tune_execute honours the control character guard', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 1;

	$dir = sys_get_temp_dir() . '/rrd_tune_exec_ctrl_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');
	file_put_contents($dir . '/resize.rrd', 'resized');

	$commands = array();
	$executor = function ($command) use (&$commands) {
		$commands[] = $command;

		return true;
	};

	// a command carrying control characters must never reach the executor, but
	// the surrounding resize bookkeeping and rename still run
	rrdtool_tune_execute($target, array('resize' => array("0 GROW 10\x01")), $executor);

	expect($commands)->toBe(array());
	expect(file_get_contents($target))->toBe('resized');

	@unlink($target);
	@rmdir($dir);
	$GLOBALS['config']['config_options_array']['storage_location'] = 0;
});

test('rrdtool_tune dispatches to the execute path when not showing the source', function () {
	$GLOBALS['config']['config_options_array']['storage_location'] = 1;

	$dir = sys_get_temp_dir() . '/rrd_tune_disp_' . getmypid();
	@mkdir($dir);

	$target = $dir . '/data.rrd';
	file_put_contents($target, 'target');
	// control characters keep the real rrdtool_execute from running while the
	// resize rename is still exercised through the default executor
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
