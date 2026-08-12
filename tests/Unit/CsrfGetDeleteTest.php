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
 * Delete actions (tree delete_node, host gt_remove/query_remove) fired on GET
 * with no CSRF token, so a cross-origin <img> or link deleted objects using the
 * victim's session cookie. They must arrive by POST with a token now: the
 * server rejects the GET, and the UI submits them through the token POST path.
 */

$root = dirname(__DIR__, 2);

test('the server rejects these delete actions on GET without a token', function () use ($root) {
	$src = file_get_contents($root . '/include/global.php');

	// the GET-method block now covers the delete actions
	expect($src)->toContain("\$bad_actions = ['save', 'update_data', 'changepassword', 'delete_node', 'gt_remove', 'query_remove']");
	// the block still requires the csrf token on POST
	expect($src)->toContain("!isset(\$_POST['__csrf_magic'])");
});

test('tree.php deletes a node via a token POST, not GET', function () use ($root) {
	$src = file_get_contents($root . '/tree.php');

	expect($src)->toContain("\$.post('?action=delete_node', { 'id' : data.node.id, 'tree_id' : \$('#id').val(), '__csrf_magic' : csrfMagicToken })");
	expect($src)->not->toContain("\$.get('?action=delete_node'");
});

test('host.php removes graph templates and data queries via a token POST', function () use ($root) {
	$src = file_get_contents($root . '/host.php');

	// gt_remove and query_remove no longer build a GET url through loadUrl
	expect($src)->not->toContain("'host.php?action=gt_remove&id='");
	expect($src)->not->toContain("'host.php?action=query_remove&id='");
	// they go through postUrl with the csrf token
	expect($src)->toContain("url: 'host.php?action=gt_remove'");
	expect($src)->toContain("url: 'host.php?action=query_remove'");
	expect(substr_count($src, '__csrf_magic: csrfMagicToken'))->toBeGreaterThanOrEqual(4);
});
