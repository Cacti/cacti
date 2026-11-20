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
*/

/**
 * Compile a byte-oriented, ASCII case-insensitive regex subset to a Thompson
 * NFA. Advance all reachable states together instead of backtracking.
 *
 * Accepted patterns also run as an equivalent PCRE under a match limit, which
 * is far faster than stepping the NFA in PHP. A line that reaches the limit is
 * decided by the NFA, so matching stays bounded without per-byte PHP work on
 * ordinary searches.
 *
 * Supports literals, ., ^, $, groups (including (?:...)), |, [], negated
 * classes, ranges, *, +, ?, and the escapes \d \w \s (and their inverses),
 * \n \r \t and escaped punctuation. Unsupported syntax is searched literally.
 */
final class CactiLogSearchPattern {
	/** Backtracking budget per line before the NFA decides the line instead. */
	const PCRE_MATCH_LIMIT = 50000;

	/** Lines allowed to reach the budget before this search stops trying PCRE. */
	const PCRE_FAILURE_LIMIT = 64;

	/** @var string */
	private $pattern;
	/** @var string|null */
	private $regex = null;
	/** @var int */
	private $pcreFailures = 0;
	/** @var int */
	private $cursor = 0;
	/** @var array<int, list<array{int, string, string}>> */
	private $edges = [];
	/** @var int */
	private $start;
	/** @var int */
	private $end;

	private function __construct(string $pattern) {
		$this->pattern = $pattern;
	}

	/** @return self|null */
	public static function compile(string $pattern) {
		// Plain text has a faster matcher, and the length cap bounds the NFA.
		if (strlen($pattern) > 50 || strpbrk($pattern, '.^$[]()|*+?{}\\') === false) {
			return null;
		}

		$compiled = new self($pattern);
		$fragment = $compiled->alternation();

		if ($fragment === null || $compiled->peek() !== '') {
			return null;
		}

		list($compiled->start, $compiled->end) = $fragment;
		$compiled->regex = self::pcre($pattern);

		return $compiled;
	}

	public function matches(string $line): bool {
		if ($this->regex !== null) {
			$result = preg_match($this->regex, $line);

			if ($result !== false) {
				return $result === 1;
			}

			if (++$this->pcreFailures >= self::PCRE_FAILURE_LIMIT) {
				$this->regex = null;
			}
		}

		return $this->nfaMatches($line);
	}

	/**
	 * Translate a pattern the parser accepted into the same PCRE. Each accepted
	 * construct has the same meaning to PCRE without the u modifier, so only the
	 * delimiter and NUL bytes need escaping.
	 */
	private static function pcre(string $pattern) {
		$regex  = '';
		$length = strlen($pattern);

		for ($offset = 0; $offset < $length; $offset++) {
			$char = $pattern[$offset];

			// The parser rejects a trailing backslash, so an escape always has a byte.
			if ($char === '\\') {
				$char   = $pattern[++$offset];
				$regex .= $char === "\0" ? '\\x00' : '\\' . $char;
			} elseif ($char === '/') {
				$regex .= '\\/';
			} elseif ($char === "\0") {
				$regex .= '\\x00';
			} else {
				$regex .= $char;
			}
		}

		// Compile once without the Cacti error handler, which would write a PCRE
		// warning into the log being searched. JIT is disabled because some PCRE2
		// JIT releases return wrong caseless alternation results; the PCRE 8.x
		// used by older PHP rejects (*NO_JIT), so retry without it. A pattern PCRE
		// rejects outright uses the NFA.
		set_error_handler(function () {
			return true;
		});

		$compiled = null;

		foreach (['(*NO_JIT)', ''] as $option) {
			$candidate = '/' . $option . '(*LIMIT_MATCH=' . self::PCRE_MATCH_LIMIT . ')' . $regex . '/i';

			if (preg_match($candidate, '') !== false) {
				$compiled = $candidate;

				break;
			}
		}

		restore_error_handler();

		return $compiled;
	}

	private function nfaMatches(string $line): bool {
		$length = strlen($line);
		$active = [];

		for ($offset = 0; $offset <= $length; $offset++) {
			$active[$this->start] = true;
			$pending              = array_keys($active);
			$next                 = [];

			while ($pending !== []) {
				$state = array_pop($pending);

				if ($state === $this->end) {
					return true;
				}

				foreach ($this->edges[$state] as list($target, $kind, $bytes)) {
					if ($kind === 'byte') {
						if ($offset < $length && $bytes[ord($line[$offset])] === "\1") {
							$next[$target] = true;
						}
					} elseif (!isset($active[$target]) && (
						$kind === 'empty'
						|| ($kind === 'start' && $offset === 0)
						|| ($kind === 'end' && ($offset === $length || ($offset === $length - 1 && $line[$offset] === "\n")))
					)) {
						$active[$target] = true;
						$pending[]       = $target;
					}
				}
			}

			$active = $next;
		}

		return false;
	}

	/** @return array{int, int}|null */
	private function alternation() {
		$first = $this->sequence();

		if ($first === null || $this->peek() !== '|') {
			return $first;
		}

		$start = $this->state();
		$end   = $this->state();
		$this->edge($start, $first[0]);
		$this->edge($first[1], $end);

		while ($this->peek() === '|') {
			$this->cursor++;
			$branch = $this->sequence();

			if ($branch === null) {
				return null;
			}

			$this->edge($start, $branch[0]);
			$this->edge($branch[1], $end);
		}

		return [$start, $end];
	}

