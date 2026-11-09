<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The stats command cache is purged when the DSStats mode or peak setting
 * changes.  poller_dsstats.php compares them with dsstats_temp_mode and
 * dsstats_temp_peak, which must be recorded once per purge, not by every
 * data source whose command is built.
 */

namespace DsstatsCommandCachePurgeTest;

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

function read_config_option(string $name) : mixed {
	return $GLOBALS['dsstats_purge_settings'][$name] ?? '';
}

function set_config_option(string $name, mixed $value) : void {
	$GLOBALS['dsstats_purge_settings'][$name] = $value;
}

function db_execute(string $sql) : bool {
	$GLOBALS['dsstats_purge_writes'][] = $sql;

	return true;
}

// poller_dsstats.php runs when it is loaded, so evaluate its purge check alone
function dsstats_purge_check() : void {
	static $fragment = null;

	if ($fragment === null) {
		$source = file_get_contents(dirname(__DIR__, 2) . '/poller_dsstats.php');
		$parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
		$node   = (new \PhpParser\NodeFinder())->findFirst($parser->parse($source), static function ($node) use ($source) {
			return $node instanceof \PhpParser\Node\Stmt\If_ &&
				str_contains(substr($source, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos()), 'TRUNCATE TABLE data_source_stats_command_cache');
		});

		if ($node === null) {
			throw new \RuntimeException('Unable to find the command cache purge in poller_dsstats.php');
		}

		$fragment = substr($source, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1);
	}

	eval('namespace DsstatsCommandCachePurgeTest;' . $fragment);
}

beforeEach(function () {
	$GLOBALS['dsstats_purge_settings'] = ['dsstats_mode' => '1', 'dsstats_peak' => 'on', 'dsstats_temp_mode' => '0', 'dsstats_temp_peak' => 'on'];
	$GLOBALS['dsstats_purge_writes']   = [];
});

afterEach(function () {
	dsstats_test_restore();
});

test('a changed mode purges the command cache once', function () {
	dsstats_purge_check();
	dsstats_purge_check();

	expect($GLOBALS['dsstats_purge_writes'])->toBe(['TRUNCATE TABLE data_source_stats_command_cache'])
		->and($GLOBALS['dsstats_purge_settings']['dsstats_temp_mode'])->toBe('1')
		->and($GLOBALS['dsstats_purge_settings']['dsstats_temp_peak'])->toBe('on');
});

test('building a stats command does not write settings', function () {
	$pdo  = dsstats_test_connect(['dsstats_mode' => 1, 'dsstats_peak' => 'on']);
	$pipe = dsstats_test_rrdtool("ds[traffic].index = 0\nrra[0].cf = \"AVERAGE\"\nOK u:0.00 s:0.00 r:0.00\n");

	expect(\dsstats_get_stats_command(51, 'f.rrd', false, 1, true, $pipe))->toContain('DEF:aa_a="f.rrd":traffic:AVERAGE')
		->and($pdo->statementsLike('INSERT INTO settings'))->toBe([]);
});
