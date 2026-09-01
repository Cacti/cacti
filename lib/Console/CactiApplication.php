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

namespace Cacti\Console;

use Cacti\Console\Command\LegacyScriptCommand;
use Symfony\Component\Console\Application;

final class CactiApplication extends Application {
	public function __construct(private readonly string $root) {
		parent::__construct('Cacti', $this->readVersion());

		foreach (LegacyCommandMap::commands() as $script => $command) {
			$this->add(new LegacyScriptCommand($command, $script, $this->root));
		}
	}

	private function readVersion(): string {
		$version = @file_get_contents($this->root . '/include/cacti_version');

		return $version === false ? 'unknown' : trim($version);
	}
}
