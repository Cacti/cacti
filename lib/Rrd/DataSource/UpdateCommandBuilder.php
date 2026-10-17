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
	public function build(string $path, string $template, string $values): RrdCommand {
		// Every supported RRDtool (1.5.0 and later) accepts --skip-past-updates.
		return new RrdCommand('update', [$path, '--skip-past-updates', '--template', $template, $values]);
	}
}
