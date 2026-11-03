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
 * GHSA-54fg-q9h9-88mm: the package-repository Direct-URL/GitHub manifest fetch
 * used raw file_get_contents() with TLS verification disabled, allowing SSRF to
 * private hosts. Both the save-time probe (package_repos.php) and the
 * import-time read (package_import.php::get_repo_file) must route through
 * cacti_http() (scheme/host validation, private-range block, DNS pinning, TLS
 * verify; Bearer token via the headers option).
 */

$root = dirname(__DIR__, 4);

$repoSource   = file_get_contents($root . '/package_repos.php');
$importSource = file_get_contents($root . '/package_import.php');

test('GHSA-54fg: both save-time repo fetch paths use cacti_http and drop raw file_get_contents', function () use ($repoSource) {
	expect($repoSource)->toContain("cacti_http('GET', \$file)")
		->and($repoSource)->toContain("cacti_http('GET', \$file, \$http_options)")
		->and($repoSource)->not->toContain('file_get_contents($file')
		->and($repoSource)->not->toContain('verify_peer');
});

test('GHSA-54fg: the import-time repo fetch routes remote reads through cacti_http', function () use ($importSource) {
	expect($importSource)->toContain("cacti_http('GET', \$file, \$http_options)")
		->and($importSource)->toContain("cacti_http('GET', \$file)")
		->and($importSource)->not->toContain('verify_peer')
		->and($importSource)->not->toContain('file_get_contents($file, false');
});
