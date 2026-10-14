<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);

foreach (['cacti_sizeof', 'read_config_option', 'cacti_escapeshellcmd', 'cacti_escapeshellarg', 'cacti_log', 'get_selective_log_level'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
}

foreach (['__', '__esc', '__gettext', '__uf'] as $function) {
	cacti_fixture_source_function($root . '/include/global_languages.php', $function, '');
}
require_once $root . '/lib/spikekill.php';
define('CACTI_WEB', false);
define('CACTI_SERVER_OS', 'unix');
define('OPTIONS_CLI', 'options');
define('POLLER_LOG_LEVEL', 5);
define('POLLER_VERBOSITY_DEBUG', 5);
define('POLLER_VERBOSITY_DEVDBG', 6);
define('POLLER_VERBOSITY_LOW', 2);
$config = ['cacti_server_os' => 'unix', 'is_web' => false, 'options' => [], 'config_options_array' => []];

foreach (['options', 'config_options_array'] as $key) {
	$config[$key]['log_verbosity']   = 0;
	$config[$key]['selective_debug'] = '';
}
$directory = sys_get_temp_dir() . '/cacti-spike-matrix-' . bin2hex(random_bytes(6));
mkdir($directory);
register_shutdown_function(static function () use ($directory): void {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}
	rmdir($directory);
});
function spike_matrix_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
function spike_matrix_object(int $method = SPIKE_METHOD_STDDEV): spikekill {
	$spiker           = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$spiker->method   = $method;
	$spiker->avgnan   = 'avg';
	$spiker->numspike = 1;
	$spiker->stddev   = 1;
	$spiker->html     = false;

	if (property_exists(spikekill::class, 'percent')) {
		$spiker->percent  = 0.1;
		$spiker->outliers = 0;
	}

	foreach (['ds_name' => ['traffic_in', 'traffic_out'], 'ds_min' => ['NaN', 'NaN'], 'ds_max' => ['NaN', 'NaN']] as $name => $value) {
		(new ReflectionProperty(spikekill::class, $name))->setValue($spiker, $value);
	}

	return $spiker;
}
function spike_matrix_rows(spikekill $spiker, array $rows): array {
	$statistics = [[
		['average' => 10, 'variance_avg' => 10, 'min_cutoff' => 0, 'max_cutoff' => 20, 'outwind_killed' => 0, 'last' => 0],
		['average' => 10, 'variance_avg' => 10, 'min_cutoff' => 0, 'max_cutoff' => 20, 'outwind_killed' => 0, 'last' => 0],
	]];
	$statistics[] = $statistics[0];
	$xml          = [];

	foreach ($rows as $row) {
		if (is_string($row)) {
			$xml[] = $row;
		} else {
			$xml[] = '<row><timestamp>100</timestamp><v>' . $row[0] . '</v><v>' . $row[1] . '</v></row>';
		}
	}

	return (new ReflectionMethod(spikekill::class, 'updateXML'))->invokeArgs($spiker, [&$xml, &$statistics]);
}
$cases               = [];
$cases['rra-budget'] = static function (): void {
	$spiker = spike_matrix_object();
	$result = spike_matrix_rows($spiker, [[100, 100], [100, 100], '</database>', '</rra>', [100, 100], [100, 100]]);
	spike_matrix_assert(substr_count(implode('', $result), '1.0000000000e+1') === 2, 'Kill budget must reset only between RRAs');
};
$cases['nan-budget'] = static function (): void {
	$spiker         = spike_matrix_object();
	$spiker->avgnan = 'nan';
	$result         = spike_matrix_rows($spiker, [[100, 100], [100, 100]]);
	spike_matrix_assert(substr_count(implode('', $result), ' NaN') === 1, 'NaN replacements must consume the kill budget');
};
$cases['preserve-gaps'] = static function (): void {
	$result = spike_matrix_rows(spike_matrix_object(), [['NaN', 10], [100, 10]]);
	spike_matrix_assert(str_contains($result[0], '<v> NaN</v>') && str_contains($result[1], '1.0000000000e+1'), 'Unknown samples must not consume the kill budget');
};
$cases['zero-last'] = static function (): void {
	$spiker         = spike_matrix_object();
	$spiker->avgnan = 'last';
	$result         = spike_matrix_rows($spiker, [[0, 10], [100, 10]]);
	spike_matrix_assert(str_contains($result[1], '<v> 0</v>'), 'Zero must be a valid previous sample');
};
$cases['unknown-bounds'] = static function (): void {
	$spiker     = spike_matrix_object();
	$statistics = [[['sumofsamples' => 120, 'numsamples' => 3]]];
	$samples    = [[[10 => 10, 20 => 10, 30 => 'NaN', 40 => 100]]];
	(new ReflectionMethod(spikekill::class, 'calculateOverallStatistics'))->invokeArgs($spiker, [&$statistics, &$samples]);
	spike_matrix_assert(is_numeric($statistics[0][0]['min_cutoff']) && is_numeric($statistics[0][0]['max_cutoff']), 'Unknown RRD limits must not replace numeric cutoffs');
	spike_matrix_assert($statistics[0][0]['stddev_killed'] === 1, 'Missing samples must not count as threshold outliers');
};

