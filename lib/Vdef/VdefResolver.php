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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Vdef;

use Closure;

require_once __DIR__ . '/VdefRepository.php';

/**
 * Resolves a VDEF and its nested type 5 references into the RPN string
 * that rrdtool and the VDEF editor display.
 */
final class VdefResolver {
	/** Nested VDEF references followed before resolution fails closed. */
	private const MAX_DEPTH = 64;

	/** VDEF expansions allowed per resolve() call, bounding shared-reference fan-out. */
	private const MAX_EXPANSIONS = 4096;

	/** @var Closure(string): mixed */
	private readonly Closure $log;

	/**
	 * @param VdefRepository                $repository Source of VDEF rows and item names.
	 * @param (Closure(string): mixed)|null $log        Error sink; defaults to cacti_log() when it is loaded.
	 */
	public function __construct(private readonly VdefRepository $repository, ?Closure $log = null) {
		// lib/Vdef can load without lib/functions.php, so only log when cacti_log() exists.
		$this->log = $log ?? static fn (string $message) => function_exists('cacti_log') ? \cacti_log($message, false, 'VDEF') : null;
	}

	/**
	 * Resolve a VDEF into its text form.
	 *
	 * Imported vdef_items rows are stored as given, so a type 5 item can name
	 * its own VDEF or start a cycle. Such definitions resolve to '' and are
	 * logged instead of recursing until the stack is exhausted.
	 *
	 * @param int  $vdef_id The VDEF to resolve.
	 * @param bool $display Separate items with ', ' for display instead of ','.
	 *
	 * @return string The resolved VDEF, or '' when it is empty, missing or invalid.
	 */
	public function resolve(int $vdef_id, bool $display = false) : string {
		$visited   = [];
		$expansion = 0;

		return $this->resolveNested($vdef_id, $display, $visited, $expansion) ?? '';
	}

	/**
	 * Resolve one VDEF while tracking the active reference chain.
	 *
	 * @param int              $vdef_id    The VDEF to resolve.
	 * @param bool             $display    Separate items with ', ' for display.
	 * @param array<int, true> &$visited   VDEF ids on the current reference chain.
	 * @param int              &$expansion VDEF expansions so far in this resolve() call.
	 *
	 * @return string|null The resolved VDEF, or null when a guard rejected it.
	 */
	private function resolveNested(int $vdef_id, bool $display, array &$visited, int &$expansion) : ?string {
		if (isset($visited[$vdef_id])) {
			($this->log)(sprintf('ERROR: VDEF %d contains a recursive cycle.', $vdef_id));

			return null;
		}

		if (count($visited) >= self::MAX_DEPTH) {
			($this->log)(sprintf('ERROR: VDEF %d exceeds the resolver nesting depth.', $vdef_id));

			return null;
		}

		if (++$expansion > self::MAX_EXPANSIONS) {
			($this->log)(sprintf('ERROR: VDEF %d exceeds the resolver expansion budget.', $vdef_id));

			return null;
		}

		$visited[$vdef_id] = true;
		$vdef_items        = $this->repository->items($vdef_id);

		$i           = 0;
		$vdef_string = '';

		if ($vdef_items !== false && count($vdef_items)) {
			foreach ($vdef_items as $vdef_item) {
				if ($i > 0) {
					$vdef_string .= ($display ? ', ' : ',');
				}

				if ($vdef_item['type'] == 5) {
					$nested = $this->resolveNested($vdef_item['value'], $display, $visited, $expansion);

					if ($nested === null) {
						return null;
					}

					$vdef_string .= $nested;
				} else {
					$vdef_string .= $this->repository->itemName($vdef_item['id']);
				}

				$i++;
			}
		}

		unset($visited[$vdef_id]);

		return $vdef_string;
	}
}
