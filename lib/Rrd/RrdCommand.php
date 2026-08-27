<?php
declare(strict_types = 1);
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

namespace Cacti\Rrd;

use InvalidArgumentException;

/**
 * An RRDtool operation and its unescaped argument values.
 *
 * Keeping arguments separate allows local execution to bypass the shell. A
 * transport that uses RRDtool's line protocol can serialize the same command
 * with the quoting rules required by that protocol.
 */
final class RrdCommand {
	/**
	 * @param list<string> $arguments
	 */
	public function __construct(
		public readonly string $operation,
		public readonly array $arguments = [],
	) {
		$this->assertValidToken($operation, 'operation', false);

		if (!array_is_list($arguments)) {
			throw new InvalidArgumentException('RRDtool arguments must be a list.');
		}

		foreach ($arguments as $argument) {
			$this->assertValidToken($argument, 'argument', true);
		}
	}

	/**
	 * @param array<array-key, scalar|null> $tokens
	 */
	public static function fromList(array $tokens): self {
		$tokens = array_values(array_map(
			static fn (mixed $token): string => (string) $token,
			$tokens
		));

		$operation = array_shift($tokens);

		if ($operation === null) {
			throw new InvalidArgumentException('An RRDtool command requires an operation.');
		}

		return new self($operation, $tokens);
	}

	/**
	 * @return non-empty-list<string>
	 */
	public function toArgv(string $binary): array {
		$this->assertValidToken($binary, 'binary path', false);

		return [$binary, $this->operation, ...$this->arguments];
	}

	private function assertValidToken(string $token, string $description, bool $allowEmpty): void {
		if (!$allowEmpty && $token === '') {
			throw new InvalidArgumentException("RRDtool $description cannot be empty.");
		}

		if (!$allowEmpty && preg_match('/\s/', $token) === 1) {
			throw new InvalidArgumentException("RRDtool $description cannot contain whitespace.");
		}

		if (preg_match('/[\x00-\x1f\x7f]/', $token) === 1) {
			throw new InvalidArgumentException("RRDtool $description contains a control character.");
		}
	}
}