if (property_exists(spikekill::class, 'dsfilter')) {
	foreach (['traffic_in', '0', ' traffic_in,missing ', 'traffic_i?', 'traffic_i*', 'missing', '1', 'traffic_*'] as $filter) {
		$cases['filter-' . $filter] = static function () use ($filter): void {
			$spiker           = spike_matrix_object();
			$spiker->dsfilter = $filter;
			$result           = spike_matrix_rows($spiker, [[100, 100]]);
			$expected         = $filter === 'missing' ? '<v> 100</v><v> 100</v>' : ($filter === '1' ? '<v> 100</v><v> 1.0000000000e+1</v>' : '<v> 1.0000000000e+1</v><v> 100</v>');
			spike_matrix_assert(str_contains($result[0], $expected), 'Data-source filter selected the wrong DS: ' . $filter);
		};
	}
	$cases['filter-statistics'] = static function (): void {
		$spiker           = spike_matrix_object();
		$spiker->dsfilter = 'traffic_in';
		$statistics       = [[['sumofsamples' => 20, 'numsamples' => 2], ['sumofsamples' => 20, 'numsamples' => 2]]];
		$samples          = [[[100 => 100, 200 => 10], [100 => 100, 200 => 10]]];
		(new ReflectionMethod(spikekill::class, 'calculateOverallStatistics'))->invokeArgs($spiker, [&$statistics, &$samples]);
		spike_matrix_assert($statistics[0][1]['stddev_killed'] === 0, 'Excluded DS must not report kills');
	};
	$cases['absolute-gaps'] = static function (): void {
		$spiker            = spike_matrix_object(SPIKE_METHOD_ABSOLUTE);
		$spiker->out_start = 1;
		$spiker->out_end   = 200;
		$spiker->absmax    = 20;
		$result            = spike_matrix_rows($spiker, [['NaN', 20], [100, 10]]);
		spike_matrix_assert(str_contains($result[0], '<v> NaN</v><v> 20</v>'), 'Absolute mode must preserve unknown and boundary samples');
	};
	$cases['gap-fill-zero'] = static function (): void {
		$spiker            = spike_matrix_object(SPIKE_METHOD_FILL);
		$spiker->out_start = 1;
		$spiker->out_end   = 200;
		$result            = spike_matrix_rows($spiker, [[0, 'NaN']]);
		spike_matrix_assert(str_contains($result[0], '<v> 0</v><v> 1.0000000000e+1</v>'), 'NaN-only gap filling must preserve real zeros');
	};
} else {
	$cases['variance-nan-budget'] = static function (): void {
		$spiker           = spike_matrix_object(SPIKE_METHOD_VARIANCE);
		$spiker->avgnan   = 'nan';
		$spiker->percent  = 0.1;
		$spiker->outliers = 0;
		$result           = spike_matrix_rows($spiker, [['NaN', 100], [100, 100]]);
		spike_matrix_assert(substr_count(implode('', $result), ' NaN') === 2, 'Variance mode must preserve the existing gap and replace only one outlier');
	};
}


