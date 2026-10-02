<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for the class contract of form_multi_dropdown() (the
 * drop_multi form method). The helper now renders a select2 'N Selected'
 * multi dropdown by default, while a caller keeps the legacy jquery-multiselect
 * widget by opting in with the 'multiselect' class. Three outcomes must hold so
 * future helper changes cannot silently double-decorate or revert a field:
 *
 *   - no class            -> 'select2-multi-count' is injected (plugin default)
 *   - 'multiselect'       -> left untouched (legacy opt-in), never select2
 *   - 'select2-multi-count' -> left untouched, not duplicated
 *
 * The select renders in a child process: lib/html_form.php wants a handful of
 * helpers that read globals or the database, and stubbing them in the Pest
 * process would leak into every other file in the suite.
 */

function _render_multi_dropdown($class) {
	$stub = <<<'PHP'
<?php
function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
}

function cacti_count($array) {
	return cacti_sizeof($array);
}

function html_escape($string) {
	return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return '';
}

$_SESSION = array();

require $argv[1];

form_multi_dropdown('test_field', array(1 => 'One', 2 => 'Two'), array(), 'id', $argv[2]);
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_md_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(defined('PHP_BINARY') ? PHP_BINARY : 'php') . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 4) . '/lib/html_form.php') . ' ' .
		escapeshellarg($class) . ' 2>&1';

	$output = array();
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	expect($status)->toBe(0, 'rendering the dropdown must not error: ' . implode("\n", $output));

	return implode("\n", $output);
}

function _multi_dropdown_class($html) {
	expect($html)->toMatch("/<select[^>]*\sclass='([^']*)'/");
	preg_match("/<select[^>]*\sclass='([^']*)'/", $html, $m);

	return $m[1];
}

test('drop_multi with no class is rendered as a select2 multi dropdown by default', function () {
	$class = _multi_dropdown_class(_render_multi_dropdown(''));

	expect($class)->toBe('select2-multi-count');
});

test('drop_multi keeps the legacy widget when the caller opts in with multiselect', function () {
	$class = _multi_dropdown_class(_render_multi_dropdown('multiselect'));

	expect($class)->toBe('multiselect')
		->and($class)->not->toContain('select2-multi-count');
});

test('drop_multi preserves a plugin class alongside the injected select2 class', function () {
	$class = _multi_dropdown_class(_render_multi_dropdown('myplugin'));

	expect($class)->toBe('select2-multi-count myplugin');
});

test('drop_multi does not double-decorate a field already marked select2-multi-count', function () {
	$class = _multi_dropdown_class(_render_multi_dropdown('select2-multi-count'));

	expect($class)->toBe('select2-multi-count')
		->and(substr_count($class, 'select2-multi-count'))->toBe(1);
});
