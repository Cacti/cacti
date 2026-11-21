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
 * Builds the RRDtool tune command for one RRD file.
 */
final class TuneCommandBuilder {
	/**
	 * Build a tune from the options that carry a value.
	 *
	 * @param string                $path    the RRD file path, unescaped
	 * @param array<string, string> $options RRDtool option => unescaped value; empty values are skipped
	 *
	 * @return RrdCommand|null the tune command, or null when no option has a value
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
