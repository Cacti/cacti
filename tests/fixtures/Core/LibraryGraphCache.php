<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Cache lookup returns exact image bytes and rejects expired or mismatched renders. */
function library_graph_cache(int $graph, array $options): void {
	global $graph_data_array, $_CACTI_REQUEST;
	require_once CACTI_PATH_LIBRARY . '/boost.php';
	$state     = [$graph_data_array ?? [], $_CACTI_REQUEST ?? [], $_REQUEST, $_SESSION];
	$directory = sys_get_temp_dir() . '/coverage-images-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Graph cache fixture owns its directory');
	$settings = [];

	foreach (['boost_png_cache_enable' => 'on', 'boost_png_cache_directory' => $directory, 'boost_rrd_update_enable' => '', 'business_hours_enable' => '', 'poller_interval' => '300'] as $key => $value) {
		$settings[$key] = (string) read_config_option($key);
		set_config_option($key, $value);
	}

	try {
		$_REQUEST       = [];
		$_CACTI_REQUEST = [];
		srv('action', 'view');
		unset($_SESSION['custom'], $_SESSION[OPTIONS_WEB]['thold_draw_vrules']);
		$_SESSION['sess_current_timespan'] = 0;
		$graph_data_array                  = $options;
		$image                             = rrdtool_function_graph($graph, '', $options);
		library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'Cache fixture renders a real graph image');
		$graph_data_array = $options;
		boost_graph_set_file($image, $graph, 0);
		$path = boost_graph_cache_filename($directory, $graph, 0, 0, '', $options);
		library_expect(file_get_contents($path), $image, 'Cache stores the exact rendered image bytes');
		library_expect(boost_graph_cache_check($graph, 0, null, $options), $image, 'Matching render receives the cached image');
		$changed                = $options;
		$changed['graph_width'] = 777;
		library_expect(boost_graph_cache_check($graph, 0, null, $changed), false, 'Different render dimensions cannot reuse the cached image');
		library_expect(touch($path, time() - 600), true, 'Fixture ages only its own cache file');
		clearstatcache(true, $path);
		library_expect(boost_graph_cache_check($graph, 0, null, $options), false, 'Expired image is rejected');
		library_expect(file_get_contents($path), $image, 'Rejected lookup does not corrupt stored bytes');
	} finally {
		foreach ($settings as $key => $value) {
			set_config_option($key, $value);
		}
		[$graph_data_array, $_CACTI_REQUEST, $_REQUEST, $_SESSION] = $state;

		foreach (glob($directory . '/*') as $path) {
			unlink($path);
		}
		rmdir($directory);
	}
}
