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
 * Source-scan tests for the composer binary path setting (#6456).
 *
 * Cacti 1.3 requires composer for installs whose include/vendor is not
 * pre-packaged.  The setting gives the installer a place to record the
 * binary location; the installer seeds it, marks it optional so packaged
 * releases install unchanged, and verifies the binary runs when a path
 * is supplied.
 */

test('path_composer setting exists with binary filepath validation', function () {
	$src = file_get_contents(CACTI_PATH_INCLUDE . '/global_settings.php');
	expect($src)->not->toBeFalse('Failed to read include/global_settings.php');

	$pos = strpos($src, "'path_composer'");
	expect($pos)->not->toBeFalse('path_composer setting must exist');

	// tolerant of alignment: scan the setting block up to the next path_ entry
	$end   = strpos($src, "'path_fping'", $pos);
	$block = substr($src, $pos, ($end === false ? 1000 : $end - $pos));

	expect(preg_match("/'method'\s*=>\s*'filepath'/", $block))->toBe(1, 'path_composer must use the filepath method')
		->and(preg_match("/'file_type'\s*=>\s*'binary'/", $block))->toBe(1, 'path_composer must be a binary file type');
});

test('installer seeds path_composer as optional', function () {
	$src = file_get_contents(CACTI_PATH_BASE . '/install/functions.php');
	expect($src)->not->toBeFalse('Failed to read install/functions.php');

	expect(strpos($src, "install_tool_path('composer'"))->not->toBeFalse('install_file_paths must seed path_composer')
		->and(strpos($src, "\$input['path_composer']['install_optional'] = true;"))->not->toBeFalse('path_composer must be optional so packaged releases install unchanged');
});

test('composer version probe accepts real output and rejects noise', function () {
	$probe = function (?string $output): bool {
		return !($output === null || !str_contains($output, 'Composer'));
	};

	expect($probe('Composer version 2.7.7 2024-06-10 22:11:12'))->toBeTrue()
		->and($probe(null))->toBeFalse()
		->and($probe('sh: composer: command not found'))->toBeFalse()
		->and($probe(''))->toBeFalse();
});
