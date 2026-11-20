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

/*
 * These contracts execute the real lib/rrd.php so the destructuring assignments
 * in rrdtool_parse_fetch_output() and rrdtool_create_error_image() stay covered.
 */
beforeEach(function () {
	$GLOBALS['__rrd_cov_config_backup']  = $GLOBALS['config'] ?? null;
	$GLOBALS['__rrd_cov_dejavu_backup']  = $GLOBALS['dejavu_paths'] ?? null;
	$GLOBALS['__rrd_cov_session_set']    = isset($_SESSION);
	$GLOBALS['__rrd_cov_session_backup'] = $_SESSION ?? null;

	$GLOBALS['config']['base_path']       = dirname(__DIR__, 4);
	$GLOBALS['config']['cacti_server_os'] = 'unix';
	$GLOBALS['config']['config_options_array']['selected_theme'] = 'modern';
	$GLOBALS['dejavu_paths']              = array(
		'/usr/share/fonts/dejavu-sans-fonts',
		'/usr/share/fonts/dejavu',
		'/usr/share/fonts/truetype/dejavu',
	);
});

afterEach(function () {
	$GLOBALS['config'] = $GLOBALS['__rrd_cov_config_backup'];

	if ($GLOBALS['__rrd_cov_dejavu_backup'] === null) {
		unset($GLOBALS['dejavu_paths']);
	} else {
		$GLOBALS['dejavu_paths'] = $GLOBALS['__rrd_cov_dejavu_backup'];
	}

	if ($GLOBALS['__rrd_cov_session_set']) {
		$_SESSION = $GLOBALS['__rrd_cov_session_backup'];
	} else {
		unset($_SESSION);
	}

	unset(
		$GLOBALS['__rrd_cov_config_backup'],
		$GLOBALS['__rrd_cov_dejavu_backup'],
		$GLOBALS['__rrd_cov_session_set'],
		$GLOBALS['__rrd_cov_session_backup']
	);
});

test('rrdtool_parse_fetch_output splits each data row on the first colon', function () {
	$output = "ds0 ds1\n1700000400: 1 2\n1700000700: 3 nan\n";

	$parsed = rrdtool_parse_fetch_output($output, 1700000700, true);

	expect($parsed['data_source_names'])->toBe(array('ds0', 'ds1'))
		->and($parsed['values'][0][1700000400])->toBe('1')
		->and($parsed['values'][1][1700000400])->toBe('2')
		->and($parsed['values'][0][1700000700])->toBe('3')
		->and($parsed['values'][1][1700000700])->toBe('U');
});

test('rrdtool_parse_fetch_output drops rows beyond the requested end time', function () {
	$output = "value\n1700000400: 1\n1700000700: 2\n";

	$parsed = rrdtool_parse_fetch_output($output, 1700000400);

	expect($parsed['values'][0])->toHaveKey(1700000400)
		->and($parsed['values'][0])->not->toHaveKey(1700000700);
});

test('rrdtool_create_error_image renders a PNG and resolves its theme colours', function () {
	$image = rrdtool_create_error_image('ERROR: synthetic coverage message');

	expect($image)->toBeString()
		->and(strlen($image))->toBeGreaterThan(0)
		->and(substr($image, 0, 4))->toBe("\x89PNG");
});

test('rrdtool_create_error_image honours an explicit width and height', function () {
	$image = rrdtool_create_error_image('ERROR: resized coverage message', 225, 100);

	expect(substr($image, 0, 4))->toBe("\x89PNG");

	$info = getimagesizefromstring($image);

	expect($info)->not->toBeFalse()
		->and($info[0])->toBe(225)
		->and($info[1])->toBe(100);
});
