<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the opt-in validator layer on the color editor.
// color.php's form_save() and the bootstrap statements that wire the layer in
// only run inside a full web request, so each checked-in statement is compiled
// at its original line number (coverage is remapped to the source file) and
// driven with request stand-ins. The save runs twice: first before the
// Composer autoloader is registered, where the legacy regex check must be
// used, then with symfony/validator autoloaded against the real
// $fields_color_edit constraints.

namespace {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	require_once dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
	require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';

	$root = dirname(__DIR__, 2);

	/**
	 * Compile the checked-in statements matching $pattern at their original
	 * line numbers and return the temporary file to require. Coverage of the
	 * temporary file is remapped to $file at shutdown.
	 *
	 * @param string $file     The checked-in source file.
	 * @param string $pattern  A multi-line regex selecting whole statements.
	 * @param int    $expected How many statements must match.
	 */
	function color_probe_slice(string $file, string $pattern, int $expected) : string {
		$source = file_get_contents($file);

		if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== $expected) {
			throw new RuntimeException('Unexpected statements in ' . basename($file));
		}
		$code = '<?php';
		$line = 1;

		foreach ($matches[0] as [$statement, $offset]) {
			$start = substr_count(substr($source, 0, $offset), "\n") + 1;
			$code .= str_repeat("\n", $start - $line) . $statement;
			$line  = $start + substr_count($statement, "\n");
		}
		$temporary = tempnam(sys_get_temp_dir(), 'cacti-color-slice-');
		file_put_contents($temporary, $code . "\n");
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

		return $temporary;
	}

	/**
	 * Translation stand-in that only performs the printf substitution.
	 *
	 * @param string $text The message format.
	 */
	function __($text, ...$args) : string {
		return $args === [] ? $text : vsprintf($text, $args);
	}

	/**
	 * Record a queued UI message; form_validate_stash() raises one globally.
	 *
	 * @param mixed $id The message id.
	 */
	function raise_message($id, ...$args) : void {
		$GLOBALS['color_messages'][] = $id;
	}

	/**
	 * Fail the probe with a message naming the broken expectation.
	 *
	 * @param bool   $condition The expectation that must hold.
	 * @param string $message   What was expected.
	 */
	function color_expect(bool $condition, string $message) : void {
		if (!$condition) {
			throw new RuntimeException($message);
		}
		$GLOBALS['color_count']++;
	}
	$GLOBALS['color_count'] = 0;

	$slices = [
		color_probe_slice($root . '/include/global_constants.php', "/^define\\('SESS_(?:ERROR_FIELDS|FIELD_VALUES|FIELD_ERRORS)', '[a-z_]+'\\);$/m", 3),
		color_probe_slice($root . '/include/global_form.php', '/^\$fields_color_edit = \[$.*?^\];$/ms', 1),
		color_probe_slice($root . '/include/global.php', "/^require_once\\(CACTI_PATH_LIBRARY \\. '\\/form_validate\\.php'\\);$/m", 1),
	];
	define('CACTI_PATH_LIBRARY', $root . '/lib');

	try {
		// Top-level requires keep $fields_color_edit in the global scope that
		// form_save() imports it from.
		foreach ($slices as $slice) {
			require $slice;
		}
	} finally {
		array_map('unlink', $slices);
	}
}

namespace Cacti\Test\ColorSave {
	/**
	 * Report whether the stand-in request carries a variable.
	 *
	 * @param string $name The request variable name.
	 */
	function isrv($name) : bool {
		return isset($GLOBALS['color_request'][$name]);
	}

	/**
	 * Return a stand-in request variable without filtering.
	 *
	 * @param string $name The request variable name.
	 */
	function gnrv($name) : mixed {
		return $GLOBALS['color_request'][$name] ?? '';
	}

	/**
	 * Return a stand-in request variable; filters are not under test here.
	 *
	 * @param string $name The request variable name.
	 */
	function gfrv($name, ...$args) : mixed {
		return gnrv($name);
	}

	/**
	 * Return a stand-in request variable.
	 *
	 * @param string $name The request variable name.
	 */
	function grv($name) : mixed {
		return gnrv($name);
	}

	/**
	 * Record the legacy regex check the save path falls back to.
	 *
	 * @param mixed $value The submitted value.
	 */
	function form_input_validate($value, ...$args) : mixed {
		$GLOBALS['color_legacy'][] = array_merge([$value], $args);

		return $value;
	}

