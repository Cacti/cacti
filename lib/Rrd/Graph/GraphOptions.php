<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Rrd\Graph;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GraphOptions {
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

		$knownOptions = array_intersect_key($options, array_flip($known));
		$resolved     = $resolver->resolve($knownOptions);

		return array_replace($options, $resolved);
	}

	private static function normalizeInteger(string $name, mixed $value): int {
		if (is_int($value)) {
			return $value;
		}

		if ((is_string($value) || is_float($value)) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
			return (int) $value;
		}

		throw new InvalidOptionsException("The '$name' option must be an integer.");
	}

	private static function normalizeNumber(string $name, mixed $value): int|float {
		if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
			$number = $value;
		} elseif (is_string($value) && is_numeric($value) && is_finite((float) $value)) {
			$number = str_contains($value, '.') ? (float) $value : (int) $value;
		} else {
			throw new InvalidOptionsException("The '$name' option must be a finite number.");
		}

		if ($number <= 0) {
			throw new InvalidOptionsException("The '$name' option must be greater than zero.");
		}

		return $number;
	}
}
