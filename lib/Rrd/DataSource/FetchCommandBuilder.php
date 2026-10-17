<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

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
