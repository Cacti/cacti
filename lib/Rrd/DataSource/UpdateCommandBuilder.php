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

/**
 * Builds the RRDtool update command for one data source.
 */
final class UpdateCommandBuilder {
	/**
	 * Build an update that writes the given values through a data source template.
	 *
	 * Every supported RRDtool (1.5.0 and later) accepts --skip-past-updates, so a
	 * replayed batch is skipped rather than rejected.
	 *
	 * @param string $path     the RRD file path, unescaped
	 * @param string $template the colon-separated data source names
	 * @param string $values   the space-separated timestamp:value tuples
	 *
	 * @return RrdCommand the update command
	 */
	public function build(string $path, string $template, string $values): RrdCommand {
		return new RrdCommand('update', [$path, '--skip-past-updates', '--template', $template, $values]);
	}
}
