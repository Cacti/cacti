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
 * Supports literals, ., ^, $, groups (including (?:...)), |, [], negated
 * classes, ranges, *, +, ?, and the escapes \d \w \s (and their inverses),
 * \n \r \t and escaped punctuation. Unsupported syntax is searched literally.
 */
final class CactiLogSearchPattern {
	private string $pattern;
	private int $cursor = 0;
	/** @var array<int, list<array{int, string, string}>> */
	private array $edges = [];
	private int $start;
	private int $end;

	private function __construct(string $pattern) {
		$this->pattern = $pattern;
	}

	public static function compile(string $pattern): ?self {
		// Plain text has a faster matcher, and the length cap bounds the NFA.
		if (strlen($pattern) > 50 || strpbrk($pattern, '.^$[]()|*+?{}\\') === false) {
			return null;
		}

		$compiled = new self($pattern);
		$fragment = $compiled->alternation();

		if ($fragment === null || $compiled->peek() !== '') {
			return null;
		}

		[$compiled->start, $compiled->end] = $fragment;

		return $compiled;
	}

	public function matches(string $line): bool {
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

				foreach ($this->edges[$state] as [$target, $kind, $bytes]) {
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
	private function alternation(): ?array {
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
	private function sequence(): ?array {
		$start = $end = $this->state();

		while (!in_array($this->peek(), ['', '|', ')'], true)) {
			$char = $this->take();
			$atom = $this->atom($char);

			if ($atom === null) {
				return null;
			}

			[$from, $to] = $atom;
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
	private function atom(string $char): ?array {
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

	private function characterClass(): ?string {
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

			[$set, $value] = $token;

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
	private function character(string $char): ?array {
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

	private function edge(int $from, int $to, string $kind = 'empty', string $bytes = ''): void {
		$this->edges[$from][] = [$to, $kind, $bytes];
	}

	private function peek(): string {
		return $this->pattern[$this->cursor] ?? '';
	}

	private function take(): string {
		return $this->pattern[$this->cursor++] ?? '';
	}
}
