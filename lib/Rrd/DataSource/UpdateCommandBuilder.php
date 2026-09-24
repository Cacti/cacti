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

final class UpdateCommandBuilder {
	public function build(string $path, string $template, string $values, bool $skipPastUpdates): RrdCommand {
		$arguments = [$path];

		if ($skipPastUpdates) {
			$arguments[] = '--skip-past-updates';
		}

		$arguments[] = '--template';
		$arguments[] = $template;
		$arguments[] = $values;

		return new RrdCommand('update', $arguments);
	}
}
