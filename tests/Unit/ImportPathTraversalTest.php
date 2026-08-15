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
 * import_package() wrote package files to $base_path/$name after only checking
 * that $name contained 'scripts/' or 'resource/'. A name like
 * 'resource/../target.php' passed that check and escaped base_path
 * (GHSA-vp35-4h28-r883). Traversal, NUL, and absolute paths are now rejected
 * before the destination is derived.
 */

$src = file_get_contents(dirname(__DIR__, 2) . '/lib/import.php');

test('the traversal and absolute-path guards run before the file write', function () use ($src) {
	$guard = strpos($src, "preg_match('#(^|/)\\.\\.(/|\$)#', \$normalized_name)");
	$write = strpos($src, "\$filename = \$config['base_path'] . \"/\$name\";");

	expect($guard)->not->toBeFalse();
	expect($write)->not->toBeFalse();
	expect($guard)->toBeLessThan($write);
	expect($src)->toContain("strpos(\$name, chr(0)) !== false");
	expect($src)->toContain("preg_match('#^([/\\\\\\\\]|[A-Za-z]:)#', \$name)");
});

test('the traversal pattern rejects the reported bypass and other escapes', function () {
	$bad = array(
		'resource/../target.php',
		'scripts/../../evil.php',
		'../x',
		'a/../../b',
		'resource/..',
	);
	foreach ($bad as $n) {
		expect(preg_match('#(^|/)\.\.(/|$)#', str_replace('\\', '/', $n)))->toBe(1);
	}
});

test('legitimate package paths are still allowed', function () {
	$ok = array('resource/script_server/foo.php', 'scripts/bar.pl', 'resource/snmp_queries/x.xml');
	foreach ($ok as $n) {
		expect(preg_match('#(^|/)\.\.(/|$)#', str_replace('\\', '/', $n)))->toBe(0);
		expect(preg_match('#^([/\\\\]|[A-Za-z]:)#', $n))->toBe(0);
	}
});

test('absolute paths are rejected', function () {
	foreach (array('/etc/passwd', '\\\\server\\share', 'C:\\win\\x') as $n) {
		expect(preg_match('#^([/\\\\]|[A-Za-z]:)#', $n))->toBe(1);
	}
});
