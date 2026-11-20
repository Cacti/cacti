<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Stored names, log lines and tab titles reached HTML or JavaScript output
 * without escaping for that context.  Each sink now escapes the value it
 * prints, or builds DOM nodes instead of markup.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/functions.php';

/**
 * Read a source file relative to the Cacti root.
 *
 * @param string $root The Cacti root directory
 * @param string $file The file to read
 *
 * @return string The file contents
 */
function outputContextSource($root, $file) {
	$source = file_get_contents($root . '/' . $file);

	expect($source)->not->toBeFalse("Failed to read $file");

	return $source;
}

test('the aggregate migrate notice escapes the graph template name', function () use ($root) {
	expect(outputContextSource($root, 'aggregate_graphs.php'))
		->toContain("html_escape(db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array(\$graph_template)))");
});

test('the installer process log escapes each log line', function () use ($root) {
	expect(outputContextSource($root, 'lib/installer.php'))
		->toContain("\$output_log = html_escape(\$logline) . '<br/>' . \$output_log;")
		->not->toContain("\$output_log = \$logline.'<br/>' . \$output_log;");
});

test('the help error report keeps only script file name characters', function () use ($root) {
	expect(outputContextSource($root, 'help.php'))
		->toContain("preg_replace('/[^A-Za-z0-9_.\\-]/', '', basename(get_nfilter_request_var('page')))");

	$page = preg_replace('/[^A-Za-z0-9_.\-]/', '', basename('host.php<b> x'));

	expect($page)->toBe('host.phpbx');
	expect(preg_replace('/[^A-Za-z0-9_.\-]/', '', basename('graph_view.php')))->toBe('graph_view.php');
});

test('the data source repair steps escape the tune commands', function () use ($root) {
	expect(outputContextSource($root, 'data_debug.php'))
		->toContain("form_selectable_ecell(\$rrdtool_path . ' tune ' . \$options, 'line3_' . \$i);")
		->not->toContain("form_selectable_cell(\$rrdtool_path . ' tune ' . \$options");
});

test('the automation SNMP item list escapes the stored version and protocols', function () use ($root) {
	expect(outputContextSource($root, 'automation_snmp.php'))
		->toContain("html_escape(\$item['snmp_version'])")
		->toContain("html_escape(\$item['snmp_auth_protocol'])")
		->toContain("html_escape(\$item['snmp_priv_protocol'])");
});

test('the graph thumbnail area escapes the data query name', function () use ($root) {
	expect(outputContextSource($root, 'lib/html.php'))
		->toContain("__('Data Query:') . ' ' . html_escape(\$graph['data_query_name'])");
});

test('the tree view passes the breadcrumb markup to JavaScript as JSON', function () use ($root) {
	$source = outputContextSource($root, 'lib/html_tree.php');

	expect($source)
		->toContain("var navBar = <?php print cacti_json_encode(\"<div id='navBar' class='navBar'>\" . draw_navigation_text() . '</div>');?>;")
		->not->toContain("var navBar = \"<div id='navBar' class='navBar'><?php print draw_navigation_text();?></div>\";");

	// backslashes in a name stay literal once the markup is JSON-encoded
	$encoded = cacti_json_encode('<a>' . htmlspecialchars('a\\b', ENT_QUOTES) . '</a>');

	expect($encoded)->toContain('a\\\\b')
		->not->toContain('<');
});

test('the tab overflow menu inserts the tab title as text', function () use ($root) {
	$source = outputContextSource($root, 'include/layout.js');

	expect($source)
		->toContain(".attr('href', href).text(text));")
		->not->toContain("'\" href=\"'+href+'\">' + text + '</a></li>'");
});
