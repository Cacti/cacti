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
 * Refs #8555: a translation printed inside a single-quoted JavaScript string
 * ends the string at its first apostrophe and the rest of the <script> block
 * never runs. The shipped fr-FR and it-IT catalogs translate each string below
 * with an apostrophe, so each one must be emitted as a JSON string literal.
 */

$root = dirname(__DIR__, 4);

dataset('translated_script_strings', [
	'package key prompt (package_import.php)' => [
		'package_import.php',
		'In order to use this Packaging Tool, you must first run the <b><i class="deviceUp">genkey.php</i></b> script in the cli directory.',
	],
	'package key prompt (lib/package.php)' => [
		'lib/package.php',
		'In order to use this Packaging Tool, you must first run the <b><i class="deviceUp">genkey.php</i></b> script in the cli directory.',
	],
	'SNMP option item delete dialog' => [
		'automation_snmp.php',
		'Delete SNMP Option Item',
	],
	'tree rule leaf type warning' => [
		'automation_tree_rules.php',
		'You are changing the leaf type to "Device" which does not support Graph-based object matching/creation.',
	],
]);

test('translated strings in inline scripts are emitted as JSON literals', function (string $file, string $msgid) use ($root) {
	$source = file_get_contents($root . '/' . $file);

	expect($source)->toContain("<?php print json_encode(__('" . $msgid)
		->and($source)->not->toContain("'<?php print __('" . $msgid);
})->with('translated_script_strings');

test('the tree rule leaf type dialog has no single-quoted translations left', function () use ($root) {
	$source = file_get_contents($root . '/automation_tree_rules.php');

	$start = strpos($source, 'var automationLeafTypeButtons');
	$end   = strpos($source, "'</div>';", (int) $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	expect(substr($source, (int) $start, (int) $end - (int) $start))
		->not->toContain("'<?php print __(");
});

test('a JSON encoded translation keeps its apostrophe inside the literal', function () {
	$encoded = json_encode("Pour utiliser cet outil d'empaquetage");

	expect($encoded)->toStartWith('"')
		->and($encoded)->toEndWith('"')
		->and(json_decode($encoded))->toBe("Pour utiliser cet outil d'empaquetage");
});
