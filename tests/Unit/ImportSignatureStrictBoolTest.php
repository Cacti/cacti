<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * import_validate_signature() reports a strict boolean and is enforced at the
 * top of import_package(), so a Package whose key Cacti does not trust cannot be
 * imported (GHSA-274c-97hj-pv2v). Returning a populated array would let
 * `if (!import_validate_signature(...))` read a failed check as a pass.
 */

$src = file_get_contents(dirname(__DIR__, 2) . '/lib/import.php');

test('import_validate_signature is declared to return bool', function () use ($src) {
	expect($src)->toContain('function import_validate_signature($xmlfile) : bool {');
	expect($src)->toContain('is_cacti_public_key(trim((string) $data[\'public_key\']))');
});

test('a package with no key is not trusted (no default-key substitution)', function () use ($src) {
	$start = strpos($src, 'function import_validate_signature(');
	$body  = substr($src, $start, strpos($src, "\n}", $start) - $start);

	// must read the raw details, not import_package_get_public_key() (which
	// substitutes Cacti's official key when <publickey> is absent)
	expect($body)->toContain('import_package_get_details($xmlfile)');
	expect($body)->not->toContain('import_package_get_public_key(');
	// an absent or empty key returns false
	expect($body)->toContain("!isset(\$data['public_key']) || trim((string) \$data['public_key']) === ''");
});

test('import_package enforces the signature before reading the package', function () use ($src) {
	$guard = strpos($src, 'if (!import_validate_signature($xmlfile))');
	$read  = strpos($src, '$data = import_read_package_data($xmlfile, $public_key);');

	expect($guard)->not->toBeFalse();
	expect($read)->not->toBeFalse();
	expect($guard)->toBeLessThan($read);
});