$thresholdMethod = defined('SPIKE_METHOD_ABSOLUTE') ? SPIKE_METHOD_ABSOLUTE : SPIKE_METHOD_VARIANCE;
$cases['threshold-statistics'] = static function () use ($thresholdMethod): void {
	$spiker = spike_matrix_object($thresholdMethod);
	$spiker->out_start = 1;
	$spiker->out_end = 200;
	if (property_exists(spikekill::class, 'absmax')) {
		$spiker->absmax = 20;
	}
	$statistics = [[['sumofsamples' => 30, 'numsamples' => 2, 'variance_avg' => 10]]];
	$samples = [[[50 => 'NaN', 100 => 100, 150 => 10]]];
	(new ReflectionMethod(spikekill::class, 'calculateOverallStatistics'))->invokeArgs($spiker, [&$statistics, &$samples]);
	spike_matrix_assert($statistics[0][0]['outwind_killed'] === 1, 'Threshold statistics must exclude unknown samples');
};
if (defined('SPIKE_METHOD_VARIANCE')) {
	$cases['variance-zero-last'] = static function (): void {
		$spiker = spike_matrix_object(SPIKE_METHOD_VARIANCE);
		$spiker->avgnan = 'last';
		$result = spike_matrix_rows($spiker, [[0, 10], [100, 10]]);
		spike_matrix_assert(str_contains($result[1], '<v> 0</v>'), 'Variance last-value repair must accept zero');
	};
}
foreach (['success' => "exit 0\n", 'stderr' => "printf '%s\\n' '<restore-failure>' >&2\nexit 17\n", 'silent' => "exit 17\n"] as $mode => $body) {
	$cases['restore-' . $mode] = static function () use ($mode, $body, $directory): void {
		global $config;
		$tool = $directory . '/rrd tool.sh';
		file_put_contents($tool, "#!/bin/sh\n" . $body);
		chmod($tool, 0700);
		// Quote the executable too: this tests real shell execution with a spaced path.
		$config['options']['path_rrdtool']              = escapeshellarg($tool);
		$config['config_options_array']['path_rrdtool'] = escapeshellarg($tool);
		$spiker                                         = spike_matrix_object();
		$spiker->html                                   = true;
		$result                                         = (new ReflectionMethod(spikekill::class, 'createRRDFileFromXML'))->invoke($spiker, $directory . '/input.xml', $directory . '/output.rrd');
		spike_matrix_assert($result === ($mode === 'success'), 'Restore status ignored');
		spike_matrix_assert(($spiker->get_errors() === '') === ($mode === 'success'), 'Restore failure missing from error channel');

		if ($mode === 'stderr') {
			spike_matrix_assert(str_contains($spiker->get_errors(), '&lt;restore-failure&gt;'), 'Command diagnostics must be escaped for HTML');
		}
	};
}

// Compile unchanged production blocks with original line numbers. The wrapper
// supplies locals only; writes, backups, command execution and errors are real.
function spike_matrix_block(string $file, int $offset, string $body, spikekill $spiker, string $parameters, string $setup = '', ?int $returnLine = null): Closure {
	$source    = file_get_contents($file);
	$line      = substr_count(substr($source, 0, $offset), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-spike-block-');
	$compiled  = '<?php return function (' . $parameters . ') {' . $setup . str_repeat("\n", $line - 1) . $body;

	if ($returnLine !== null) {
		$compiled .= str_repeat("\n", $returnLine - substr_count($compiled, "\n") - 1) . 'return !$this->is_error_set();';
	}
	file_put_contents($temporary, $compiled . "\n};");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		return (require $temporary)->bindTo($spiker, spikekill::class);
	} finally {
		unlink($temporary);
	}
}

