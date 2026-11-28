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

/*
 * These page handlers changed or deleted stored data when reached by a plain
 * GET, so a cross-site request could trigger them with the victim's session.
 * Each must now refuse anything but a CSRF-checked POST, and the page script
 * that triggers it must send the POST (include/layout.js cactiIsPostActionUrl).
 */

/**
 * Collects the action names a page passes to csrf_require_post().
 *
 * @param string $file The page, relative to the repository root
 *
 * @return array<int, string> Action names guarded by csrf_require_post().
 */
function get_mutation_guarded_actions(string $file) : array {
	$src = file_get_contents(dirname(__DIR__, 4) . '/' . $file);

	if (!preg_match_all('/if \(in_array\(grv\(\'action\'\), \[([\s\S]*?)\], true\)\) \{\s*csrf_require_post\(\);/', $src, $blocks)) {
		return [];
	}

	$names = [];

	foreach ($blocks[1] as $block) {
		preg_match_all("/'([^']+)'/", $block, $m);
		$names = array_merge($names, $m[1]);
	}

	return $names;
}

test('mutating actions are refused unless they arrive by CSRF-checked POST', function (string $file, array $actions) {
	expect(array_diff($actions, get_mutation_guarded_actions($file)))->toBe([]);
})->with([
	'graph templates' => ['graph_templates.php', ['ajax_dnd', 'input_remove']],
	'graphs'          => ['graphs.php', ['ajax_dnd']],
	'automation'      => ['automation_templates.php', ['ajax_dnd', 'graph_dnd', 'tree_dnd', 'exitonchange', 'item_add_agr', 'item_add_atr', 'item_add_ttr', 'item_remove_agr', 'item_remove_atr', 'item_remove_ttr']],
	'data debug'      => ['data_debug.php', ['purge']],
	'utilities'       => ['utilities.php', ['purge_snmpagent_events']],
	'auth profile'    => ['auth_profile.php', ['logout_everywhere', 'clear_user_settings', 'reset_default']],
	'trees'           => ['tree.php', ['sortasc', 'sortdesc', 'ajax_dnd', 'lock', 'unlock', 'set_host_sort', 'set_branch_sort']],
]);

test('purge actions no longer run from a request variable on a GET', function () {
	$root = dirname(__DIR__, 4);

	$debug     = file_get_contents($root . '/data_debug.php');
	$utilities = file_get_contents($root . '/utilities.php');

	expect($debug)->not->toContain("isrv('purge')")
		->and($utilities)->not->toContain("isrv('purge')")
		->and($utilities)->not->toContain('purge=true')
		->and($utilities)->toContain("'url'     => 'utilities.php?action=purge_snmpagent_events'");
});

test('the page scripts send the converted actions as POST', function () {
	$root = dirname(__DIR__, 4);

	$profile = file_get_contents($root . '/auth_profile.php');
	$auto    = file_get_contents($root . '/automation_templates.php');

	expect($profile)->not->toContain("\$.get('auth_profile.php?action=logout_everywhere'")
		->and($profile)->toContain("cactiPreparePostRequest('auth_profile.php', Object.assign({action: 'logout_everywhere'}, fields))")
		->and($auto)->toContain("filter_value(\$exit_text, '', \$exit_on_url, '', true)");
});
