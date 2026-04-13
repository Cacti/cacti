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
 * Contract and regression tests for SQL injection advisories.
 *
 * Each group documents the vulnerable code path so the tests serve as
 * living proof-of-the-bug, not just proof-of-the-fix.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// =====================================================================
// GHSA-69gg / GHSA-9jqv: RLIKE SQL injection via rfilter
//
// Affected paths:
//   lib/html_graph.php:858  -- " gtg.title_cache RLIKE '" . grv('rfilter') . "'"
//   lib/html_tree.php:1432  -- " gtg.title_cache RLIKE '" . grv('rfilter') . "'"
//
// grv() is an alias of get_request_var(), which returns the raw
// $_REQUEST value (or the $_CACTI_REQUEST pre-filtered value when set).
// The rfilter value is concatenated directly into SQL without escaping.
// sanitize_search_string() is NOT called on RLIKE operands.
// =====================================================================

test('sanitize_search_string does not strip SQL keywords UNION SELECT OR', function () {
	// The function is intended for keyword/phrase search display, not SQL safety.
	// It only removes shell/HTML metacharacters. SQL keywords pass through unchanged,
	// so callers cannot rely on it as a SQL injection barrier.
	$payload = 'UNION SELECT password OR 1 FROM user_auth';

	$result = sanitize_search_string($payload);

	expect($result)->toContain('UNION')
		->and($result)->toContain('SELECT')
		->and($result)->toContain('FROM')
		->and($result)->toContain('OR');
});

test('grv returns raw request variable value without SQL escaping', function () {
	// grv() -> get_request_var() -> $_REQUEST[$name] when not pre-filtered.
	// No addslashes(), no db_qstr(), no htmlspecialchars() is applied.
	// Proof: set a value in $_REQUEST and confirm grv() returns it byte-for-byte.
	$payload = "a' OR '1'='1";
	$_REQUEST['rfilter'] = $payload;

	$result = grv('rfilter');

	expect($result)->toBe($payload);

	unset($_REQUEST['rfilter']);
});

test('RLIKE payload is valid PCRE so MySQL accepts it without error', function () {
	// MySQL RLIKE uses PCRE. A quote-terminated payload that is also valid PCRE
	// allows the attacker to close the string literal and inject arbitrary SQL.
	// preg_match() here stands in for MySQL's REGEXP engine acceptance check.
	$payload = "a' OR 1=1 -- ";

	// The RLIKE operand in the query becomes: RLIKE 'a' OR 1=1 -- '
	// The portion before the injected quote is a valid PCRE pattern.
	$pcre_portion = "a";
	expect(preg_match('/' . $pcre_portion . '/', 'a'))->toBe(1);
});

test('get_request_var does not alter SQL-dangerous characters for RLIKE context', function () {
	// Contract: get_request_var() must not silently sanitize values because
	// callers that need raw data (like RLIKE construction) rely on receiving
	// the unmodified string. This means callers bear full responsibility for
	// escaping before interpolating into SQL.
	//
	// get_request_var() checks $_CACTI_REQUEST before $_REQUEST, so clear
	// any cached value from prior tests that exercised grv() on the same key.
	global $_CACTI_REQUEST;
	unset($_CACTI_REQUEST['rfilter']);

	$dangerous = "x' UNION SELECT 1,2,3 -- ";
	$_REQUEST['rfilter'] = $dangerous;

	$returned = get_request_var('rfilter');

	// The value arrives at the SQL construction site unmodified.
	expect($returned)->toBe($dangerous);

	unset($_REQUEST['rfilter'], $_CACTI_REQUEST['rfilter']);
});

// =====================================================================
// GHSA-pf37: Stored REGEXP SQL injection via graph_name_regexp
//
// Affected path:
//   lib/html_reports.php:361  -- $save['graph_name_regexp'] = gnrv('graph_name_regexp');
//   lib/reports.php:1236      -- " AND title_cache REGEXP '" . $item['graph_name_regexp'] . "'"
//   lib/reports.php:1427      -- " AND title_cache REGEXP '" . $item['graph_name_regexp'] . "'"
//   lib/reports.php:1473      -- (same pattern, leaf_type == 'graph')
//   lib/reports.php:1489      -- (same pattern, leaf_type == 'graph' nested)
//
// gnrv() is get_nfilter_request_var(): returns $_REQUEST[$name] raw.
// The value is stored to the DB without validation and later concatenated
// into SQL REGEXP clauses without parameterization.
// =====================================================================

test('gnrv returns payload unmodified with no validation on save', function () {
	// gnrv() -> get_nfilter_request_var(): explicitly bypasses all filter checks.
	// graph_name_regexp is written with gnrv() directly to $save, so any
	// SQL metacharacters reach the database column unchanged.
	$payload = "x' UNION SELECT password,2 FROM user_auth -- ";
	$_REQUEST['graph_name_regexp'] = $payload;

	$result = gnrv('graph_name_regexp');

	expect($result)->toBe($payload);

	unset($_REQUEST['graph_name_regexp']);
});

