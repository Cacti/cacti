<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Coverage backfill for PR #7150. db_fetch_cell_return() used to read
 * $r[0][$col_name] unconditionally, emitting a PHP 8 undefined-index
 * notice and returning null when the column was absent. The fix wraps
 * the read in array_key_exists() and logs the missing column name to
 * DBCALL so the call site can be traced.
 */

$source = file_get_contents(__DIR__ . '/../../lib/database.php');

function _extract_db_fetch_cell_return_body(string $src): string {
	$start = strpos($src, 'function db_fetch_cell_return(');
	if ($start === false) {
		return '';
	}

	$brace = strpos($src, '{', $start);
	$depth = 1;
	$i     = $brace + 1;
	$len   = strlen($src);

	while ($depth > 0 && $i < $len) {
		$ch = $src[$i];
		if ($ch === '{') { $depth++; }
		elseif ($ch === '}') { $depth--; }
		$i++;
	}

	return substr($src, $brace + 1, $i - $brace - 2);
}

test('db_fetch_cell_return body wraps the column read in array_key_exists', function () use ($source) {
	$body = _extract_db_fetch_cell_return_body($source);
	expect($body)->not->toBe('');
	expect($body)->toContain('array_key_exists($col_name, $r[0])');
	expect($body)->toContain('return $r[0][$col_name];');

	$keyPos = strpos($body, 'array_key_exists($col_name, $r[0])');
	$retPos = strpos($body, 'return $r[0][$col_name];');
	expect($keyPos < $retPos)->toBeTrue();
});

test('miss-path logs the column name to DBCALL and returns false', function () use ($source) {
	$body = _extract_db_fetch_cell_return_body($source);
	expect($body)->toContain('Requested column not found in SQL result');
	expect($body)->toContain("'DBCALL'");

	$keyPos = strpos($body, 'array_key_exists($col_name, $r[0])');
	$retInner = strpos($body, 'return $r[0][$col_name];');
	$logPos = strpos($body, 'Requested column not found in SQL result');
	$retOuter = strpos($body, 'return false;');

	expect($keyPos < $retInner)->toBeTrue();
	expect($retInner < $logPos)->toBeTrue();
	expect($logPos < $retOuter)->toBeTrue();
});
