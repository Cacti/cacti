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
// title_trim tests
// =====================================================================

test('title_trim returns short string unchanged', function () {
	expect(title_trim('hello', 10))->toBe('hello');
});

test('title_trim returns string at exact max length unchanged', function () {
	expect(title_trim('abcde', 5))->toBe('abcde');
});

test('title_trim truncates long string with ellipsis', function () {
	expect(title_trim('hello world foo', 5))->toBe('hello...');
});

test('title_trim handles empty string', function () {
	expect(title_trim('', 10))->toBe('');
});

test('title_trim handles multibyte characters', function () {
	$text = str_repeat("\xC3\xA9", 10); // 10 x 'é' (2 bytes each)

	$result = title_trim($text, 5);

	expect(mb_substr($result, 0, -3, 'UTF-8'))->toHaveLength(5);
	expect($result)->toEndWith('...');
});

test('title_trim with max_length of 1', function () {
	expect(title_trim('ab', 1))->toBe('a...');
});

// =====================================================================
// sanitize_search_string tests
// =====================================================================

test('sanitize_search_string passes plain text through', function () {
	expect(sanitize_search_string('simple search'))->toBe('simple search');
});

test('sanitize_search_string strips parentheses', function () {
	$result = sanitize_search_string('test(value)');

	expect($result)->not->toContain('(')
		->and($result)->not->toContain(')');
});

test('sanitize_search_string replaces angle brackets with spaces', function () {
	expect(sanitize_search_string('a<b>c'))->toBe('a b c');
});

test('sanitize_search_string strips backticks and single quotes', function () {
	$result = sanitize_search_string("it`s it's");

	expect($result)->not->toContain('`')
		->and($result)->not->toContain("'");
});

test('sanitize_search_string replaces pipes and commas with spaces', function () {
	expect(sanitize_search_string('a|b,c'))->toBe('a b c');
});

test('sanitize_search_string strips brackets and braces', function () {
	$result = sanitize_search_string('arr[0]{key}');

	expect($result)->not->toContain('[')
		->and($result)->not->toContain(']')
		->and($result)->not->toContain('{')
		->and($result)->not->toContain('}');
});

test('sanitize_search_string strips hash semicolon bang equals star', function () {
	$result = sanitize_search_string('a#b;c!d=e*f');

	expect($result)->not->toContain('#')
		->and($result)->not->toContain(';')
		->and($result)->not->toContain('!')
		->and($result)->not->toContain('=')
		->and($result)->not->toContain('*');
});

test('sanitize_search_string replaces line endings with space', function () {
	expect(sanitize_search_string("line1\nline2\rline3"))->toBe('line1 line2 line3');
});

test('sanitize_search_string strips HTML entities', function () {
	expect(sanitize_search_string('foo&nbsp;bar'))->toBe('foo bar');
});

test('sanitize_search_string strips URLs', function () {
	$result = sanitize_search_string('visit http://example.com/path today');

	expect($result)->not->toContain('http')
		->and($result)->toContain('visit')
		->and($result)->toContain('today');
});

test('sanitize_search_string handles empty string', function () {
	expect(sanitize_search_string(''))->toBe('');
});

// =====================================================================
// clean_up_lines tests
// =====================================================================

test('clean_up_lines joins multiple lines into one', function () {
	expect(clean_up_lines("line1\nline2\nline3"))->toBe('line1 line2 line3');
});

test('clean_up_lines returns null for null input', function () {
	expect(clean_up_lines(null))->toBeNull();
});

test('clean_up_lines returns single line unchanged', function () {
	expect(clean_up_lines('no newlines here'))->toBe('no newlines here');
});

test('clean_up_lines normalizes mixed line endings', function () {
	expect(clean_up_lines("a\r\nb\rc"))->toBe('a b c');
});

test('clean_up_lines collapses surrounding whitespace around newlines', function () {
	expect(clean_up_lines("a  \n  b"))->toBe('a b');
});

// =====================================================================
// clean_up_name tests
// =====================================================================

test('clean_up_name returns alphanumeric unchanged', function () {
	expect(clean_up_name('MyHost01'))->toBe('MyHost01');
});

