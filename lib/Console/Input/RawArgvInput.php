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

namespace Cacti\Console\Input;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

final class RawArgvInput extends ArgvInput {
	/** @var list<string> */
	private readonly array $rawTokens;

	/** @param list<string>|null $argv */
	public function __construct(?array $argv = null, ?InputDefinition $definition = null) {
		$argv ??= $_SERVER['argv'] ?? [];
		$this->rawTokens = array_values(array_slice($argv, 1));

		parent::__construct($argv, $definition);
	}

	/**
	 * @param  list<string|null> $names
	 * @return list<string>
	 */
	public function argumentsAfterCommand(array $names): array {
		foreach ($this->rawTokens as $offset => $token) {
			if (in_array($token, $names, true)) {
				return array_slice($this->rawTokens, $offset + 1);
			}
		}

		return [];
	}
}
