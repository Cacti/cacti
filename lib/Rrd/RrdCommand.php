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
 * Keeping arguments separate until serialization lets a transport that uses
 * RRDtool's line protocol apply the quoting rules required by that protocol.
 */
final class RrdCommand {
	/**
	 * Create a command, rejecting control characters in every token.
	 *
	 * @param string       $operation the RRDtool operation, such as update or fetch
	 * @param list<string> $arguments the unescaped argument values
	 *
	 * @throws InvalidArgumentException when a token is empty, malformed or contains a control character
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
	 * Create a command from a flat token list whose first token is the operation.
	 *
	 * @param array<array-key, scalar|null> $tokens the operation followed by its arguments
	 *
	 * @throws InvalidArgumentException when the list is empty or a token is invalid
	 *
	 * @return self the command
	 */
	public static function fromList(array $tokens): self {
		$tokens = array_values(array_map(static fn (mixed $token): string => (string) $token, $tokens));

		$operation = array_shift($tokens);

		if ($operation === null) {
			throw new InvalidArgumentException('An RRDtool command requires an operation.');
		}

		return new self($operation, $tokens);
	}

	/**
	 * Reject a token that RRDtool's argument or line protocol cannot carry safely.
	 *
	 * @param string $token       the token to check
	 * @param string $description the token's role, for the exception message
	 * @param bool   $allowEmpty  whether an empty token is valid; it also permits whitespace
	 *
	 * @throws InvalidArgumentException when the token is not allowed
	 */
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
