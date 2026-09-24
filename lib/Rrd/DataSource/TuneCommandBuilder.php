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

final class TuneCommandBuilder {
	/**
	 * @param array<string, string> $options RRDtool option => unescaped value
	 */
	public function build(string $path, array $options): ?RrdCommand {
		$arguments = [$path];

		foreach ($options as $option => $value) {
			if ($value === '') {
				continue;
			}

			$arguments[] = $option;
			$arguments[] = $value;
		}

		return count($arguments) === 1 ? null : new RrdCommand('tune', $arguments);
	}
}
