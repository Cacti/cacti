<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace Cacti\Tests\Helpers;

/** Source contracts ignore formatting, while retaining token kinds and literals. */
final class PhpSource {
	/** @return array{source: string, line: int} */
	public static function functionDefinition(string $source, string $function) : array {
		$tokens = token_get_all($source);
		$offset = 0;

		foreach ($tokens as $index => $token) {
			$text = is_array($token) ? $token[1] : $token;

			if (is_array($token) && $token[0] === T_FUNCTION) {
				$next = $index + 1;

				while (isset($tokens[$next])) {
					$part     = $tokens[$next];
					$partText = is_array($part) ? $part[1] : $part;

					if ((is_array($part) && in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) || $partText === '&') {
						$next++;

						continue;
					}

					break;
				}

				if (isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING && strcasecmp($tokens[$next][1], $function) === 0) {
					$end    = $offset;
					$depth  = 0;
					$opened = false;

					for ($cursor = $index, $length = count($tokens); $cursor < $length; $cursor++) {
						$part = $tokens[$cursor];
						$end += strlen(is_array($part) ? $part[1] : $part);

						if ($part === '{' || (is_array($part) && in_array($part[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
							$depth++;
							$opened = true;
						} elseif ($part === '}' && $opened) {
							$depth--;

							if ($depth === 0) {
								return ['source' => substr($source, $offset, $end - $offset), 'line' => $token[2]];
							}
						} elseif ($part === ';' && !$opened) {
							break;
						}
					}

					throw new \RuntimeException('Incomplete source function ' . $function);
				}
			}
			$offset += strlen($text);
		}

		throw new \RuntimeException('Cannot locate source function ' . $function);
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
