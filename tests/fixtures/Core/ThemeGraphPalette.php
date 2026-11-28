<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';

// lib/rrd.php includes rrdtheme.php in function scope and passes $rrdcolors
// to rrdtool, so the default graph font must be readable on the canvas.
function theme_graph_palette(string $file) : array {
	$rrdcolors = [];

	include $file;

	return $rrdcolors;
}

function theme_graph_luminance(string $hex) : float {
	$channels = array_map(static function (string $pair) : float {
		$value = hexdec($pair) / 255;

		return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
	}, str_split(substr($hex, 0, 6), 2));

	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

$files = glob($root . '/include/themes/*/rrdtheme.php');

if ($files === false || count($files) < 9) {
	throw new RuntimeException('Theme graph palettes are missing');
}

foreach ($files as $file) {
	$colors = theme_graph_palette($file);
	$font   = theme_graph_luminance($colors['font'] ?? '');
	$canvas = theme_graph_luminance($colors['canvas'] ?? '');
	$ratio  = (max($font, $canvas) + 0.05) / (min($font, $canvas) + 0.05);

	if ($ratio < 4.5) {
		throw new RuntimeException(sprintf('%s graph font contrast is %.2f:1', basename(dirname($file)), $ratio));
	}
}
