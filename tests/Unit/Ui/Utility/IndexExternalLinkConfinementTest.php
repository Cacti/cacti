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

$src = file_get_contents(dirname(__DIR__, 4) . '/index.php');

function _render_links_body(string $src): string {
	expect($src)->not->toBeEmpty();
	$start = strpos($src, 'function render_external_links(');
	expect($start)->not->toBeFalse();
	$end = strpos($src, "\n}", $start);
	expect($end)->not->toBeFalse();

	return substr($src, $start, $end - $start);
}

test('the content include is confined to include/content before it is required', function () use ($src) {
	$body = _render_links_body($src);

	$guard   = strpos($body, 'if ($file !== false && is_file($file))');
	$require = strpos($body, 'require_once($file)');

	expect($body)->toContain("\$file = cacti_path_within(CACTI_PATH_INCLUDE . '/content', \$page['contentfile']);");
	expect($guard)->not->toBeFalse();
	expect($require)->not->toBeFalse();
	// the confinement check gates the require
	expect($guard)->toBeLessThan($require);
	// the raw file_exists()->require path is gone
	expect($body)->not->toContain('if (file_exists($file)) {');
});

test('external links render contained files, URLs and missing-file messages', function () {
	require_once dirname(__DIR__, 3) . '/fixtures/ExternalLinkRendering.php';
	$result = \Cacti\Test\ExternalLinks\render_fixture();

	expect($result['html'])->toContain('Contained fixture content')
		->toContain('missing&amp;page.txt')
		->toContain('directory')
		->toContain('src="https://example.test/page?a=1&amp;b=2"')
		->toContain('Linked fixture content')
		->toContain('leak.txt')
		->not->toContain('Realm restricted fixture content')
		->not->toContain('Outside fixture content')
		->and(substr_count($result['html'], 'does not exist!!'))->toBe(3)
		->and($result['empty'])->toBe('')
		->and($result['styles'])->toBe([['FRONT'], ['FRONTTOP']]);
});

test('the contentfile is escaped in the iframe src and error output', function () use ($src) {
	$body = _render_links_body($src);

	expect($body)->toContain("src=\"' . html_escape(\$page['contentfile'])");
	expect($body)->not->toContain("src=\"' . \$page['contentfile']");
	// the not-found error output escapes only the filename, matching link.php
	expect($body)->toContain("html_escape(\$page['contentfile']) . '\\' does not exist");
	expect($body)->not->toContain("html_escape('The file");
});
