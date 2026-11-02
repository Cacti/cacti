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
	public function build(string $path, string $template, string $values): RrdCommand {
		// Every supported RRDtool (1.5.0 and later) accepts --skip-past-updates.
		return new RrdCommand('update', [$path, '--skip-past-updates', '--template', $template, $values]);
	}
}