	/** @return array{int, int}|null */
	private function sequence() {
		$start = $end = $this->state();

		while (!in_array($this->peek(), ['', '|', ')'], true)) {
			$char = $this->take();
			$atom = $this->atom($char);

			if ($atom === null) {
				return null;
			}

			list($from, $to) = $atom;
			$repeat      = $this->peek();

			if (in_array($repeat, ['*', '+', '?'], true)) {
				if ($char === '^' || $char === '$') {
					return null;
				}

				$this->cursor++;

				// Lazy and greedy forms are equivalent for a boolean match.
				if ($this->peek() === '?') {
					$this->cursor++;
				}

				$entry = $this->state();
				$exit  = $this->state();
				$this->edge($entry, $from);
				$this->edge($to, $exit);

				if ($repeat !== '+') {
					$this->edge($entry, $exit);
				}

				if ($repeat !== '?') {
					$this->edge($to, $from);
				}

				$from = $entry;
				$to   = $exit;
			}

			$this->edge($end, $from);
			$end = $to;
		}

		return [$start, $end];
	}

	/** @return array{int, int}|null */
	private function atom(string $char) {
		if ($char === '(') {
			if ($this->peek() === '?') {
				if (substr($this->pattern, $this->cursor, 2) !== '?:') {
					return null;
				}

				$this->cursor += 2;
			}

			$group = $this->alternation();

			return $this->take() === ')' ? $group : null;
		}

		$kind  = 'byte';
		$bytes = '';

		if ($char === '^' || $char === '$') {
			$kind = $char === '^' ? 'start' : 'end';
		} elseif ($char === '.') {
			$bytes     = str_repeat("\1", 256);
			$bytes[10] = "\0";
		} elseif ($char === '[') {
			$bytes = $this->characterClass();
		} elseif (strpos('*+?{}]', $char) !== false) {
			return null;
		} else {
			$token = $this->character($char);
			$bytes = $token === null ? null : $token[0];
		}

		if ($bytes === null) {
			return null;
		}

		$start = $this->state();
		$end   = $this->state();
		$this->edge($start, $end, $kind, $bytes);

		return [$start, $end];
	}

	/** @return string|null */
	private function characterClass() {
		$negate = $this->peek() === '^';

		if ($negate) {
			$this->cursor++;
		}

		$bytes = str_repeat("\0", 256);
		$first = true;

		while ($this->peek() !== '' && ($first || $this->peek() !== ']')) {
			$first = false;
			$char  = $this->take();

			// POSIX classes and nested set syntax are outside the subset.
			if ($char === '[') {
				return null;
			}

			$token = $this->character($char);

			if ($token === null) {
				return null;
			}

			list($set, $value) = $token;

			if ($this->peek() === '-' && !in_array($this->pattern[$this->cursor + 1] ?? '', ['', ']'], true)) {
				$this->cursor++;
				$last = $this->character($this->take());

				if ($value === null || $last === null || $last[1] === null || $value > $last[1]) {
					return null;
				}

				$set = $this->byteSet(range($value, $last[1]));
			}

			$bytes |= $set;
		}

		if ($this->take() !== ']') {
			return null;
		}

		return $negate ? $bytes ^ str_repeat("\1", 256) : $bytes;
	}

	/** @return array{string, int|null}|null */
	private function character(string $char) {
		if ($char === '\\') {
			$char  = $this->take();
			$lower = strtolower($char);

			if (in_array($lower, ['d', 's', 'w'], true)) {
				$digits = range(48, 57);
				$values = $lower === 'd' ? $digits : ($lower === 's' ? range(9, 13) : array_merge($digits, range(65, 90), [95]));

				if ($lower === 's') {
					$values[] = 32;
				}

				$set = $this->byteSet($values);

				return [$char === $lower ? $set : $set ^ str_repeat("\1", 256), null];
			}

			$escapes = ['n' => "\n", 'r' => "\r", 't' => "\t"];

			if (isset($escapes[$char])) {
				$char = $escapes[$char];
			} elseif ($char === '' || ctype_alnum($char)) {
				return null;
			}
		}

		$value = ord($char);

		return [$this->byteSet([$value]), $value];
	}

	/** @param list<int> $values */
	private function byteSet(array $values): string {
		$set = str_repeat("\0", 256);

		foreach ($values as $value) {
			$set[$value] = "\1";

			if (($value >= 65 && $value <= 90) || ($value >= 97 && $value <= 122)) {
				$set[$value ^ 32] = "\1";
			}
		}

		return $set;
	}

	private function state(): int {
		$state         = count($this->edges);
		$this->edges[] = [];

		return $state;
	}

	private function edge(int $from, int $to, string $kind = 'empty', string $bytes = '') {
		$this->edges[$from][] = [$to, $kind, $bytes];
	}

	private function peek(): string {
		return $this->pattern[$this->cursor] ?? '';
	}

	private function take(): string {
		return $this->pattern[$this->cursor++] ?? '';
	}
}
