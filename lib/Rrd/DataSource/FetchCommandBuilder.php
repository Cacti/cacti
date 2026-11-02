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

declare(strict_types = 1);

namespace Cacti\Rrd\DataSource;

use Cacti\Rrd\RrdCommand;

final class FetchCommandBuilder {
	private const CONSOLIDATION_FUNCTIONS = ['AVERAGE', 'MIN', 'MAX', 'LAST'];

	public function build(string $path, string $consolidationFunction, int $start, int $end, int $resolution = 0): RrdCommand {
		$cf = strtoupper($consolidationFunction);

		if (!in_array($cf, self::CONSOLIDATION_FUNCTIONS, true)) {
			$cf = 'AVERAGE';
		}

		$arguments = [$path, $cf, '-s', (string) $start, '-e', (string) $end];

		if ($resolution > 0) {
			$arguments[] = '-r';
			$arguments[] = (string) $resolution;
		}

		return new RrdCommand('fetch', $arguments);
	}
}
