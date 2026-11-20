<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * These handlers purge logs, change templates, enable or disable data
 * sources, reorder items or reset profile settings.  They must only run on a
 * POST, which csrf-magic validates, and the page scripts must send a POST.
 */

$root = dirname(__DIR__, 4);

/**
 * Return the source between a marker and the next case label or closing
 * brace at the same depth, enough to see the guard that opens a handler.
 *
 * @param string $source The file contents
 * @param string $marker The text that starts the handler
 *
 * @return string The handler opening, or an empty string when not found
 */
function stateChangeHandler($source, $marker) {
	$start = strpos($source, $marker);

	if ($start === false) {
		return '';
	}

	return substr($source, $start, 160);
}

dataset('post only handlers', array(
	'data debug purge'          => array('data_debug.php', "if (isset_request_var('purge')) {"),
	'snmpagent events purge'    => array('utilities.php', "if (isset_request_var('purge')) {\n\t\tcsrf_require_post();\n\n\t\tdb_execute('TRUNCATE table snmpagent_notifications_log');"),
	'manager log purge'         => array('managers.php', "if (isset_request_var('purge')) {"),
	'device template add gt'    => array('host_templates.php', "case 'item_add_gt':"),
	'device template remove gt' => array('host_templates.php', "case 'item_remove_gt':"),
	'device template add dq'    => array('host_templates.php', "case 'item_add_dq':"),
	'device template remove dq' => array('host_templates.php', "case 'item_remove_dq':"),
	'data input field remove'   => array('data_input.php', "case 'field_remove':"),
	'data source disable'       => array('data_sources.php', "case 'ds_disable':"),
	'data source enable'        => array('data_sources.php', "case 'ds_enable':"),
	'profile logout everywhere' => array('auth_profile.php', "case 'logout_everywhere':"),
	'profile clear settings'    => array('auth_profile.php', "case 'clear_user_settings':"),
	'profile reset default'     => array('auth_profile.php', "case 'reset_default':"),
	'cdef item reorder'         => array('cdef.php', "case 'ajax_dnd':"),
	'vdef item reorder'         => array('vdef.php', "case 'ajax_dnd':"),
));

test('the handler requires a POST before it changes anything', function ($file, $marker) use ($root) {
	$handler = stateChangeHandler(file_get_contents($root . '/' . $file), $marker);

	expect($handler)->not->toBe('', "$file: handler not found")
		->and(preg_match('/^[^\n]*\n\s*csrf_require_post\(\);/', $handler))->toBe(1, "$file: $marker");
})->with('post only handlers');

test('the page scripts send these actions as a POST', function () use ($root) {
	$debug     = file_get_contents($root . '/data_debug.php');
	$utilities = file_get_contents($root . '/utilities.php');
	$sources   = file_get_contents($root . '/data_sources.php');
	$profile   = file_get_contents($root . '/auth_profile.php');

	expect($debug)->toContain("strURL = 'data_debug.php?purge=1&debug=-1&header=false';\n\t\t\tloadPageUsingPostUrl(strURL);");
	expect($utilities)->toContain("strURL = 'utilities.php?action=view_snmpagent_events&purge=1&header=false';\n\t\tloadPageUsingPostUrl(strURL);");

	expect($sources)->toContain("<a class='dsActiveToggle' href='<?php print html_escape('data_sources.php?action=ds_'")
		->toContain("on('click.dsActiveToggle', 'a.dsActiveToggle', function(event) {")
		->not->toContain("<a class='hyperLink' href='<?php print html_escape('data_sources.php?action=ds_'");

	expect($profile)->toContain("$.post('auth_profile.php?action=clear_user_settings', {__csrf_magic: csrfMagicToken}")
		->toContain("$.post('auth_profile.php?action=logout_everywhere', {__csrf_magic: csrfMagicToken}")
		->toContain("&action=reset_default', {__csrf_magic: csrfMagicToken, name: id}")
		->not->toContain("$.get('auth_profile.php");

	foreach (array('cdef.php', 'vdef.php') as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->toContain("loadPageUsingPostUrl('$file?action=ajax_dnd&id=")
			->not->toContain("loadPageNoHeader('$file?action=ajax_dnd&id=");
	}
});