test('clean_up_name replaces spaces with underscores', function () {
	expect(clean_up_name('my host name'))->toBe('my_host_name');
});

test('clean_up_name replaces dots with underscores', function () {
	expect(clean_up_name('host.example.com'))->toBe('host_example_com');
});

test('clean_up_name strips special characters', function () {
	expect(clean_up_name('host@#$%!'))->toBe('host');
});

test('clean_up_name collapses multiple underscores', function () {
	expect(clean_up_name('a   b'))->toBe('a_b');
});

test('clean_up_name returns null for null input', function () {
	expect(clean_up_name(null))->toBeNull();
});

// =====================================================================
// clean_up_file_name tests
// =====================================================================

test('clean_up_file_name returns simple name unchanged', function () {
	expect(clean_up_file_name('myfile'))->toBe('myfile');
});

test('clean_up_file_name preserves hyphens', function () {
	expect(clean_up_file_name('my-file-name'))->toBe('my-file-name');
});

test('clean_up_file_name replaces spaces with underscores', function () {
	expect(clean_up_file_name('my file name'))->toBe('my_file_name');
});

test('clean_up_file_name strips special characters but keeps hyphens', function () {
	expect(clean_up_file_name('file@name#1-test'))->toBe('filename1-test');
});

test('clean_up_file_name returns null for null input', function () {
	expect(clean_up_file_name(null))->toBeNull();
});

// =====================================================================
// is_valid_pathname tests
// =====================================================================

test('is_valid_pathname accepts valid unix path', function () {
	expect(is_valid_pathname('/usr/local/cacti/rra'))->toBeTrue();
});

test('is_valid_pathname rejects windows path with spaces', function () {
	/* production regex does not allow spaces, even in Windows paths */
	expect(is_valid_pathname('C:\\Program Files\\Cacti\\rra'))->toBeFalse();
});

test('is_valid_pathname accepts windows path without spaces', function () {
	expect(is_valid_pathname('C:\\Cacti\\rra'))->toBeTrue();
});

test('is_valid_pathname accepts relative path with dots', function () {
	expect(is_valid_pathname('./relative/path'))->toBeTrue();
});

test('is_valid_pathname rejects path with spaces', function () {
	expect(is_valid_pathname('/path with spaces/file'))->toBeFalse();
});

test('is_valid_pathname rejects path with shell metacharacters', function () {
	expect(is_valid_pathname('/path;rm -rf /'))->toBeFalse();
});

test('is_valid_pathname rejects path with backticks', function () {
	expect(is_valid_pathname('/path/`whoami`'))->toBeFalse();
});

test('is_valid_pathname rejects empty string', function () {
	expect(is_valid_pathname(''))->toBeFalse();
});

// =====================================================================
// is_base64_encoded tests
// =====================================================================

test('is_base64_encoded accepts valid base64', function () {
	$encoded = base64_encode('hello world');

	expect(is_base64_encoded($encoded))->toBeTrue();
});

test('is_base64_encoded accepts base64 with padding', function () {
	$encoded = base64_encode('ab');  // produces 'YWI='

	expect(is_base64_encoded($encoded))->toBeTrue();
});

test('is_base64_encoded rejects string with invalid characters', function () {
	expect(is_base64_encoded('not!valid@base64'))->toBeFalse();
});

test('is_base64_encoded rejects plain text that looks like base64', function () {
	expect(is_base64_encoded('abcdef'))->toBeFalse();
});

test('is_base64_encoded accepts empty string', function () {
	/* empty string base64-encodes to empty string */
	expect(is_base64_encoded(''))->toBeTrue();
});

// =====================================================================
// generate_hash tests
// =====================================================================

test('generate_hash returns 32-character hex string', function () {
	$hash = generate_hash();

	expect($hash)->toHaveLength(32)
		->and(ctype_xdigit($hash))->toBeTrue();
});

test('generate_hash returns unique values', function () {
	$a = generate_hash();
	$b = generate_hash();

	expect($a)->not->toBe($b);
});
