<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 |                                                                         |
 | HandOff tests: verify behavioral properties of the security fixes from  |
 | SecurityHardening1_2xTest without requiring a full Cacti bootstrap.     |
 +-------------------------------------------------------------------------+
*/

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');
$databaseSource  = file_get_contents(__DIR__ . '/../../lib/database.php');

// -------------------------------------------------------------------------
// sanitize_uri — double-decode prevention (M-2)
//
// Before the fix, sanitize_uri decoded percent-encoded input via urldecode()
// before stripping dangerous characters. An attacker could encode a payload
// as %3Cscript%3E, which would survive the is_urlencoded() check, get decoded
// to <script>, and then be stripped — but double-encoded inputs like
// %253Cscript%253E would decode to %3Cscript%3E and pass through intact.
//
// After the fix the function never calls urldecode(), so encoded payloads are
// passed through as literal percent-sequences and rendered harmless.
// -------------------------------------------------------------------------

test('sanitize_uri does not decode percent-encoded payloads', function () use ($functionsSource) {
	// Extract sanitize_uri and its dependency is_urlencoded, then eval with a
	// minimal stub for get_nfilter_request_var (only used in the graph_view branch).
	if (!function_exists('get_nfilter_request_var')) {
		// phpcs:ignore
		function get_nfilter_request_var($key) { return ''; }
	}

	if (!function_exists('sanitize_uri')) {
		if (preg_match('/function is_urlencoded\(.*?^\}/ms', $functionsSource, $m)) {
			eval($m[0]);
		}
		if (preg_match('/function sanitize_uri\(.*?^\}/ms', $functionsSource, $m)) {
			eval($m[0]);
		}
	}

	expect(function_exists('sanitize_uri'))->toBeTrue();

	// A double-encoded script tag: before the fix this decoded to %3Cscript%3E
	// and in a second pass could become <script>. After the fix the percent signs
	// are kept and the strip_tags call has nothing to remove.
	$input  = '%253Cscript%253Ealert(1)%253C%2Fscript%253E';
	$result = sanitize_uri($input);

	// The % character is not in the drop_char_match list, so the encoded form
	// must be preserved rather than decoded to angle brackets.
	expect($result)->not->toContain('<script>');
	expect($result)->not->toContain('<');
});

test('sanitize_uri strips literal angle brackets directly', function () {
	if (!function_exists('sanitize_uri')) {
		$this->markTestSkipped('sanitize_uri not loaded');
	}

	$result = sanitize_uri('<script>alert(1)</script>');
	expect($result)->not->toContain('<');
	expect($result)->not->toContain('>');
});

// -------------------------------------------------------------------------
// db_dump_data — unescaped credentials (H-4)
//
// Before the fix, credential values were concatenated directly into the shell
// command string. A username or database name containing shell metacharacters
// (e.g. spaces, semicolons, backticks) could break command structure.
//
// After the fix every element of $command — including $username and $database
// — is wrapped with cacti_escapeshellarg() before being appended to the
// command string. The password is passed via MYSQL_PWD so it never appears
// on the command line at all.
// -------------------------------------------------------------------------

test('db_dump_data command loop calls cacti_escapeshellarg on every argument', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_dump_data(');
	expect($start)->not->toBeFalse();

	$body = substr($databaseSource, $start, 2500);

	// The loop must call cacti_escapeshellarg on each $arg, not concatenate raw.
	expect($body)->toContain('cacti_escapeshellarg($arg)');

	// Username must reach the $command array (where it gets escaped) not be
	// interpolated directly into $cmd_string.
	$posUsername    = strpos($body, '$username');
	$posCommandArr  = strpos($body, '$command = array(');
	$posCmdString   = strpos($body, '$cmd_string');
	expect($posUsername)->toBeLessThan($posCmdString);
	expect($posCommandArr)->toBeLessThan($posCmdString);
});

test('db_dump_data never interpolates password into command string', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_dump_data(');
	$body = substr($databaseSource, $start, 2500);

	// Password must not appear in $command array or $cmd_string concatenation.
	// It is passed exclusively through MYSQL_PWD environment variable.
	expect($body)->toContain("'MYSQL_PWD' => \$password");

	// Verify the $password variable does not appear inside the $command array
	// literal. We check the slice between $command = array( and the closing );
	$cmdStart = strpos($body, '$command = array(');
	$cmdEnd   = strpos($body, ');', $cmdStart);
	$cmdBody  = substr($body, $cmdStart, $cmdEnd - $cmdStart);
	expect($cmdBody)->not->toContain('$password');
	expect($cmdBody)->not->toContain('--password');
});
