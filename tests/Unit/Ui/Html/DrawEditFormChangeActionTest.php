<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for the draw_edit_form() handler binding (#7553).
 *
 * #7107 moved the inline onChange= attributes into $_SESSION and made
 * draw_edit_form() emit a <script> block that binds them, so that forms
 * work under CSP Level 3.  The guard on that block read the singular
 * $_SESSION['form_change_action'], a key nothing writes, which left the
 * whole block behind the form_click_actions clause.  A form carrying only
 * on_change fields bound nothing and its dropdowns went dead.  #7402
 * corrected the key.
 *
 * The form renders in a child process.  lib/html_form.php wants a dozen
 * helpers that read globals or the database, and stubbing those in the Pest
 * process would leak into every other file in the suite.
 */

function _draw_edit_form_render($fields) {
	$stub = <<<'PHP'
<?php
function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
}

function cacti_count($array) {
	return cacti_sizeof($array);
}

function cacti_log($message, $stdout = false, $facility = 'CACTI') {
}

function cacti_debug_backtrace($trace = '') {
}

function html_escape($string) {
	return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

function html_purify($string) {
	return (string) $string;
}

function read_config_option($name, $force = false) {
	return '';
}

function display_tooltip($text) {
	return '';
}

function get_current_page($url = true) {
	return 'test.php';
}

function kill_session_var($variable) {
	unset($_SESSION[$variable]);
}

function db_fetch_assoc($sql) {
	return array();
}

function html_create_list($data, $column_display = '', $column_id = '', $selected = '', $class = '') {
	foreach ($data as $id => $label) {
		print "<option value='" . html_escape($id) . "'>" .
			html_escape(is_array($label) ? reset($label) : $label) . '</option>';
	}
}

class CactiSecureHeaders {
	public static function getNonceAttribute() {
		return "nonce='test'";
	}
}

$_SESSION = array();

require $argv[1];

draw_edit_form(json_decode($argv[2], true));
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_form_');
	file_put_contents($script, $stub);

	$form = array(
		'config' => array('no_form_tag' => true),
		'fields' => $fields,
	);

	$cmd = escapeshellarg(defined('PHP_BINARY') ? PHP_BINARY : 'php') . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 4) . '/lib/html_form.php') . ' ' .
		escapeshellarg(json_encode($form)) . ' 2>&1';

	$output = array();
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	expect($status)->toBe(0, 'rendering the form must not error: ' . implode("\n", $output));

	return implode("\n", $output);
}

function _draw_edit_form_dropdown($on_change) {
	return array(
		'friendly_name' => 'Threshold Type',
		'method'        => 'drop_array',
		'array'         => array(0 => 'Templated', 1 => 'Non Templated'),
		'value'         => 0,
		'on_change'     => $on_change,
	);
}

test('a form whose only handler is on_change still binds it', function () {
	$html = _draw_edit_form_render(array('type_id' => _draw_edit_form_dropdown('applyTholdFilter()')));

	expect($html)->toContain("on('change'")
		->and($html)->toContain('#type_id')
		->and($html)->toContain('applyTholdFilter()');
});

test('a click handler alongside the change handler binds both', function () {
	$html = _draw_edit_form_render(array(
		'type_id' => _draw_edit_form_dropdown('applyTholdFilter()'),
		'go'      => array(
			'friendly_name' => 'Go',
			'method'        => 'button',
			'value'         => 'Go',
			'on_click'      => 'submitForm()',
		),
	));

	expect($html)->toContain("on('change'")
		->and($html)->toContain("on('click'")
		->and($html)->toContain('submitForm()');
});

test('a form with no handlers emits no binding block', function () {
	$html = _draw_edit_form_render(array(
		'type_id' => array(
			'friendly_name' => 'Threshold Type',
			'method'        => 'drop_array',
			'array'         => array(0 => 'Templated', 1 => 'Non Templated'),
			'value'         => 0,
		),
	));

	expect($html)->not->toContain('<script');
});
