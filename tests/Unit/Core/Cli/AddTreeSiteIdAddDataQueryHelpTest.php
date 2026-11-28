<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Cacti is designed, written and maintained by the Cacti Group.           |
 |                                                                         |
 | Please read the included docs/CONTRIBUTING.md file for more information.|
 +-------------------------------------------------------------------------+
 */

$addTree = file_get_contents(dirname(__DIR__, 4) . '/cli/add_tree.php');
$addQuery = file_get_contents(dirname(__DIR__, 4) . '/cli/add_data_query.php');
$apiTree = file_get_contents(dirname(__DIR__, 4) . '/lib/api_tree.php');

if ($addTree === false || $addQuery === false || $apiTree === false) {
	throw new RuntimeException('Unable to read the CLI or API sources.');
}

test('add_tree.php parses --site-id like --host-id and the help advertises it', function () use ($addTree) {
	expect($addTree)->toContain("case '--site-id':")
		->and($addTree)->toContain('$siteId = $value;')
		->and($addTree)->toContain('--site-id=[ID]');
});

test('add_tree.php maps the site node type and validates the site id', function () use ($addTree) {
	expect($addTree)->toContain("'site' => 3")
		->and($addTree)->toContain('!ctype_digit((string) $siteId) || $siteId < 1')
		->and($addTree)->toContain('No such site-id ($siteId) exists');
});

test('add_tree.php passes the parsed site id to api_tree_item_save', function () use ($addTree) {
	expect($addTree)->toContain('api_tree_item_save(0, $treeId, $itemType, $parentNode, $name, $graphId, $hostId, $siteId,');
});

test('api_tree_item_save persists the site id', function () use ($apiTree) {
	expect($apiTree)->toContain("\$save['site_id']            = form_input_validate(\$site_id, 'site_id'");
});

test('add_data_query.php help describes the already-associated error', function () use ($addQuery) {
	expect($addQuery)->toContain('ERROR: Data Query is already associated')
		->and($addQuery)->toContain('exit(1)')
		->and($addQuery)->toContain('the script reports an error and changes nothing')
		->and($addQuery)->toContain('the method is updated and the data query is reindexed')
		->and($addQuery)->not->toContain('If the data query was already associated, it will be reindexed.');
});
