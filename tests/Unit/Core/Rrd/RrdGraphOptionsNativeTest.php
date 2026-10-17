<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

test('native graph option construction preserves recorded commands', function () {
	global $config;

	$savedConfig   = $config;
	$savedSession  = $_SESSION;
	$savedTimezone = date_default_timezone_get();
	$savedDatechar = $GLOBALS['datechar'];

	try {
		date_default_timezone_set('UTC');
		$GLOBALS['datechar'] = [1 => '/'];
		$options             = ['rrdtool_watermark' => 'on', 'graph_watermark' => 'Cacti graph', 'graph_watermark_rrd' => '', 'default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'font_method' => 0];

		foreach (['title', 'axis', 'legend', 'unit', 'watermark'] as $type) {
			$options[$type . '_font'] = 'DejaVu Sans';
			$options[$type . '_size'] = 10;
		}

		$config[OPTIONS_CLI]    = array_replace($config[OPTIONS_CLI] ?? [], $options);
		$_SESSION[OPTIONS_WEB]  = array_replace($_SESSION[OPTIONS_WEB] ?? [], $options);
		$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];
		$version                = get_rrdtool_version();
		$fixtureVersion         = version_compare($version, '1.8', '>=') ? '1.8.0' : (version_compare($version, '1.4', '>=') ? '1.4.0' : '1.3.0');
		$cases                  = json_decode(file_get_contents(dirname(__DIR__, 3) . '/fixtures/rrd-graph-options/' . $fixtureVersion . '.json'), true, 512, JSON_THROW_ON_ERROR);

		foreach ($cases as $case) {
			$graph = $case['graph'];
			$data  = $case['options'];
			expect(rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data))->toBe($case['command']);
			expect($graph)->toBe($case['graph'])->and($data)->toBe($case['options']);
		}

		$graph   = array_replace($cases['autoscale']['graph'], ['base_value' => 1024, 'no_gridfit' => CHECKED, 'unit_length' => 8, 'tab_width' => 40, 'dynamic_labels' => CHECKED, 'force_rules_legend' => CHECKED]);
		$data    = array_replace($cases['autoscale']['options'], ['output_filename' => '/tmp/native graph.svg']);
		$command = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);

		foreach (["'/tmp/native graph.svg'", '--base=1024', '--no-gridfit', "--units-length '8'", "--tabwidth '40'", '--dynamic-labels', '--force-rules-legend'] as $option) {
			expect($command)->toContain($option);
		}
	} finally {
		$config              = $savedConfig;
		$_SESSION            = $savedSession;
		$GLOBALS['datechar'] = $savedDatechar;
		date_default_timezone_set($savedTimezone);
	}
});