foreach (['write', 'backup', 'restore', 'success'] as $mode) {
	$cases['dispatch-' . $mode] = static function () use ($mode, $root, $directory): void {
		global $config;
		$spiker          = spike_matrix_object();
		$spiker->rrdfile = $directory . '/source.rrd';
		file_put_contents($spiker->rrdfile, 'original-data');
		$tool = $directory . '/dispatch.sh';
		file_put_contents($tool, "#!/bin/sh\nexit " . ($mode === 'restore' ? '17' : '0') . "\n");
		chmod($tool, 0700);

		foreach (['options', 'config_options_array'] as $key) {
			$config[$key]['path_rrdtool']        = escapeshellarg($tool);
			$config[$key]['spikekill_backupdir'] = $mode === 'backup' ? $directory . '/missing' : $directory;
		}
		$file       = $root . '/lib/spikekill.php';
		$source     = file_get_contents($file);
		$offset     = strpos($source, "\t\tif (!\$this->dryrun) {\n\t\t\tif (\$continue)");
		$end        = strpos($source, "\n\t\t\$this->strout .=", $offset + 1);
		$body       = substr($source, $offset, $end - $offset);
		$returnLine = substr_count(substr($source, 0, strpos($source, 'return !$this->is_error_set();', $end)), "\n") + 1;
		$probe      = spike_matrix_block($file, $offset, $body, $spiker, '$xmlfile', '$continue = true; $output = true; $new_output = ["<rrd/>"]; $total = 0;', $returnLine);
		$result     = $probe($mode === 'write' ? $directory . '/missing/dump.xml' : $directory . '/dump.xml');
		spike_matrix_assert($result === ($mode === 'success'), 'Mutation failure must propagate to the caller: ' . $mode);
		spike_matrix_assert(($spiker->get_errors() === '') === ($mode === 'success'), 'Mutation error channel is empty: ' . $mode);
		spike_matrix_assert(str_contains($spiker->get_output(), 'Spikes Found and Remediated') === ($mode === 'success'), 'Failed mutation must not announce remediation');
	};
}
$cases['combined-results'] = static function () use ($root): void {
	$file   = $root . '/spikekill.php';
	$source = file_get_contents($file);
	preg_match('/\$results \.=[^\n]+;/', $source, $match, PREG_OFFSET_CAPTURE);
	$spiker = spike_matrix_object();
	(new ReflectionProperty(spikekill::class, 'errors'))->setValue($spiker, ['first-rrd-failed']);
	(new ReflectionProperty(spikekill::class, 'strout'))->setValue($spiker, 'second-rrd-succeeded');
	$probe   = spike_matrix_block($file, $match[0][1], $match[0][0], $spiker, '$result, $spiker, &$results');
	$results = '';
	$probe(false, $spiker, $results);
	$probe(true, $spiker, $results);
	spike_matrix_assert($results === "first-rrd-failed\nsecond-rrd-succeeded", 'Later RRD success must not hide an earlier error');
};

foreach (['success', 'empty', 'failure'] as $mode) {
	$cases['dump-' . $mode] = static function () use ($mode, $root, $directory): void {
		global $config;
		$tool = $directory . '/dump.sh';
		file_put_contents($tool, "#!/bin/sh\n" . ($mode === 'empty' ? '' : "printf '%s\\n' '<rrd/>'\n") . 'exit ' . ($mode === 'failure' ? '17' : '0') . "\n");
		chmod($tool, 0700);

		foreach (['options', 'config_options_array'] as $key) {
			$config[$key]['path_rrdtool'] = escapeshellarg($tool);
		}
		$spiker          = spike_matrix_object();
		$spiker->rrdfile = $directory . '/source.rrd';
		$file            = $root . '/lib/spikekill.php';
		$source          = file_get_contents($file);
		$offset          = strpos($source, '$dump_output = [];');
		$end             = strpos($source, '// backup the rrdfile', $offset);

		if ($end === false) {
			$end = strpos($source, '/* backup the rrdfile', $offset);
		}
		spike_matrix_assert($end !== false, 'Missing dump block');
		$body = substr($source, $offset, $end - $offset);
		// The application logger is outside this filesystem/command test. Keep its
		// line blank instead of replacing the logger with a process-global stub.
		$body       = preg_replace('/cacti_log\([^\n]*;/', '', $body);
		$returnLine = substr_count(substr($source, 0, strpos($source, 'return !$this->is_error_set();', $end)), "\n") + 1;
		$probe      = spike_matrix_block($file, $offset, $body, $spiker, '$xmlfile', '', $returnLine);
		$xmlfile    = $directory . '/probe-dump.xml';
		spike_matrix_assert($probe($xmlfile) === ($mode === 'success'), 'Empty or failed dump accepted');
		spike_matrix_assert(!file_exists($xmlfile), 'Dump artifact was not cleaned up');
	};
}

