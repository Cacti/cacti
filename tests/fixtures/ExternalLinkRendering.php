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

namespace Cacti\Test\ExternalLinks;

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';

// Only database and authorization boundaries are substituted. The renderer,
// path helper, file checks and HTML escaping all use production code.
function db_fetch_assoc_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	$GLOBALS['external_link_fixture_styles'][] = $params;

	return $GLOBALS['external_link_fixture_pages'];
}

function is_realm_allowed(int $realm, int|bool $check_user = false) : bool {
	return $realm !== 10005;
}

function remove_fixture_tree(string $root) : void {
	$entries = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
		\RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($entries as $entry) {
		// Remove a symlink itself, never what it points at.
		if ($entry->isLink() || !$entry->isDir()) {
			unlink($entry->getPathname());
		} else {
			rmdir($entry->getPathname());
		}
	}

	rmdir($root);
}

function render_fixture() : array {
	$root = sys_get_temp_dir() . '/cacti-external-links-' . bin2hex(random_bytes(8));
	mkdir($root . '/content/directory', 0700, true);
	file_put_contents($root . '/content/welcome.txt', 'Contained fixture content');
	file_put_contents($root . '/content/restricted.txt', 'Realm restricted fixture content');
	// A symlink that stays inside the content directory is served; one that
	// leads out of it is refused even though its name looks contained.
	mkdir($root . '/content/shared', 0700);
	file_put_contents($root . '/content/shared/linked.txt', 'Linked fixture content');
	file_put_contents($root . '/outside.txt', 'Outside fixture content');
	symlink($root . '/content/shared/linked.txt', $root . '/content/alias.txt');
	symlink($root . '/outside.txt', $root . '/content/leak.txt');
	define(__NAMESPACE__ . '\\CACTI_PATH_INCLUDE', $root);
	cacti_fixture_source_function(dirname(__DIR__, 2) . '/index.php', 'render_external_links', __NAMESPACE__);
	$GLOBALS['external_link_fixture_styles'] = [];
	$GLOBALS['external_link_fixture_pages']  = [
		['id' => 1, 'contentfile' => 'welcome.txt'],
		['id' => 2, 'contentfile' => 'missing&page.txt'],
		['id' => 3, 'contentfile' => 'directory'],
		['id' => 4, 'contentfile' => 'https://example.test/page?a=1&b=2'],
		['id' => 5, 'contentfile' => 'restricted.txt'],
		['id' => 6, 'contentfile' => 'alias.txt'],
		['id' => 7, 'contentfile' => 'leak.txt'],
	];
	$buffer_level = ob_get_level();

	try {
		ob_start();
		render_external_links();
		$html                                   = ob_get_clean();
		$GLOBALS['external_link_fixture_pages'] = [];
		ob_start();
		render_external_links('FRONTTOP');
		$empty = ob_get_clean();

		return ['html' => $html, 'empty' => $empty, 'styles' => $GLOBALS['external_link_fixture_styles']];
	} finally {
		while (ob_get_level() > $buffer_level) {
			ob_end_clean();
		}
		unset($GLOBALS['external_link_fixture_styles'], $GLOBALS['external_link_fixture_pages']);
		remove_fixture_tree($root);
	}
}
