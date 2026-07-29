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
 * package_import.php previously let a request set trust_signer=on to skip
 * package_validate_signature() entirely and call import_validate_public_key()
 * with a forced-trust flag instead, so any request could bootstrap trust for
 * an author it had never verified. The fix removes the control end to end
 * (request var validation, the hidden form field, the JS that set it, and
 * the form_save() branch that read it), leaving package_validate_signature()
 * as the only gate. Structural check: none of those pieces may still exist.
 */

$repoRoot = __DIR__ . '/../..';

test('form_save() always gates on package_validate_signature(), no bypass branch', function () use ($repoRoot) {
	$src = file_get_contents("$repoRoot/package_import.php");

	expect($src)->toContain('if (!package_validate_signature($xmlfile)) {');
	expect($src)->not->toContain('isset_request_var(\'trust_signer\')');
});

test('trust_signer is gone from validate_request_vars(), the form, and the JS', function () use ($repoRoot) {
	$src = file_get_contents("$repoRoot/package_import.php");

	expect($src)->not->toContain('trust_signer');
});
