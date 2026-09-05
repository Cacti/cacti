<?php

namespace CsvWindowRrdIntegrationTest;

function csv_window_rrd_run(array $command) {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptors, $pipes);

	if (!is_resource($process)) {
		throw new \RuntimeException('Unable to start RRDtool.');
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	if ($status !== 0) {
		throw new \RuntimeException('RRDtool failed: ' . $stderr);
	}

	return $stdout;
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php');

if ($source === false || preg_match('/function rrdtool_parse_fetch_output\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract rrdtool_parse_fetch_output() for integration tests.');
}

eval('namespace CsvWindowRrdIntegrationTest;' . $matches[0]);

test('real RRDtool output is trimmed to the requested CSV window', function () {
	$binary = getenv('RRDTOOL_TEST_BINARY');

	if (!is_string($binary) || $binary == '' || !is_executable($binary)) {
		$this->markTestSkipped('Set RRDTOOL_TEST_BINARY to an executable RRDtool binary.');
	}

	$directory = sys_get_temp_dir() . '/cacti-csv-window-' . bin2hex(random_bytes(6));
	mkdir($directory, 0700, true);
	$rrd   = $directory . '/window.rrd';
	$start = 1700000100;
	$end   = $start + 900;

	try {
		csv_window_rrd_run(array(
			$binary, 'create', $rrd, '--start', (string) ($start - 300), '--step', '300',
			'DS:value:GAUGE:600:0:U', 'RRA:AVERAGE:0.5:1:20',
		));
		csv_window_rrd_run(array(
			$binary, 'update', $rrd, $start . ':1', ($start + 300) . ':2',
			($start + 600) . ':3', $end . ':4', ($end + 300) . ':5',
		));
		$raw = csv_window_rrd_run(array(
			$binary, 'fetch', $rrd, 'AVERAGE', '--start', (string) $start,
			'--end', (string) $end, '--resolution', '300',
		));
		$xport = csv_window_rrd_run(array(
			$binary, 'xport', '--start', (string) $start, '--end', (string) $end,
			'--maxrows', '10000', 'DEF:value=' . $rrd . ':value:AVERAGE:step=300', 'XPORT:value',
		));
		$fetch = rrdtool_parse_fetch_output($raw, $end, true);

		expect($fetch['timestamp']['end_time'])->toBeLessThanOrEqual($end)
			->and($fetch['timestamp']['step'])->toBe(300)
			->and(array_keys($fetch['values'][0]))->not->toContain($end + 300)
			->and($xport)->toContain('<step>300</step>');
	} finally {
		if (is_file($rrd)) {
			unlink($rrd);
		}

		if (is_dir($directory)) {
			rmdir($directory);
		}
	}
});
