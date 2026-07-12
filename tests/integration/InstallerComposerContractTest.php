<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Cross-file contract test for the composer path detection (#6456).
 *
 * setPaths() in lib/installer.php honours install_optional, so the
 * composer probe must only run when a path was supplied and exists;
 * an empty path must not block the Binary Locations step.
 */

$root = dirname(__DIR__, 2);

test('setPaths probes composer only when a path is present', function () use ($root) {
	$src = file_get_contents($root . '/lib/installer.php');
	expect($src)->not->toBeFalse('Failed to read lib/installer.php');

	$fnPos = strpos($src, 'private function setPaths(');
	expect($fnPos)->not->toBeFalse('setPaths must exist');

	// tolerant of formatting: the gate must test the name, a non-empty path, and file existence
	$gate = '/\$name\s*==\s*\'path_composer\'.*!empty\(\$path\).*file_exists\(\$path\)/s';
	expect(preg_match($gate, substr($src, $fnPos), $m, PREG_OFFSET_CAPTURE))->toBe(1,
		'composer probe must be gated on a supplied, existing path');

	$block = substr($src, $fnPos + $m[0][1], 600);
	expect(strpos($block, '--version'))->not->toBeFalse('probe must run composer --version')
		->and(strpos($block, 'cacti_escapeshellcmd'))->not->toBeFalse('probe must escape the configured path')
		->and(strpos($block, 'STEP_BINARY_LOCATIONS'))->not->toBeFalse('probe failure must surface on the Binary Locations step');
});

test('composer probe follows the php_binary validation pattern', function () use ($root) {
	$src = file_get_contents($root . '/lib/installer.php');
	expect($src)->not->toBeFalse('Failed to read lib/installer.php');

	$phpPos      = strpos($src, "\$name == 'path_php_binary'");
	$composerPos = strpos($src, "\$name == 'path_composer'");

	expect($phpPos)->not->toBeFalse('php_binary validation must exist')
		->and($composerPos)->not->toBeFalse('composer validation must exist')
		->and($composerPos)->toBeGreaterThan($phpPos, 'composer probe sits with the other binary probes in setPaths');
});

test('global settings and installer seeding agree on the setting name', function () use ($root) {
	$settings = file_get_contents($root . '/include/global_settings.php');
	$install  = file_get_contents($root . '/install/functions.php');

	expect(strpos($settings, "'path_composer'"))->not->toBeFalse('setting must exist in global_settings')
		->and(strpos($install, "install_tool_path('composer'"))->not->toBeFalse('installer must seed the same setting');
});
