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
require_once dirname(__DIR__, 2) . '/include/global.php';

// =====================================================================
// html_escape tests
// =====================================================================

test('html_escape returns plain text unchanged', function () {
	expect(html_escape('hello world'))->toBe('hello world');
});

test('html_escape encodes angle brackets', function () {
	expect(html_escape('<script>alert(1)</script>'))->toContain('&lt;')
		->and(html_escape('<script>alert(1)</script>'))->toContain('&gt;');
});

test('html_escape encodes double quotes', function () {
	expect(html_escape('say "hello"'))->toContain('&quot;');
});

test('html_escape encodes single quotes', function () {
	$result = html_escape("it's");

	/* ENT_QUOTES | ENT_HTML5 produces &apos; */
	expect($result)->toContain('&apos;');
});

test('html_escape replaces backtick with entity', function () {
	expect(html_escape('a`b'))->toContain('&#96;');
});

test('html_escape returns empty string for empty input', function () {
	expect(html_escape(''))->toBe('');
});

test('html_escape returns empty string for null input', function () {
	expect(html_escape(null))->toBe('');
});

test('html_escape does not double-encode existing entities', function () {
	expect(html_escape('&amp;'))->toBe('&amp;');
});

test('html_escape handles ampersand in plain text', function () {
	expect(html_escape('a&b'))->toBe('a&amp;b');
});

// =====================================================================
// html_split_string tests
// =====================================================================

test('html_split_string returns short string unchanged', function () {
	expect(html_split_string('short string', 90))->toBe('short string');
});

test('html_split_string breaks long string at word boundary', function () {
	$words = 'The quick brown fox jumps over the lazy dog and keeps running far away';

	$result = html_split_string($words, 30, 10);

	expect($result)->toContain('<br>');
});

test('html_split_string respects forgiveness parameter', function () {
	/* 40 chars no space, then a space, then more */
	$str = str_repeat('a', 35) . ' ' . str_repeat('b', 20);

	$result = html_split_string($str, 40, 10);

	expect($result)->toContain('<br>');
});

test('html_split_string limits iterations to 5 maximum', function () {
	$long = str_repeat('word ', 200);

	$result = html_split_string($long, 20, 10);

	$breaks = substr_count($result, '<br>');

	/* loop runs j=0..4 (5 iterations), breaks when j>4, so up to 5 <br> tags */
	expect($breaks)->toBeLessThanOrEqual(5);
});
