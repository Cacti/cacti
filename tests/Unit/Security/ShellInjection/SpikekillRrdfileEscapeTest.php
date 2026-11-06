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
 * GHSA-vhh2-gghg-whjg (confirmation): the spikekill batch poller concatenated the
 * data-source-controlled rrd_path into the removespikes.php command line
 * unescaped. The RRDfile path must be wrapped in cacti_escapeshellarg().
 * Fixed in #7235; this guards against regression.
 */

$spikekillSource = file_get_contents(dirname(__DIR__, 4) . '/poller_spikekill.php');

test('GHSA-vhh2: the spikekill poller escapes the RRDfile path', function () use ($spikekillSource) {
	expect(preg_match('~[\x27] --rrdfile=[\x27]\s*\.\s*cacti_escapeshellarg\(\$f\)~', $spikekillSource))->toBe(1)
		->and(preg_match('~[\x27] --rrdfile=[\x27]\s*\.\s*\$f\s*\.~', $spikekillSource))->toBe(0);
});
