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

// --- title_trim ---

test('title_trim returns original string when shorter than max', function () {
	expect(title_trim('short', 10))->toBe('short');
});

test('title_trim returns original string when exactly max length', function () {
	expect(title_trim('12345', 5))->toBe('12345');
});

test('title_trim truncates and appends ellipsis when over max', function () {
	expect(title_trim('this is a long string', 7))->toBe('this is...');
});

test('title_trim handles empty string', function () {
	expect(title_trim('', 10))->toBe('');
});

test('title_trim with max length of 1 truncates to 1 char plus ellipsis', function () {
	expect(title_trim('hello', 1))->toBe('h...');
});

// --- clean_up_lines ---

test('clean_up_lines collapses newlines into single space', function () {
	expect(clean_up_lines("line1\nline2\nline3"))->toBe('line1 line2 line3');
});

test('clean_up_lines collapses carriage returns', function () {
	expect(clean_up_lines("line1\r\nline2"))->toBe('line1 line2');
});

test('clean_up_lines collapses multiple blank lines', function () {
	expect(clean_up_lines("a\n\n\nb"))->toBe('a b');
});

test('clean_up_lines strips surrounding whitespace around newlines', function () {
	expect(clean_up_lines("a  \n  b"))->toBe('a b');
});

test('clean_up_lines returns null for null input', function () {
	expect(clean_up_lines(null))->toBeNull();
});

test('clean_up_lines returns string unchanged when no newlines', function () {
	expect(clean_up_lines('no newlines here'))->toBe('no newlines here');
});

// --- clean_up_name ---

test('clean_up_name replaces spaces with underscores', function () {
	expect(clean_up_name('my host name'))->toBe('my_host_name');
});

test('clean_up_name replaces dots with underscores', function () {
	expect(clean_up_name('router.east.01'))->toBe('router_east_01');
});

test('clean_up_name strips special characters', function () {
	expect(clean_up_name('host@#$%name'))->toBe('hostname');
});

test('clean_up_name collapses multiple underscores', function () {
	expect(clean_up_name('a...b'))->toBe('a_b');
});

test('clean_up_name returns null for null input', function () {
	expect(clean_up_name(null))->toBeNull();
});

test('clean_up_name handles already clean alphanumeric string', function () {
	expect(clean_up_name('CleanHost01'))->toBe('CleanHost01');
});

// --- clean_up_file_name ---

test('clean_up_file_name replaces spaces and dots with underscores', function () {
	expect(clean_up_file_name('my file.txt'))->toBe('my_file_txt');
});

test('clean_up_file_name preserves hyphens', function () {
	expect(clean_up_file_name('my-file'))->toBe('my-file');
});

test('clean_up_file_name strips special characters but keeps hyphens', function () {
	expect(clean_up_file_name('file@name#1'))->toBe('filename1');
});

test('clean_up_file_name returns null for null input', function () {
	expect(clean_up_file_name(null))->toBeNull();
});

test('clean_up_file_name collapses multiple underscores', function () {
	expect(clean_up_file_name('a...b...c'))->toBe('a_b_c');
});

// --- sanitize_search_string ---

test('sanitize_search_string strips parentheses and backticks', function () {
	$result = sanitize_search_string('test(foo)bar`baz');
	expect($result)->not->toContain('(')
		->and($result)->not->toContain(')')
		->and($result)->not->toContain('`');
});

test('sanitize_search_string replaces angle brackets with spaces', function () {
	expect(sanitize_search_string('a<b>c'))->toBe('a b c');
});

test('sanitize_search_string strips HTML entities', function () {
	expect(sanitize_search_string('foo&nbsp;bar'))->toBe('foo bar');
});

test('sanitize_search_string replaces newlines with spaces', function () {
	expect(sanitize_search_string("line1\nline2"))->toBe('line1 line2');
});

test('sanitize_search_string strips URLs', function () {
	$result = sanitize_search_string('visit http://example.com/path today');
	expect($result)->not->toContain('http://');
});

test('sanitize_search_string strips square brackets', function () {
	expect(sanitize_search_string('test[0]'))->toBe('test 0 ');
});

test('sanitize_search_string passes through clean alphanumeric', function () {
	expect(sanitize_search_string('simple search 123'))->toBe('simple search 123');
});

test('sanitize_search_string handles empty string', function () {
	expect(sanitize_search_string(''))->toBe('');
});

// --- is_valid_pathname ---

test('is_valid_pathname accepts simple unix path', function () {
	expect(is_valid_pathname('/usr/local/bin/cacti'))->toBeTrue();
});

test('is_valid_pathname accepts windows path without spaces', function () {
	expect(is_valid_pathname('C:\\Cacti\\rra'))->toBeTrue();
});

test('is_valid_pathname accepts relative path with dots', function () {
	expect(is_valid_pathname('./scripts/poller.php'))->toBeTrue();
});

test('is_valid_pathname accepts filename with hyphens and underscores', function () {
	expect(is_valid_pathname('my_script-v2.sh'))->toBeTrue();
});

test('is_valid_pathname rejects path with spaces', function () {
	expect(is_valid_pathname('/path with spaces/file'))->toBeFalse();
});

test('is_valid_pathname rejects path with semicolon', function () {
	expect(is_valid_pathname('/bin/cmd;rm -rf /'))->toBeFalse();
});

test('is_valid_pathname rejects path with backtick', function () {
	expect(is_valid_pathname('/bin/`whoami`'))->toBeFalse();
});

test('is_valid_pathname rejects path with pipe', function () {
	expect(is_valid_pathname('/bin/cmd|cat /etc/passwd'))->toBeFalse();
});

test('is_valid_pathname rejects empty string', function () {
	expect(is_valid_pathname(''))->toBeFalse();
});

// --- is_base64_encoded ---

test('is_base64_encoded accepts valid base64 string', function () {
	$encoded = base64_encode('Hello, World!');
	expect(is_base64_encoded($encoded))->toBeTrue();
});

test('is_base64_encoded accepts base64 with padding', function () {
	$encoded = base64_encode('ab');
	expect(is_base64_encoded($encoded))->toBeTrue();
});

test('is_base64_encoded rejects plain text', function () {
	expect(is_base64_encoded('not base64!'))->toBeFalse();
});

test('is_base64_encoded rejects string with invalid characters', function () {
	expect(is_base64_encoded('abc$%^&'))->toBeFalse();
});

test('is_base64_encoded accepts empty string as valid base64', function () {
	// Empty string passes regex and round-trips through encode/decode
	expect(is_base64_encoded(''))->toBeTrue();
});

// --- generate_hash ---

test('generate_hash returns 32 character hex string', function () {
	$hash = generate_hash();
	expect(strlen($hash))->toBe(32)
		->and(ctype_xdigit($hash))->toBeTrue();
});

test('generate_hash produces unique values', function () {
	$hash1 = generate_hash();
	$hash2 = generate_hash();
	expect($hash1)->not->toBe($hash2);
});
