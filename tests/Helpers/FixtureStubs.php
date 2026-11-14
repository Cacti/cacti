<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/**
 * Recording stubs for source slices loaded with cacti_fixture_source_function().
 *
 * A slice resolves unqualified calls in its fixture namespace before the global
 * one, so a stub declared there isolates the slice from the rest of Cacti. Each
 * stub records its call and answers from $GLOBALS['cacti_fixture_returns'],
 * either a value or a closure given the call arguments; anything unset
 * returns null. A function the fixture already declared in that namespace,
 * and every PHP internal function, is left alone.
 */

/**
 * Lists the user functions that the named functions of a checked-in file call.
 *
 * @param string   $file      The source file.
 * @param string[] $functions The functions whose bodies are scanned, or [] for the whole
 *                            file, whose own declarations are then left out.
 *
 * @return string[] The called function names, lower-cased and unique.
 */
function cacti_fixture_called_functions(string $file, array $functions) : array {
	$tokens   = token_get_all(file_get_contents($file));
	$internal = array_flip(get_defined_functions()['internal']);
	$wanted   = array_flip(array_map('strtolower', $functions));
	$called   = [];
	$declared = [];
	$inside   = !$functions;
	$depth    = 0;
	$count    = count($tokens);

	for ($index = 0; $index < $count; $index++) {
		$token = $tokens[$index];

		if (!$inside) {
			if (is_array($token) && $token[0] === T_FUNCTION) {
				$next = $index + 1;

				while (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
					$next++;
				}

				if (is_array($tokens[$next]) && isset($wanted[strtolower($tokens[$next][1])])) {
					$inside = true;
					$depth  = 0;
				}
			}

			continue;
		}

		if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
			$depth++;
		} elseif ($token === '}') {
			$depth--;
			$inside = !$functions || $depth > 0;
		} elseif (is_array($token) && $token[0] === T_STRING) {
			$next = $index + 1;

			while (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
				$next++;
			}
			$previous = $index - 1;

			while (is_array($tokens[$previous]) && $tokens[$previous][0] === T_WHITESPACE) {
				$previous--;
			}
			$member = is_array($tokens[$previous]) && in_array($tokens[$previous][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_FUNCTION], true);
			$name   = strtolower($token[1]);

			if (is_array($tokens[$previous]) && $tokens[$previous][0] === T_FUNCTION) {
				$declared[$name] = $name;
			} elseif ($tokens[$next] === '(' && !$member && !isset($internal[$name])) {
				$called[$name] = $name;
			}
		}
	}

	return array_values(array_diff_key($called, $functions ? [] : $declared));
}

/**
 * Declares a recording stub in a namespace for each name not already declared there.
 *
 * @param string[] $names     The function names to stub.
 * @param string   $namespace The fixture namespace, or '' for the global namespace.
 *
 * @return void
 */
function cacti_fixture_stub_functions(array $names, string $namespace) : void {
	$source = '<?php ' . ($namespace !== '' ? 'namespace ' . $namespace . ';' : '');

	foreach (array_unique(array_map('strtolower', $names)) as $name) {
		if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
			throw new InvalidArgumentException('Invalid stub name ' . $name);
		}
		$qualified = ($namespace !== '' ? $namespace . '\\' : '') . $name;

		if (function_exists($qualified) || ($namespace === '' && function_exists($name))) {
			continue;
		}
		$source .= "\nfunction $name(...\$arguments) { return \\cacti_fixture_stub_call(" . var_export($qualified, true) . ', $arguments); }';
	}
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-fixture-stubs-');
	file_put_contents($temporary, $source);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}

/**
 * Aliases global classes into a fixture namespace.
 *
 * A slice's unqualified class names resolve only in its namespace, with no
 * global fallback, so "new RuntimeException" or "catch (Throwable ...)" needs
 * the alias to behave as it does in the original file.
 *
 * @param string[] $classes   The global class or interface names.
 * @param string   $namespace The fixture namespace.
 *
 * @return void
 */
function cacti_fixture_alias_classes(array $classes, string $namespace) : void {
	foreach ($classes as $class) {
		if (!class_exists($namespace . '\\' . $class, false) && !interface_exists($namespace . '\\' . $class, false)) {
			class_alias($class, $namespace . '\\' . $class);
		}
	}
}

/**
 * Records a stub call and returns its configured answer.
 *
 * @param string $name      The namespaced function name.
 * @param array  $arguments The call arguments.
 *
 * @return mixed The configured value, the configured closure's result, or null.
 */
function cacti_fixture_stub_call(string $name, array $arguments) : mixed {
	$GLOBALS['cacti_fixture_calls'][] = [$name, $arguments];
	$short                            = substr($name, (int) strrpos('\\' . $name, '\\'));
	$answer                           = $GLOBALS['cacti_fixture_returns'][$name] ?? $GLOBALS['cacti_fixture_returns'][$short] ?? null;

	return $answer instanceof Closure ? $answer(...$arguments) : $answer;
}

/**
 * Returns the recorded calls to one stub, matched by its short name.
 *
 * @param string $short The function name without its namespace.
 *
 * @return array[] The argument lists, in call order.
 */
function cacti_fixture_stub_arguments(string $short) : array {
	$found = [];

	foreach ($GLOBALS['cacti_fixture_calls'] ?? [] as [$name, $arguments]) {
		if ($name === $short || str_ends_with($name, '\\' . $short)) {
			$found[] = $arguments;
		}
	}

	return $found;
}

/**
 * Fails the fixture process when a condition does not hold.
 *
 * @param bool   $condition The asserted condition.
 * @param string $message   What was expected.
 *
 * @return void
 */
function cacti_fixture_assert(bool $condition, string $message) : void {
	if (!$condition) {
		fwrite(STDERR, 'Fixture assertion failed: ' . $message . "\n");

		exit(1);
	}
}
