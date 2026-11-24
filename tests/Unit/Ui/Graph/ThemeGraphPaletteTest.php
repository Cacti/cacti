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

/*
 * Graph text drawn with a theme's default rrdtool palette must stay readable
 * on that palette's canvas. lib/rrd.php includes rrdtheme.php the same way, in
 * function scope, and passes $rrdcolors to rrdtool as --color options.
 */

function theme_graph_palette(string $theme): array {
	$rrdcolors = [];

	include dirname(__DIR__, 4) . '/include/themes/' . $theme . '/rrdtheme.php';

	return $rrdcolors;
}

function theme_graph_luminance(string $hex): float {
	$channels = array_map(static function (string $pair): float {
		$value = hexdec($pair) / 255;

		return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
	}, str_split(substr($hex, 0, 6), 2));

	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

dataset('rrd_themes', array_map('basename', array_map('dirname', glob(dirname(__DIR__, 4) . '/include/themes/*/rrdtheme.php'))));

test('the default graph font is readable on the canvas', function (string $theme): void {
	$colors = theme_graph_palette($theme);

	expect($colors)->toHaveKeys(['font', 'canvas']);

	$font   = theme_graph_luminance($colors['font']);
	$canvas = theme_graph_luminance($colors['canvas']);
	$ratio  = (max($font, $canvas) + 0.05) / (min($font, $canvas) + 0.05);

	expect($ratio)->toBeGreaterThanOrEqual(4.5);
})->with('rrd_themes');
