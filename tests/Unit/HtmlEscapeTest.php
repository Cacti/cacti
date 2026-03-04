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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/html.php';

// --- html_escape ---

test('html_escape encodes angle brackets', function () {
	expect(html_escape('<script>alert(1)</script>'))
		->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('html_escape encodes double quotes', function () {
	expect(html_escape('a "quoted" value'))->toContain('&quot;');
});

test('html_escape encodes single quotes', function () {
	$result = html_escape("it's a test");
	// ENT_HTML5 produces &apos; rather than &#039;
	expect($result)->toContain('&apos;');
});

test('html_escape encodes grave accent to prevent XSS', function () {
	expect(html_escape('test`injection'))->toContain('&#96;');
});

test('html_escape returns empty string for empty input', function () {
	expect(html_escape(''))->toBe('');
});

test('html_escape returns empty string for null-like input', function () {
	expect(html_escape())->toBe('');
});

test('html_escape does not double-encode existing entities', function () {
	expect(html_escape('&amp;'))->toBe('&amp;');
});

test('html_escape preserves plain alphanumeric text', function () {
	expect(html_escape('Hello World 123'))->toBe('Hello World 123');
});

test('html_escape handles ampersand', function () {
	expect(html_escape('foo & bar'))->toBe('foo &amp; bar');
});

// --- html_split_string ---

test('html_split_string returns short string unchanged', function () {
	expect(html_split_string('short', 90))->toBe('short');
});

test('html_split_string breaks long string at word boundary', function () {
	$long = 'the quick brown fox jumps over the lazy dog and keeps running forever';
	$result = html_split_string($long, 30, 10);
	expect($result)->toContain('<br>');
});

test('html_split_string handles string with no spaces', function () {
	$long = str_repeat('x', 200);
	$result = html_split_string($long, 50, 10);
	// No word boundaries found, so chars outside the forgiveness window are dropped
	expect(strlen($result))->toBeLessThan(strlen($long));
});

test('html_split_string handles empty string', function () {
	expect(html_split_string('', 90))->toBe('');
});
