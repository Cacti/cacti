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

namespace RrdCommandBuilderFailureTest;

use Cacti\Rrd\RrdCommand;

// Database lookups and the RRDtool process are replaced inside this namespace only.
function get_data_source_item_name(int $id) : string {
	return 'traffic_in';
}

function get_data_source_path(int $id, bool $expand) : string {
	return $GLOBALS['tune_fixture_path'];
}

function rrdtool_execute(RrdCommand $command, mixed ...$options) : bool {
	$GLOBALS['tune_fixture_commands'][] = [$command->operation, $command->arguments, $options];

	return $GLOBALS['tune_fixture_result'];
}

function cacti_log(string $message, mixed ...$options) : bool {
	$GLOBALS['tune_fixture_logs'][] = $message;

	return true;
}

require_once CACTI_PATH_LIBRARY . '/rrd.php';
require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/rrd.php', 'rrdtool_function_tune', __NAMESPACE__);
\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/rrd.php', 'rrdtool_function_update', __NAMESPACE__);

beforeEach(function () : void {
	$this->directory = sys_get_temp_dir() . '/cacti-rrd-builder-' . bin2hex(random_bytes(8));
	mkdir($this->directory, 0700);
	$GLOBALS['tune_fixture_path']     = $this->directory . '/tune.rrd';
	$GLOBALS['tune_fixture_commands'] = [];
	$GLOBALS['tune_fixture_logs']     = [];
	$GLOBALS['tune_fixture_result']   = true;
});

afterEach(function () : void {
	foreach (glob($this->directory . '/*') as $path) {
		unlink($path);
	}
	rmdir($this->directory);
	unset($GLOBALS['tune_fixture_path'], $GLOBALS['tune_fixture_commands'], $GLOBALS['tune_fixture_logs'], $GLOBALS['tune_fixture_result']);
});

test('tune sends every populated option for an existing RRD file and logs a failed tune', function () : void {
	touch($GLOBALS['tune_fixture_path']);
	$tune = ['data_source_id' => 7, 'heartbeat' => '600', 'minimum' => '0', 'maximum' => '100',
		'data-source-type'       => 2, 'data-source-rename' => 'traffic_out'];
	rrdtool_function_tune($tune);
	expect($GLOBALS['tune_fixture_commands'])->toBe([['tune', [
		$GLOBALS['tune_fixture_path'],
		'--heartbeat', 'traffic_in:600', '--minimum', 'traffic_in:0', '--maximum', 'traffic_in:100',
		'--data-source-type', 'traffic_in:COUNTER', '--data-source-rename', 'traffic_in:traffic_out',
	], [false, RRDTOOL_OUTPUT_BOOLEAN, null, 'WEBLOG']]])->and($GLOBALS['tune_fixture_logs'])->toBe([]);

	$GLOBALS['tune_fixture_commands'] = [];
	$GLOBALS['tune_fixture_result']   = false;
	rrdtool_function_tune(['heartbeat' => '', 'minimum' => '', 'maximum' => '5', 'data-source-type' => 1, 'data-source-rename' => ''] + $tune);
	expect($GLOBALS['tune_fixture_commands'][0][1])->toBe([$GLOBALS['tune_fixture_path'], '--maximum', 'traffic_in:5', '--data-source-type', 'traffic_in:GAUGE'])
		->and($GLOBALS['tune_fixture_logs'])->toHaveCount(1)
		->and($GLOBALS['tune_fixture_logs'][0])->toStartWith('ERROR: Unable to tune ');
});

test('tune skips a missing RRD file and rejects an unusable path', function () : void {
	$tune = ['data_source_id' => 7, 'heartbeat' => '600', 'minimum' => '', 'maximum' => '',
		'data-source-type'       => 1, 'data-source-rename' => ''];
	rrdtool_function_tune($tune);
	expect($GLOBALS['tune_fixture_commands'])->toBe([])->and($GLOBALS['tune_fixture_logs'])->toBe([]);

	$GLOBALS['tune_fixture_path'] = $this->directory . "/tu\x01ne.rrd";
	rrdtool_function_tune($tune);
	expect($GLOBALS['tune_fixture_commands'])->toBe([])
		->and($GLOBALS['tune_fixture_logs'])->toHaveCount(1)
		->and($GLOBALS['tune_fixture_logs'][0])->toStartWith('ERROR: Unable to tune ');
});

test('an update whose template the command builder rejects is not sent or counted', function () : void {
	$path = $this->directory . '/update.rrd';
	touch($path);
	$acknowledged = [];
	$updates      = [$path => ['local_data_id' => 0, 'times' => [1700000060 => ["va\x01lue" => 1], 1700000120 => ['value' => 2]]]];

	expect(rrdtool_function_update($updates, null, $acknowledged))->toBe(0)
		->and($acknowledged)->toBe([])
		->and($GLOBALS['tune_fixture_commands'])->toBe([])
		->and($GLOBALS['tune_fixture_logs'])->toHaveCount(1)
		->and($GLOBALS['tune_fixture_logs'][0])->toStartWith('ERROR: RRDtool update rejected for ');
});

test('invalid graph options return the graph error before any graph is built', function () : void {
	expect(\rrdtool_function_graph(1, 0, ['graph_width' => 'wide']))->toBe('ERROR: Invalid graph options');
});
