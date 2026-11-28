<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * form_color_dropdown() writes the stored hex of the selected color into the
 * select's style attribute.  The value comes from the colors table, so it must
 * be escaped like every other stored value.
 *
 * The select renders in a child process so the database and escaping stubs do
 * not leak into the rest of the suite.
 */

function _render_color_dropdown(string $stored_hex) : string {
	$stub = <<<'PHP'
<?php
define('SESS_ERROR_FIELDS', 'sess_error_fields');

function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
}

function html_escape($string) {
	return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

function db_fetch_cell_prepared($sql, $params = []) {
	return $GLOBALS['stored_hex'];
}

function db_fetch_assoc($sql) {
	return [];
}

$_SESSION = [];
$GLOBALS['stored_hex'] = $argv[2];

require $argv[1];

form_color_dropdown('color_id', 1, '', 0);
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_cd_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(PHP_BINARY) . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 4) . '/lib/html_form.php') . ' ' .
		escapeshellarg($stored_hex) . ' 2>&1';

	$output = [];
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	expect($status)->toBe(0, 'rendering the dropdown must not error: ' . implode("\n", $output));

	return implode("\n", $output);
}

test('form_color_dropdown escapes the stored color in the style attribute', function () {
	$html = _render_color_dropdown("FF0000;' onmouseover='x");

	expect($html)->toContain("background-color: #FF0000;&#039; onmouseover=&#039;x;'")
		->and($html)->not->toContain("' onmouseover='x");
});

test('form_color_dropdown leaves a plain hex color unchanged', function () {
	expect(_render_color_dropdown('00FF00'))->toContain("<select style='background-color: #00FF00;' id='color_id'");
});