foreach (['success', 'restore-failure', 'no-repairs'] as $mode) {
	$cases['full-mutation-' . $mode] = static function () use ($mode, $root, $directory): void {
		global $config;
		$xml = "<rrd>\n<step>300</step>\n<ds>\n<name>traffic_in</name>\n<min>NaN</min>\n<max>NaN</max>\n</ds>\n<rra>\n<cf>AVERAGE</cf>\n<pdp_per_row>1</pdp_per_row>\n<database>\n";

		foreach ([10, 10, $mode === 'no-repairs' ? 'NaN' : 1000] as $index => $value) {
			$xml .= '<!-- date / ' . (100 + $index) . ' --> <row><v>' . $value . "</v></row>\n";
		}
		$xml .= "</database>\n</rra>\n</rrd>\n";
		file_put_contents($directory . '/fixture.xml', $xml);
		$tool = $directory . '/full.sh';
		file_put_contents($tool, "#!/bin/sh\nif [ \"\$1\" = dump ]; then cat " . escapeshellarg($directory . '/fixture.xml') . "; exit 0; fi\nexit " . ($mode === 'restore-failure' ? '17' : '0') . "\n");
		chmod($tool, 0700);

		foreach (['options', 'config_options_array'] as $key) {
			$config[$key]['path_rrdtool']        = escapeshellarg($tool);
			$config[$key]['spikekill_backupdir'] = $directory;
		}
		$spiker          = spike_matrix_object();
		$spiker->rrdfile = $directory . '/original.rrd';
		file_put_contents($spiker->rrdfile, 'original-data');
		$file   = $root . '/lib/spikekill.php';
		$source = file_get_contents($file);
		preg_match('/public function remove_spikes\(\)[^\n]*\{(.*?)\n\t\}/s', $source, $match, PREG_OFFSET_CAPTURE);
		$body = $match[1][0];
		// Supply valid initialized fields without connecting to the application DB.
		// Everything after initialization runs from the actual production method.
		$body   = preg_replace('/\$this->initialize(?:Spikekill|_spikekill)\(\);/', '', $body);
		$body   = preg_replace('/cacti_log\([^\n]*;/', '', $body);
		$probe  = spike_matrix_block($file, $match[1][1], $body, $spiker, '');
		$result = $probe();
		spike_matrix_assert($result === ($mode !== 'restore-failure'), 'Complete mutation swallowed restore error');
		spike_matrix_assert(($spiker->get_errors() === '') === ($mode !== 'restore-failure'), 'Complete mutation has wrong error state');
	};
}

foreach (defined('SPIKE_METHOD_VARIANCE') ? [SPIKE_METHOD_STDDEV, SPIKE_METHOD_VARIANCE] : [SPIKE_METHOD_STDDEV] as $method) {
	$cases['window-zero-last-' . $method] = static function () use ($method): void {
		$spiker = spike_matrix_object($method);
		$spiker->avgnan = 'last';
		$spiker->out_start = 100;
		$spiker->out_end = 200;
		$result = spike_matrix_rows($spiker, ['<row><timestamp>50</timestamp><v>0</v><v>10</v></row>', [100, 10]]);
		spike_matrix_assert(str_contains($result[1], '<v> 0</v>'), 'Window repair must accept a previous zero outside the window');
	};
}
if (property_exists(spikekill::class, 'dsfilter')) {
	$cases['gap-fill-last-zero'] = static function (): void {
		$spiker = spike_matrix_object(SPIKE_METHOD_FILL);
		$spiker->avgnan = 'last';
		$spiker->out_start = 1;
		$spiker->out_end = 200;
		$result = spike_matrix_rows($spiker, [[0, 'NaN']]);
		spike_matrix_assert(str_contains($result[0], '<v> 0</v><v> 0</v>'), 'Gap filling must retain real zero and replace only the missing sample');
	};
}
$requested = $argv[1] ?? 'all';
$passed    = [];

foreach ($cases as $name => $case) {
	if ($requested === 'all' || $requested === $name) {
		$case();
		$passed[] = $name;
	}
}
spike_matrix_assert($passed !== [], 'Unknown test case');
print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
