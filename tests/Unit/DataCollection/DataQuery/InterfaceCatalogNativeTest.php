<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

use Symfony\Component\Process\Process;

test('interface catalog keeps native importer identities and supported customization policy', function (): void {
	if (getenv('CACTI_INTERFACE_CATALOG_REAL_DB') !== '1') {
		$this->markTestSkipped('Set CACTI_INTERFACE_CATALOG_REAL_DB=1 with disposable CACTI_TEST_DB_* settings.');
	}
	$process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/interface_catalog_native.php']);
	$process->setTimeout(60);
	$process->mustRun();
	expect($process->getErrorOutput())->toBe('');
	$report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
	expect($report['checks'])->toHaveCount(9);

	foreach ($report['checks'] as $passed) {
		expect($passed)->toBeTrue();
	}
});

test('the shipped interface template retains native COUNTER updates wraps and reset ambiguity', function (): void {
	$root   = dirname(__DIR__, 4);
	$binary = getenv('RRDTOOL_TEST_BINARY') ?: '/usr/bin/rrdtool';

	if (!is_executable($binary)) {
		$this->markTestSkipped('Real RRDtool is required; CI provisions it.');
	}
	$package = simplexml_load_string(gzdecode(file_get_contents($root . '/install/templates/Generic_SNMP_Device.xml.gz')));
	expect($package)->not->toBeFalse();
	$template = null;

	foreach ($package->files->file as $file) {
		if ((string) $file->name === 'Generic_SNMP_Device.xml') {
			$template = simplexml_load_string(base64_decode((string) $file->data, true));
		}
	}
	expect($template)->toBeInstanceOf(SimpleXMLElement::class);
	$traffic = $template->xpath('hash_0101046632e1e0b58a565c135d7ff90440c335');
	expect($traffic)->toHaveCount(1);
	$definition = $traffic[0];
	$item       = $definition->items->children()[0];
	expect((string) $item->data_source_type_id)->toBe('2');
	expect((string) $item->rrd_maximum)->toBe('|query_ifSpeed|');
	$step = (int) $definition->ds->rrd_step;
	expect($step)->toBeGreaterThan(0);
	// Match the existing numeric ifSpeed substitution, including its current units.
	$dataSource = [
		'rrd_minimum'   => (string) $item->rrd_minimum,
		'rrd_maximum'   => '1000000000',
		'rrd_heartbeat' => (string) $item->rrd_heartbeat,
	];
	require_once $root . '/lib/rrd.php';
	include $root . '/include/global_arrays.php';
	$type = $data_source_types[(int) $item->data_source_type_id];
	expect($type)->toBe('COUNTER');
	$ds = rrdtool_tune_ds_add_spec('traffic_in', $type, $dataSource['rrd_heartbeat'], $dataSource['rrd_minimum'], $dataSource['rrd_maximum']);
	expect($ds)->toBe('DS:traffic_in:COUNTER:' . $item->rrd_heartbeat . ':0:1000000000');
	$directory = realpath(sys_get_temp_dir()) . '/interface-catalog-counter-' . bin2hex(random_bytes(8));
	expect(mkdir($directory, 0700))->toBeTrue();
	$path = $directory . '/traffic.rrd';
	$run  = static function (array $arguments) use ($binary): string {
		$process = new Process(array_merge([$binary], $arguments), env: ['RRDCACHED_ADDRESS' => false]);
		$process->setTimeout(5);
		$process->mustRun();
		expect($process->getErrorOutput())->toBe('');

		return $process->getOutput();
	};

	try {
		expect(file_exists($path) || is_link($path))->toBeFalse();
		$start = 1700000040; // Divisible by the shipped 60-second step.
		$run(['create', $path, '--start', (string) $start, '--step', (string) $step, $ds, 'RRA:AVERAGE:0.5:1:30']);
		// Exact integer deltas above 2^53, valid 32-bit wrap, reset ambiguity and explicit outage.
		$samples = ['9007199254740993', '9007199254741053', '0', '60', '4294967295', '59', '120', '0', 'U', '60', '120'];

		foreach ($samples as $offset => $sample) {
			$run(['update', $path, ($start + ($offset + 1) * $step) . ':' . $sample]);
		}
		$fetch = $run(['fetch', $path, 'AVERAGE', '--start', (string) $start, '--end', (string) ($start + count($samples) * $step)]);
		$rows  = [];

		foreach (explode("\n", $fetch) as $line) {
			if (preg_match('/^([0-9]+):\s+(\S+)$/D', $line, $match)) {
				$rows[(int) $match[1]] = strtolower($match[2]);
			}
		}
		expect((float) $rows[$start + 2 * $step])->toBe(1.0);
		expect($rows[$start + 3 * $step])->toMatch('/^-?nan$/');
		expect((float) $rows[$start + 4 * $step])->toBe(1.0);
		// RRDtool's existing COUNTER correction adds 2^32-1 to this negative delta.
		expect(abs((float) $rows[$start + 6 * $step] - 59 / $step))->toBeLessThan(1e-10);
		// COUNTER cannot distinguish this reset from a wrap below the configured maximum.
		expect((float) $rows[$start + 8 * $step])->toBeGreaterThan(1000000.0);
		expect($rows[$start + 9 * $step])->toMatch('/^-?nan$/');
		expect($rows[$start + 10 * $step])->toMatch('/^-?nan$/');
		expect((float) $rows[$start + 11 * $step])->toBe(1.0);
	} finally {
		if (is_file($path)) {
			unlink($path);
		}
		rmdir($directory);
	}
});
