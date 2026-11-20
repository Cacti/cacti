<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Password fields drawn with 'secret' set never write the stored value into
 * the page. A blank submission keeps the stored value, a typed value replaces
 * it, and only the field's '_clear' checkbox blanks it.
 *
 * The checks run in a child process (tests/fixtures/SecretFieldMatrix.php):
 * lib/html_form.php and the page save code want helpers that read globals or
 * the database, and stubbing them here would leak into the rest of the suite.
 */

test('settings, data collector and LDAP domain passwords are not rendered and are kept, replaced or cleared on save', function () {
	$output = array();
	$status = 0;

	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/fixtures/SecretFieldMatrix.php') . ' 2>&1', $output, $status);

	expect($status)->toBe(0, implode("\n", $output));
	expect(json_decode(implode("\n", $output), true))->toBe(array('passed' => 20));
});
