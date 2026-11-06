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

namespace Cacti\Http;

/**
 * Typed, validated access to the current HTTP request parameters.
 *
 * This is an opt-in, backward-compatible alternative to the global
 * get_filter_request_var()/get_nfilter_request_var() (gfrv/gnrv) helpers. Every
 * accessor returns a native, typed value and fails *closed* (returns the caller
 * supplied default) when the input is missing or does not satisfy the requested
 * validation. Nothing is written back to $_REQUEST/$_GET/$_POST, so a Request
 * instance can be created and read without mutating global state.
 *
 * Validation is performed with the same primitives Cacti's filters already use
 * (PHP filter_var() and preg_match()), so an accepted value is identical to the
 * value gfrv() would accept for the equivalent FILTER_* specification. The
 * difference is on the rejection path: where gfrv() emits a fatal input error
 * (die_html_input_error()), the typed accessor returns the default. Callers that
 * want the legacy fatal behaviour keep using gfrv(); callers that want a safe,
 * typed value opt into this class.
 *
 * Backing store:
 *   - When symfony/http-foundation is autoloaded (the normal web runtime), the
 *     parameters are taken from Request::createFromGlobals(); POST (request)
 *     parameters override GET (query) parameters, mirroring PHP's default
 *     $_REQUEST (GP) ordering.
 *   - When http-foundation is not autoloaded (degraded runtime, e.g. a partial
 *     vendor tree), the parameters are read straight from $_REQUEST with the
 *     identical PHP filters applied. A running site therefore never fatals on a
 *     missing optional dependency.
 */
final class Request {
	/**
	 * Flattened request parameters: a name => value map equivalent to $_REQUEST.
	 *
	 * @var array<string,mixed>
	 */
	private array $params;

	/** Whether the parameters were sourced from symfony/http-foundation. */
	private bool $symfonyBacked;

	/**
	 * @param array<string,mixed> $params        Flattened request parameters.
	 * @param bool                $symfonyBacked  True when sourced from http-foundation.
	 */
	private function __construct(array $params, bool $symfonyBacked) {
		$this->params        = $params;
		$this->symfonyBacked = $symfonyBacked;
	}

	/**
	 * Build a Request from the current PHP globals.
	 *
	 * Uses symfony/http-foundation when available, otherwise degrades gracefully
	 * to reading $_REQUEST directly.
	 *
	 * @return self
	 */
	public static function createFromGlobals() : self {
		$sfClass = 'Symfony\\Component\\HttpFoundation\\Request';

		if (class_exists($sfClass)) {
			$sf = $sfClass::createFromGlobals();

			// POST (request) overrides GET (query), mirroring PHP's default
			// $_REQUEST ordering. array union keeps left-hand (POST) keys.
			$params = $sf->request->all() + $sf->query->all();

			return new self($params, true);
		}

		// Graceful degradation: http-foundation is not autoloaded.
		return new self(isset($_REQUEST) && is_array($_REQUEST) ? $_REQUEST : [], false);
	}

	/**
	 * Build a Request from an explicit parameter map. Intended for tests and for
	 * callers that have already assembled a parameter array.
	 *
	 * @param array<string,mixed> $params
	 *
	 * @return self
	 */
	public static function fromArray(array $params) : self {
		return new self($params, false);
	}

	/**
	 * Whether this instance is backed by symfony/http-foundation (true) or the
	 * degraded $_REQUEST fallback (false).
	 *
	 * @return bool
	 */
	public function isSymfonyBacked() : bool {
		return $this->symfonyBacked;
	}

