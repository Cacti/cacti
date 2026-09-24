<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use Cacti\Rrd\DataSource\FetchCommandBuilder;
use Cacti\Rrd\DataSource\TuneCommandBuilder;
use Cacti\Rrd\DataSource\UpdateCommandBuilder;

$rrdRoot = dirname(__DIR__, 4) . '/lib/Rrd';

require_once "$rrdRoot/RrdCommand.php";
require_once "$rrdRoot/DataSource/FetchCommandBuilder.php";
require_once "$rrdRoot/DataSource/TuneCommandBuilder.php";
require_once "$rrdRoot/DataSource/UpdateCommandBuilder.php";

test('fetch builder creates validated commands with optional resolution', function () {
	$builder = new FetchCommandBuilder();

	expect($builder->build('/tmp/a file.rrd', 'max', -600, 0, 60)->arguments)
		->toBe(['/tmp/a file.rrd', 'MAX', '-s', '-600', '-e', '0', '-r', '60'])
		->and($builder->build('/tmp/a.rrd', 'malicious', 1, 2)->arguments)
		->toBe(['/tmp/a.rrd', 'AVERAGE', '-s', '1', '-e', '2']);
});

test('update builder controls skip-past-updates as a discrete argument', function () {
	$builder = new UpdateCommandBuilder();

	expect($builder->build('/tmp/a.rrd', 'in:out', '10:1:2', true)->arguments)
		->toBe(['/tmp/a.rrd', '--skip-past-updates', '--template', 'in:out', '10:1:2'])
		->and($builder->build('/tmp/a.rrd', 'in', 'N:U', false)->arguments)
		->toBe(['/tmp/a.rrd', '--template', 'in', 'N:U']);
});

test('tune builder omits empty settings and returns null when there is no work', function () {
	$builder = new TuneCommandBuilder();

	expect($builder->build('/tmp/a.rrd', []))->toBeNull();

	$command = $builder->build('/tmp/a.rrd', [
		'--heartbeat' => 'traffic_in:600',
		'--maximum'   => '',
		'--minimum'   => 'traffic_in:0',
	]);

	expect($command)->not->toBeNull()
		->and($command->arguments)->toBe([
			'/tmp/a.rrd',
			'--heartbeat',
			'traffic_in:600',
			'--minimum',
			'traffic_in:0',
		]);
});
