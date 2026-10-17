<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Installer;

final readonly class UpgradePlan {
	/** @param list<string> $versions
	 * @param list<string> $templates
	 * @param list<string> $tables
	 * @param list<string> $plugins
	 * @param list<int>    $collectors
	 */
	public function __construct(public string $from, public string $to, array $versions,
		public array $templates = [], public array $tables = [], public array $plugins = [], public array $collectors = []) {
		$this->migrations = $from === 'new_install' ? [] : array_values(array_filter($versions,
			fn (string $version) : bool => version_compare($version, $from, '>') && version_compare($version, $to, '<=')));
	}

	/** @var list<string> */
	public array $migrations;

	public function encode() : string {
		return json_encode(get_object_vars($this), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
	}
}
