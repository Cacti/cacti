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
 * MutationEndpointPostGuardTest proves every map entry has a server guard.
 * This covers the other direction: an action the dispatcher guard refuses on
 * GET must not be requested by GET from the UI. loadUrl() only switches to
 * POST for actions listed in cactiIsPostActionUrl(), so a guarded action that
 * a page reaches through loadUrl() or a plain link, and that the map omits,
 * answers 405 in the browser.
 */

/**
 * Return the POST-only action map from include/layout.js.
 *
 * @param string $root The Cacti root directory
 *
 * @return array<string, list<string>>
 */
function post_action_transport_map(string $root) : array {
	preg_match('/var actions = (\{.*?\});/s', file_get_contents($root . '/include/layout.js'), $match);

	return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Return the actions each page's dispatcher guard sends to csrf_require_post().
 *
 * @param string $root The Cacti root directory
 *
 * @return array<string, list<string>>
 */
function post_action_dispatcher_guards(string $root) : array {
	$guards = [];

	foreach (glob($root . '/*.php') as $file) {
		if (!preg_match_all('/^if \(in_array\(grv\(\'action\'\), \[(.*?)\], true\)\) \{\s*csrf_require_post\(\);/ms', file_get_contents($file), $matches)) {
			continue;
		}

		foreach ($matches[1] as $list) {
			preg_match_all("/'([^']+)'/", $list, $names);

			$guards[basename($file)] = array_merge($guards[basename($file)] ?? [], $names[1]);
		}
	}

	return $guards;
}

/**
 * Return each UI reference to a guarded action that is not sent by POST.
 *
 * A reference is a GET request when the first request call in the 12 lines
 * starting at it is loadUrl(), $.get(), $.ajax() or a link href, unless its
 * element carries cactiPostAction. A URL that is never sent within the window,
 * such as the unused options object in vdef_item_remove_confirm(), is not a
 * request.
 *
 * @param string                      $root   The Cacti root directory
 * @param array<string, list<string>> $guards The guarded actions by page
 * @param array<string, list<string>> $map    The layout.js POST action map
 *
 * @return list<string>
 */
function post_action_get_references(string $root, array $guards, array $map) : array {
	$sources = array_merge(glob($root . '/*.php'), glob($root . '/lib/*.php'), glob($root . '/include/*.php'), glob($root . '/include/*.js'));
	$found   = [];

	foreach ($sources as $source) {
		$lines = file($source);

		foreach ($lines as $number => $line) {
			if (!preg_match_all('/([a-z_]+\.php)\?(?:[^\'"\s]*&)?action=([a-z_]+)/', $line, $references, PREG_SET_ORDER)) {
				continue;
			}

			foreach ($references as [, $page, $action]) {
				if (!in_array($action, $guards[$page] ?? [], true) || in_array($action, $map[$page] ?? [], true) || str_contains($line, 'cactiPostAction')) {
					continue;
				}

				preg_match('/(loadUrl|postUrl|\$\.post|\$\.get|\$\.ajax|href=)/', implode('', array_slice($lines, $number, 12)), $call);

				if (in_array($call[1] ?? '', ['loadUrl', '$.get', '$.ajax', 'href='], true)) {
					$found[] = $page . ' ' . $action . ' <- ' . basename($source) . ':' . ($number + 1);
				}
			}
		}
	}

	return $found;
}

test('guarded actions are reached only by POST from the UI', function () {
	$root   = dirname(__DIR__, 4);
	$guards = post_action_dispatcher_guards($root);

	expect($guards)->toHaveKeys(['automation_graph_rules.php', 'automation_templates.php', 'cdef.php', 'tree.php'])
		->and(post_action_get_references($root, $guards, post_action_transport_map($root)))->toBe([]);
});

test('a guarded action dropped from the transport map is reported', function () {
	$root = dirname(__DIR__, 4);
	$map  = post_action_transport_map($root);

	$map['automation_graph_rules.php'] = array_values(array_diff($map['automation_graph_rules.php'], ['qedit']));
	$map['automation_templates.php']   = array_values(array_diff($map['automation_templates.php'], ['graph_dnd']));

	$actions = array_map(fn ($reference) => strstr($reference, ' <- ', true), post_action_get_references($root, post_action_dispatcher_guards($root), $map));

	expect(array_unique($actions))->toEqualCanonicalizing(['automation_graph_rules.php qedit', 'automation_templates.php graph_dnd']);
});
