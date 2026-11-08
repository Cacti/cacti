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

namespace Cacti\Rrd\Graph;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Validates the graph options owned by the RRD graph service.
 */
final class GraphOptions {
	private static ?OptionsResolver $resolver = null;

	private const FLAG_OPTIONS = [
		'export',
		'export_csv',
		'get_error',
		'graph_nolegend',
		'graphv',
		'print_source',
	];

	private const PATH_OPTIONS = [
		'export_filename',
		'export_realtime',
		'output_filename',
	];

	/**
	 * Normalize the options owned by the RRD graph service while retaining
	 * extension values consumed by plugin hooks.
	 *
	 * @param array<string, mixed> $options
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve(array $options): array {
		// Cache the fixed rules only; OptionsResolver clones them for each resolution.
		$resolver     = self::$resolver ??= self::createResolver();
		$knownOptions = array_intersect_key($options, array_flip($resolver->getDefinedOptions()));

		foreach ($knownOptions as $name => $value) {
			if (self::isTolerated($name, $value)) {
				// Callers have always been able to leave these unset-like; keep the value for the legacy consumers.
				unset($knownOptions[$name]);
			} elseif (is_float($value) && is_finite($value) && in_array($name, ['graph_start', 'graph_end', 'output_flag'], true)) {
				$knownOptions[$name] = (int) $value;
			}
		}

		$resolved = $resolver->resolve($knownOptions);

		return array_replace($options, $resolved);
	}

	/**
	 * Values the legacy graph code accepted and ignored: null or empty strings, non-positive
	 * sizes, and relative time expressions such as 'now-1d' for the window bounds.
	 *
	 * @param string $name  the option name
	 * @param mixed  $value the caller's value
	 *
	 * @return bool true when the value bypasses validation
	 */
	private static function isTolerated(string $name, mixed $value): bool {
		if ($value === null || $value === '') {
			return true;
		}

		if (in_array($name, ['graph_height', 'graph_width'], true)) {
			return is_numeric($value) && (float) $value <= 0;
		}

		if (in_array($name, ['graph_start', 'graph_end'], true)) {
			return is_string($value) && !is_numeric($value);
		}

		return false;
	}

	/**
	 * Configure the fixed type and value rules for the known options.
	 *
	 * @return OptionsResolver the configured resolver
	 */
	private static function createResolver(): OptionsResolver {
		$resolver = new OptionsResolver();
		$known    = array_merge(self::FLAG_OPTIONS, self::PATH_OPTIONS, [
			'graph_end',
			'graph_height',
			'graph_start',
			'graph_theme',
			'graph_width',
			'image_format',
			'output_flag',
		]);

		$resolver->setDefined($known);

		foreach (self::FLAG_OPTIONS as $name) {
			$resolver->setAllowedTypes($name, ['bool', 'int', 'string']);
		}

		foreach (self::PATH_OPTIONS as $name) {
			$resolver->setAllowedTypes($name, 'string');
		}

		foreach (['graph_start', 'graph_end', 'output_flag'] as $name) {
			$resolver->setAllowedTypes($name, ['int', 'float', 'string']);
			$resolver->setNormalizer($name, static fn (Options $unused, mixed $value): int => self::normalizeInteger($name, $value));
		}

		foreach (['graph_height', 'graph_width'] as $name) {
			$resolver->setAllowedTypes($name, ['int', 'float', 'string']);
			$resolver->setNormalizer($name, static fn (Options $unused, mixed $value): int|float => self::normalizeNumber($name, $value));
		}

		foreach (['graph_theme', 'image_format'] as $name) {
			$resolver->setAllowedTypes($name, 'string');
		}

		$resolver->setAllowedValues('image_format', ['png', 'svg+xml']);
		$resolver->setAllowedValues('output_flag', static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_INT) !== false && in_array((int) $value, [0, 1, 2, 3, 4, 5], true));

		return $resolver;
	}

	/**
	 * Accept an int, or a string or float that holds an exact integer.
	 *
	 * @param string $name  the option name, for the exception message
	 * @param mixed  $value the value to normalize
	 *
	 * @throws InvalidOptionsException when the value is not an exact integer
	 *
	 * @return int the integer value
	 */
	private static function normalizeInteger(string $name, mixed $value): int {
		if (is_int($value)) {
			return $value;
		}

		if ((is_string($value) || is_float($value)) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
			return (int) $value;
		}

		throw new InvalidOptionsException("The '$name' option must be an integer.");
	}

	/**
	 * Accept a finite number given as a number or a numeric string.
	 *
	 * Non-positive sizes never reach this normalizer: isTolerated() passes them
	 * through unvalidated, as the legacy graph code did.
	 *
	 * @param string $name  the option name, for the exception message
	 * @param mixed  $value the value to normalize
	 *
	 * @throws InvalidOptionsException when the value is not a finite number
	 *
	 * @return float|int the number, kept as an int when it is integral
	 */
	private static function normalizeNumber(string $name, mixed $value): int|float {
		if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
			$number = $value;
		} elseif (is_string($value) && is_numeric($value) && is_finite((float) $value)) {
			$integer = filter_var($value, FILTER_VALIDATE_INT);
			$number  = $integer !== false ? $integer : (float) $value;
		} else {
			throw new InvalidOptionsException("The '$name' option must be a finite number.");
		}

		return $number;
	}
}