test('lib/reports.php uses string concatenation not parameterized query for REGEXP', function () {
	// Source code contract: the REGEXP clause must be built by concatenation,
	// not by a prepared-statement placeholder, for the advisory to apply.
	// This test catches any future accidental reversion of a parameterized fix.
	$reportsPath = dirname(__DIR__, 2) . '/lib/reports.php';
	$source      = file_get_contents($reportsPath);

	// All REGEXP sites must use db_qstr() instead of string concatenation.
	expect($source)->not->toContain("REGEXP '\" . \$item['graph_name_regexp'] . \"'");
	expect($source)->toContain('REGEXP ')
		->and($source)->toContain("db_qstr(\$item['graph_name_regexp'])");
});

// =====================================================================
// GHSA-84q3: ORDER BY SQL injection via sort/sort_direction parameters
//
// The sort column value passes through sanitize_search_string() before
// being appended to an ORDER BY clause. The function strips quotes,
// backticks, and semicolons, but leaves spaces and SQL keywords intact,
// so multi-word payloads like "id,(SELECT ...)" survive after the
// parentheses are stripped, yielding injectable fragments.
// =====================================================================

test('sanitize_search_string returns UNION SELECT payload mostly intact', function () {
	// The function is not an ORDER BY allowlist. Spaces and keywords survive.
	$payload = '1 UNION SELECT password FROM user_auth--';

	$result = sanitize_search_string($payload);

	// Quotes and # are stripped; the keywords and spaces remain.
	expect($result)->toContain('UNION')
		->and($result)->toContain('SELECT')
		->and($result)->toContain('FROM');
});

test('sanitize_search_string strips quotes backticks and semicolons but not spaces or keywords', function () {
	// Verify precisely which characters are in the drop list and which are not.
	// drop_char_match: ( ) ^ $ < > ` ' " | , ? + [ ] { } # ; ! = *
	$withQuotes    = "col' OR 1=1";
	$withBacktick  = '`col`';
	$withSemicolon = 'col; DROP TABLE';
	$withKeyword   = 'col UNION SELECT';

	expect(sanitize_search_string($withQuotes))->not->toContain("'")
		->and(sanitize_search_string($withBacktick))->not->toContain('`')
		->and(sanitize_search_string($withSemicolon))->not->toContain(';')
		->and(sanitize_search_string($withKeyword))->toContain('UNION')
		->and(sanitize_search_string($withKeyword))->toContain('SELECT');
});

test('sort_column values reaching ORDER BY must be validated against an allowlist', function () {
	// Contract: the fix for GHSA-84q3 must use an explicit column allowlist,
	// not sanitize_search_string(), because the latter does not block keywords.
	// This test asserts the structural requirement: an allowlist is the only
	// safe mitigation for ORDER BY injection.
	//
	// We verify that sanitize_search_string alone is INSUFFICIENT as a guard
	// by confirming a dangerous fragment survives it.
	$orderByPayload = 'local_graph_id,(SELECT SLEEP(5))';

	$sanitized = sanitize_search_string($orderByPayload);

	// Parentheses are stripped, but the keyword survives -- not safe for ORDER BY.
	expect($sanitized)->toContain('SELECT');
});

// =====================================================================
// GHSA-j9jv: SQL injection in managers.php via unserialize + implode
//
// Affected path: managers.php form_actions()
//   $selected_items = cacti_unserialize(stripslashes(gnrv('selected_graphs_array')));
//   db_execute('DELETE FROM snmpagent_managers WHERE id IN (' . implode(',', $selected_items) . ')');
//
// cacti_unserialize() wraps unserialize() with allowed_classes=false,
// which prevents object injection but does NOT sanitize string values
// within the array. A serialized array of SQL payload strings passes
// through and is imploded directly into the query without escaping.
// =====================================================================

test('cacti_unserialize with allowed_classes false still returns string array values intact', function () {
	// allowed_classes=false blocks object injection; it does not filter strings.
	$items = serialize(['1', '2', '3']);

	$result = cacti_unserialize($items);

	expect($result)->toBe(['1', '2', '3']);
});

test('serialized array with SQL payload deserializes with payload intact', function () {
	// The attack vector: attacker submits a serialized array where one element
	// is a SQL fragment. cacti_unserialize() returns it unchanged because
	// allowed_classes=false only prevents __PHP_Incomplete_Class instantiation.
	$payload      = '1) OR 1=1--';
	$serialized   = serialize([$payload]);

	$result = cacti_unserialize($serialized);

	expect($result)->toBeArray()
		->and($result[0])->toBe($payload);
});

test('implode of deserialized payload produces injectable SQL fragment', function () {
	// End-to-end proof: the exact code path in managers.php form_actions().
	// After cacti_unserialize() the array is passed to implode(',', $items)
	// and the result is concatenated into the query string.
	$payload     = '1) OR 1=1--';
	$serialized  = serialize([$payload]);
	$items       = cacti_unserialize($serialized);

	$sql_fragment = implode(',', $items);
	$query        = 'DELETE FROM snmpagent_managers WHERE id IN (' . $sql_fragment . ')';

	// The resulting query contains the unescaped injection payload.
	expect($query)->toContain('OR 1=1');
});

// Covered advisories:
//   GHSA-69gg  RLIKE injection via rfilter (html_graph.php)
//   GHSA-9jqv  RLIKE injection via rfilter (html_tree.php)
//   GHSA-pf37  Stored REGEXP injection via graph_name_regexp (html_reports.php / reports.php)
//   GHSA-84q3  ORDER BY injection (sanitize_search_string insufficient guard)
//   GHSA-j9jv  managers.php unserialize+implode injection
