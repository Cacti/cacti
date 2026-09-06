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
		/* Symfony resolves abbreviated command names, so the typed token is
		 * often neither the registered name nor an alias. Without the token it
		 * actually consumed, an abbreviation matches nothing and the legacy
		 * script is spawned with an empty argv, silently taking its
		 * no-argument path. */
		$names[] = $this->getFirstArgument();

		foreach ($this->rawTokens as $offset => $token) {
			if (in_array($token, $names, true)) {
				/* Drop the command token, keep everything else. getFirstArgument()
				 * skips leading options, so 'bin/cacti --help poller:rebuild-cache'
				 * dispatches with the flag ahead of the name; slicing only forward
				 * handed the script an empty argv and it rebuilt every cache. */
				return array_merge(
					array_slice($this->rawTokens, 0, $offset),
					array_slice($this->rawTokens, $offset + 1)
				);
			}
		}

		throw new \LogicException('The resolved command token is missing from the raw argument vector.');
	}
}
