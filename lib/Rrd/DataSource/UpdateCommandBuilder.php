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