	/**
	 * Whether a parameter with the given name is present in the request.
	 *
	 * Equivalent to isset_request_var(): a present-but-null value is treated as
	 * absent (HTTP inputs are never null in practice).
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function has(string $name) : bool {
		return isset($this->params[$name]);
	}

	/**
	 * Return a validated integer, or $default when the parameter is missing or
	 * is not a valid integer.
	 *
	 * Parity: equivalent to gfrv($name, FILTER_VALIDATE_INT). Accepts the same
	 * values filter_var() accepts (including a leading sign and '0'); rejects
	 * non-numeric input, arrays and floats, returning $default instead of dying.
	 *
	 * @param string $name
	 * @param int    $default
	 *
	 * @return int
	 */
	public function int(string $name, int $default = 0) : int {
		if (!$this->has($name)) {
			return $default;
		}

		$value = $this->params[$name];

		if (is_array($value)) {
			return $default;
		}

		$filtered = filter_var($value, FILTER_VALIDATE_INT);

		return $filtered === false ? $default : $filtered;
	}

	/**
	 * Return a string value, optionally validated/sanitized, or $default when the
	 * parameter is missing or fails validation.
	 *
	 * The $filter argument mirrors Cacti's filter conventions:
	 *   - null                     : raw string, equivalent to gnrv($name).
	 *   - a delimited regex pattern : equivalent to FILTER_VALIDATE_REGEXP; the
	 *                                 value is returned only when it matches,
	 *                                 otherwise $default (e.g. '/^[a-zA-Z0-9_]+$/').
	 *   - a PHP FILTER_* int const  : passed to filter_var(); a FILTER_VALIDATE_*
	 *                                 failure returns $default, a FILTER_SANITIZE_*
	 *                                 returns the cleaned string.
	 *
	 * Array values always fail closed to $default.
	 *
	 * @param string          $name
	 * @param string          $default
	 * @param int|string|null $filter
	 *
	 * @return string
	 */
	public function string(string $name, string $default = '', int|string|null $filter = null) : string {
		if (!$this->has($name)) {
			return $default;
		}

		$value = $this->params[$name];

		if (is_array($value)) {
			return $default;
		}

		$value = (string) $value;

		if ($filter === null) {
			return $value;
		}

		if (is_string($filter)) {
			// A delimited regular expression, matching Cacti's
			// FILTER_VALIDATE_REGEXP usage. Fail closed on a non-match or a
			// malformed pattern.
			return @preg_match($filter, $value) === 1 ? $value : $default;
		}

		$result = filter_var($value, $filter);

		// FILTER_VALIDATE_* returns false on failure; FILTER_SANITIZE_* returns
		// the cleaned string. null is possible with FILTER_NULL_ON_FAILURE.
		if ($result === false || $result === null) {
			return $default;
		}

		return (string) $result;
	}

	/**
	 * Return a value constrained to an allowed set, or $default when the parameter
	 * is missing or outside the set.
	 *
	 * Comparison is performed on the string form of both the value and each
	 * allowed entry, so an integer allow-list and a string request value compare
	 * as expected. Array values fail closed to $default.
	 *
	 * @param string            $name
	 * @param array<int,scalar> $allowed
	 * @param string            $default
	 *
	 * @return string
	 */
	public function enum(string $name, array $allowed, string $default = '') : string {
		if (!$this->has($name)) {
			return $default;
		}

		$value = $this->params[$name];

		if (is_array($value)) {
			return $default;
		}

		$value = (string) $value;

		foreach ($allowed as $candidate) {
			if ((string) $candidate === $value) {
				return $value;
			}
		}

		return $default;
	}

	/**
	 * Return a validated boolean, or $default when the parameter is missing or not
	 * a recognised boolean.
	 *
	 * Parity: equivalent to gfrv($name, FILTER_VALIDATE_BOOLEAN). '1', 'true',
	 * 'on' and 'yes' are true; '0', 'false', 'off', 'no' and '' are false; any
	 * other value returns $default. Array values fail closed to $default.
	 *
	 * @param string $name
	 * @param bool   $default
	 *
	 * @return bool
	 */
	public function bool(string $name, bool $default = false) : bool {
		if (!$this->has($name)) {
			return $default;
		}

		$value = $this->params[$name];

		if (is_array($value)) {
			return $default;
		}

		$result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

		return $result === null ? $default : $result;
	}
}
