<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/html_utility.php';

test('validate_redirect_url must fail on protocol-relative (Mutation Protection)', function () {
	// If a developer removes 'if (str_starts_with($url, "//"))', this test fails.
	$malicious = '//evil.com';
	expect(validate_redirect_url($malicious))->toBe('index.php');
});

test('validate_redirect_url must fail on javascript scheme (Mutation Protection)', function () {
	// If a developer removes 'javascript:' from bad_strings, this test fails.
	$malicious = 'javascript:alert(1)';
	expect(validate_redirect_url($malicious))->toBe('index.php');
});
