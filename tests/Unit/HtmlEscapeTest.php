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

	// ENT_QUOTES | ENT_HTML5 produces &apos;
	expect($result)->toContain('&apos;');
});

test('html_escape replaces backtick with entity', function () {
	expect(html_escape('a`b'))->toContain('&#96;');
});

test('html_escape returns empty string for empty input', function () {
	expect(html_escape(''))->toBe('');
});

test('html_escape preserves zero values', function () {
	expect(html_escape('0'))->toBe('0')
		->and(html_escape(0))->toBe('0');
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

test('html_escape_attr encodes attribute delimiters', function () {
	$result = html_escape_attr('" onmouseover="alert(1)');

	expect($result)->toBe('&quot; onmouseover=&quot;alert(1)');
});

test('html_escape_attr encodes backticks', function () {
	expect(html_escape_attr('` autofocus onfocus=alert(1)'))->toBe('&#96; autofocus onfocus=alert(1)');
});

test('html_escape_url encodes URL attribute delimiters', function () {
	expect(html_escape_url('https://example.invalid/?q=" onmouseover="alert(1)'))
		->toBe('https://example.invalid/?q=&quot; onmouseover=&quot;alert(1)');
});

test('cacti_js_encode escapes script-sensitive characters', function () {
	$result = cacti_js_encode("</script><img src=x onerror='alert(1)'>");

	expect($result)->toContain('\u003C')
		->and($result)->toContain('\u0027')
		->and($result)->not->toContain('</script>');
});

test('cacti_url appends encoded query parameters', function () {
	expect(cacti_url('foo.php', [
		'filter' => "a b&c'",
		'page'   => 2,
	]))->toBe('foo.php?filter=a%20b%26c%27&page=2');
});

test('cacti_url preserves existing query strings', function () {
	expect(cacti_url('foo.php?action=view', [
		'filter' => 'a+b',
	]))->toBe('foo.php?action=view&filter=a%2Bb');
});

test('cacti_url inserts query parameters before fragments', function () {
	expect(cacti_url('foo.php#section', [
		'page' => 2,
	]))->toBe('foo.php?page=2#section');

	expect(cacti_url('foo.php?action=view#section', [
		'page' => 2,
	]))->toBe('foo.php?action=view&page=2#section');
});

test('cacti_redirect is used for constructed local page redirects', function () {
	foreach (['graph_templates.php', 'graphs.php', 'vdef.php'] as $page) {
		$source = file_get_contents(__DIR__ . '/../../' . $page);
		expect($source)->not->toBeFalse();
		expect($source)->toContain('cacti_redirect(');
	}
});

test('cacti_script_data renders JSON without exposing script delimiters', function () {
	$result = cacti_script_data('ctx" onmouseover="alert(1)', [
		'message' => "</script><img src=x onerror='alert(1)'>",
	]);

	expect($result)->toContain("type='application/json'")
		->and($result)->toContain("id='ctx&quot; onmouseover=&quot;alert(1)'")
		->and($result)->toContain('\u003C\/script\u003E')
		->and($result)->not->toContain('</script><img');
});

test('html_hidden_input escapes name and value attributes', function () {
	$result = html_hidden_input('selected_hashes', "a' onfocus='alert(1)");

	expect($result)->toBe("<input type='hidden' name='selected_hashes' value='a&apos; onfocus=&apos;alert(1)'>");
});

test('html_text_input escapes value and additional attributes', function () {
	$result = html_text_input('rfilter', "a' autofocus", [
		'class'    => 'ui-state-default',
		'onChange' => 'applyFilter()',
		'bad attr' => 'drop',
	]);

	expect($result)->toContain("value='a&apos; autofocus'")
		->and($result)->toContain("onChange='applyFilter()'")
		->and($result)->not->toContain("badattr='drop'");
});

test('html input helpers preserve zero values', function () {
	expect(html_hidden_input('selected_id', 0))->toContain("value='0'")
		->and(html_text_input('rfilter', '0'))->toContain("value='0'");
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
	// 40 chars no space, then a space, then more
	$str = str_repeat('a', 35) . ' ' . str_repeat('b', 20);

	$result = html_split_string($str, 40, 10);

	expect($result)->toContain('<br>');
});

test('html_split_string limits iterations to 5 maximum', function () {
	$long = str_repeat('word ', 200);

	$result = html_split_string($long, 20, 10);

	$breaks = substr_count($result, '<br>');

	// loop runs j=0..4 (5 iterations), breaks when j>4, so up to 5 <br> tags
	expect($breaks)->toBeLessThanOrEqual(5);
});
