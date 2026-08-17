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
 * render_external_links() included external_links.contentfile from
 * include/content with only a file_exists() check, so a '../' value included an
 * arbitrary local file (stored LFI for a user with external-links write). It now
 * confines the resolved path to include/content the way link.php does, and
 * escapes the contentfile in the iframe src and the error message.
 */

$src = file_get_contents(dirname(__DIR__, 2) . '/index.php');

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

	$guard   = strpos($body, 'str_starts_with($file, $basepath . DIRECTORY_SEPARATOR)');
	$require = strpos($body, 'require_once($file)');

	expect($body)->toContain("\$basepath = realpath(CACTI_PATH_INCLUDE . '/content')");
	expect($body)->toContain('$file     = ($basepath !== false) ? realpath($basepath');
	expect($guard)->not->toBeFalse();
	expect($require)->not->toBeFalse();
	// the confinement check gates the require
	expect($guard)->toBeLessThan($require);
	// the raw file_exists()->require path is gone
	expect($body)->not->toContain('if (file_exists($file)) {');
});

test('a traversal contentfile does not resolve inside the content base', function () {
	// mirror the guard: realpath of a traversal escapes the base, so str_starts_with fails
	$base = sys_get_temp_dir() . '/cacti_content_' . getmypid();
	@mkdir($base, 0700, true);
	@mkdir($base . '/legit', 0700, true);
	file_put_contents($base . '/legit/ok.php', '<?php');
	file_put_contents(dirname($base) . '/evil.php', '<?php');

	$within = static function (string $contentfile) use ($base): bool {
		$basepath = realpath($base);
		$file     = ($basepath !== false) ? realpath($basepath . '/' . $contentfile) : false;

		return $file !== false && is_file($file) && str_starts_with($file, $basepath . DIRECTORY_SEPARATOR);
	};

	expect($within('legit/ok.php'))->toBeTrue();
	expect($within('../evil.php'))->toBeFalse();
	expect($within('legit/../../evil.php'))->toBeFalse();

	@unlink($base . '/legit/ok.php');
	@unlink(dirname($base) . '/evil.php');
	@rmdir($base . '/legit');
	@rmdir($base);
});

test('the contentfile is escaped in the iframe src and error output', function () use ($src) {
	$body = _render_links_body($src);

	expect($body)->toContain("src=\"' . html_escape(\$page['contentfile'])");
	expect($body)->not->toContain("src=\"' . \$page['contentfile']");
	// the not-found error output escapes only the filename, matching link.php
	expect($body)->toContain("html_escape(\$page['contentfile']) . '\\' does not exist");
	expect($body)->not->toContain("html_escape('The file");
});
