<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace Cacti\Tests\Helpers;

/** Source contracts ignore formatting, while retaining token kinds and literals. */
final class PhpSource {
	/** @return array{code: string, line: int} */
	public static function functionDefinition(string $source, string $name): array {
		$tokens  = \PhpToken::tokenize($source, TOKEN_PARSE);
		$matches = [];

		foreach ($tokens as $index => $token) {
			if ($token->id !== T_FUNCTION) {
				continue;
			}
			$next = $index + 1;

			while (isset($tokens[$next]) && ($tokens[$next]->isIgnorable() || $tokens[$next]->text === '&')) {
				$next++;
			}

			if (!isset($tokens[$next]) || $tokens[$next]->id !== T_STRING || $tokens[$next]->text !== $name) {
				continue;
			}
			$parentheses = 0;
			$body        = null;

			for ($next++; isset($tokens[$next]); $next++) {
				$text = $tokens[$next]->text;

				if ($text === '(') {
					$parentheses++;
				} elseif ($text === ')') {
					$parentheses--;
				} elseif ($text === '{' && $parentheses === 0) {
					$body = $next;

					break;
				} elseif ($text === ';' && $parentheses === 0) {
					break;
				}
			}

			if ($body === null) {
				throw new \RuntimeException('Function has no executable body: ' . $name);
			}
			$depth = 0;

			for ($next = $body; isset($tokens[$next]); $next++) {
				$current = $tokens[$next];

				if ($current->text === '{' || $current->id === T_CURLY_OPEN || $current->id === T_DOLLAR_OPEN_CURLY_BRACES) {
					$depth++;
				} elseif ($current->text === '}') {
					$depth--;

					if ($depth === 0) {
						$matches[] = ['code' => substr($source, $token->pos, $current->pos + 1 - $token->pos), 'line' => $token->line];

						break;
					}
				}
			}
		}

		if (count($matches) !== 1) {
			throw new \RuntimeException('Expected one source function ' . $name . ', found ' . count($matches));
		}

		return $matches[0];
	}

	public static function normalizeWhitespace(string $source) : string {
		$output = '';

		foreach (token_get_all($source) as $token) {
			$id   = is_array($token) ? $token[0] : 0;
			$text = is_array($token) ? $token[1] : $token;

			if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				if (!str_ends_with($output, ' ')) {
					$output .= ' ';
				}
			} else {
				$output .= $text;
			}
		}

		return $output;
	}

	private static function encode(string $source, bool $fragment = false) : array {
		if ($fragment) {
			$source = '<?php ' . $source;
		}

		$encoded = [];
		$offsets = [];
		$offset  = 0;

		foreach (token_get_all($source) as $token) {
			$id   = is_array($token) ? $token[0] : 0;
			$text = is_array($token) ? $token[1] : $token;

			if (!in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				$encoded[] = json_encode([$id, $text], JSON_THROW_ON_ERROR);
				$offsets[] = $offset;
			}

			$offset += strlen($text);
		}

		return [implode("\n", $encoded), $offsets];
	}

	public static function position(string $source, string $fragment) : int|false {
		[$encoded, $offsets] = self::encode($source);
		[$needle]            = self::encode($fragment, true);
		$match               = self::match($encoded, $needle);

		return $match === false ? false : $offsets[substr_count(substr($encoded, 0, $match), "\n")];
	}

	public static function count(string $source, string $fragment) : int {
		[$encoded] = self::encode($source);
		[$needle]  = self::encode($fragment, true);
		$count     = 0;
		$offset    = 0;

		while (($match = self::match($encoded, $needle, $offset)) !== false) {
			$count++;
			$offset = $match + strlen($needle);
		}

		return $count;
	}

	private static function match(string $source, string $needle, int $offset = 0) : int|false {
		if ($needle === '') {
			throw new \InvalidArgumentException('A PHP source contract needs at least one token.');
		}

		while (($match = strpos($source, $needle, $offset)) !== false) {
			$end = $match + strlen($needle);

			if (($match === 0 || $source[$match - 1] === "\n") &&
				($end === strlen($source) || $source[$end] === "\n")) {
				return $match;
			}

			$offset = $match + 1;
		}

		return false;
	}
}

if (function_exists('expect')) {
	expect()->extend('toContainPhp', function (string $fragment) {
		\PHPUnit\Framework\Assert::assertNotFalse(
			PhpSource::position($this->value, $fragment),
			'Expected PHP token sequence: ' . $fragment
		);

		return $this;
	});
}