	/**
	 * Same decision as lib/functions.php: any flagged field is an error.
	 */
	function is_error_message() : bool {
		return !empty($_SESSION[SESS_ERROR_FIELDS]);
	}

	/**
	 * Record the row the save path would write.
	 *
	 * @param array  $save  The row to save.
	 * @param string $table The target table.
	 */
	function sql_save($save, $table) : int {
		$GLOBALS['color_saved'][] = [$table, $save];

		return 9;
	}

	/**
	 * Record the redirect, then unwind before form_save() reaches its exit.
	 *
	 * @param string $value The header line.
	 */
	function header($value) : void {
		$GLOBALS['color_redirect'] = $value;

		throw new \LogicException('redirect');
	}

	\cacti_fixture_source_function($root . '/color.php', 'form_save', __NAMESPACE__);

	/**
	 * Run form_save() for one submission from a clean session.
	 *
	 * @param string $name The posted color name.
	 * @param string $hex  The posted hex value.
	 */
	function color_save(string $name, string $hex) : void {
		$_SESSION                   = [];
		$GLOBALS['color_request']   = ['save_component_color' => 1, 'id' => '4', 'name' => $name, 'hex' => $hex];
		$GLOBALS['color_legacy']    = [];
		$GLOBALS['color_saved']     = [];
		$GLOBALS['color_messages']  = [];
		$GLOBALS['color_redirect']  = '';

		try {
			form_save();
		} catch (\LogicException $e) {
			// The redirect is the save path's last step.
		}
	}

	// Without the autoloader the layer must stay out of the way entirely.
	\color_expect(!\form_validate_available() && \form_validate_get_validator() === null, 'Validator must be unavailable before autoloading');
	\color_expect(\form_validate_fields($GLOBALS['fields_color_edit'], ['hex' => 'zz']) === [], 'An unavailable validator must treat input as valid');
	color_save('Fallback', 'zz');
	\color_expect($GLOBALS['color_legacy'] === [['zz', 'hex', '^[a-fA-F0-9]+$', false, 3]], 'Fallback must use the legacy hex check');
	\color_expect(count($GLOBALS['color_saved']) === 1 && $GLOBALS['color_redirect'] === 'Location: color.php', 'Fallback save must proceed');

	require_once $root . '/include/vendor/autoload.php';
	\color_expect(\form_validate_available(), 'Validator must be available once autoloaded');

	color_save('Lime', '00FF00');
	\color_expect($GLOBALS['color_legacy'] === [] && $GLOBALS['color_saved'] === [['colors', ['id' => '4', 'name' => 'Lime', 'hex' => '00FF00']]], 'A valid color must be saved unchanged');
	\color_expect($GLOBALS['color_redirect'] === 'Location: color.php' && !isset($_SESSION[SESS_FIELD_ERRORS]), 'A valid color must not stash errors');

	color_save('', '0f0f0f');
	\color_expect(count($GLOBALS['color_saved']) === 1, 'An unnamed color must remain valid');

	foreach ([[str_repeat('n', 41), '', ['name', 'hex']], ['Short', 'FFF', ['hex']], ['Bad', 'GGGGGG', ['hex']]] as [$name, $hex, $fields]) {
		color_save($name, $hex);
		\color_expect($GLOBALS['color_saved'] === [] && $GLOBALS['color_messages'] === [3], 'An invalid color must not be saved: ' . $hex);
		\color_expect(array_keys($_SESSION[SESS_FIELD_ERRORS]) === $fields && array_keys($_SESSION[SESS_ERROR_FIELDS]) === $fields, 'Wrong fields flagged for ' . $hex);
		\color_expect($_SESSION[SESS_FIELD_VALUES] === ['name' => $name, 'hex' => $hex], 'Submitted values must be retained');
		\color_expect($GLOBALS['color_redirect'] === 'Location: color.php?action=edit&id=4', 'An invalid color must return to the editor');
	}
	// Regex skips empty values, so the last case (a malformed hex) carries it.
	\color_expect(in_array('Hex Value must be exactly 6 hexadecimal digits (000000-FFFFFF).', $_SESSION[SESS_FIELD_ERRORS]['hex'], true), 'The declared hex message must be stashed');

	print json_encode(['passed' => $GLOBALS['color_count']], JSON_THROW_ON_ERROR) . "\n";
}
