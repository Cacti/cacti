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
 * The signature line of a Package is replaced by an empty signature before
 * the text is verified, so anything else on that line would not be covered
 * by the signature. The test builds Packages signed with a key of its own,
 * which the probe accepts as the trusted key, and runs the unchanged
 * import_package() and Package readers of lib/import.php.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';
require_once dirname(__DIR__, 3) . '/Helpers/SignedPackage.php';

function package_signature_import(?callable $tamper): array {
	$package = cacti_test_signed_package(array('Demo.xml' => '<cacti></cacti>'), $tamper);

	try {
		return cacti_import_export_probe(array(
			'slice'      => cacti_test_package_reader_slices(),
			'public_key' => $package['public_key'],
			'returns'    => array('import_xml_data' => array('host_template' => array())),
			'call'       => 'import_package',
			'arguments'  => array($package['file'], 1, false, false, true, false, false),
		));
	} finally {
		unlink($package['file']);
	}
}

test('a signed Package is read', function (?callable $tamper) {
	$verdict = package_signature_import($tamper);

	expect($verdict['error'])->toBeNull()
		->and($verdict['result'][0])->toBe(array('host_template' => array()));
})->with(array(
	'as signed'                  => array(null),
	'with Windows line endings'  => array(fn ($xml) => str_replace("</signature>\n", "</signature>\r\n", $xml)),
));

test('a Package with other content on its signature line is refused', function (callable $tamper) {
	$verdict = package_signature_import($tamper);

	expect($verdict['error'])->toBeNull()
		->and($verdict['result'])->toBeFalse()
		->and(cacti_import_export_called($verdict))->not->toContain('import_xml_data');
})->with(array(
	'element before' => array(fn ($xml) => str_replace('   <signature>', '   <class>other</class><signature>', $xml)),
	'element after'  => array(fn ($xml) => preg_replace('#</signature>\n#', "</signature><class>other</class>\n", $xml)),
	/* markup without base64 characters leaves the extracted signature intact */
	'markup outside the signature alphabet' => array(fn ($xml) => str_replace('   <signature>', '   <?_?><!----><signature>', $xml)),
));
