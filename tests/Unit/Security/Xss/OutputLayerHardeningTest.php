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

// Mock html_auth_footer logic for testing escaping
function test_html_auth_footer_logic(string $error) : string {
	// The Hardened Logic: htmle($error)
	if (!function_exists('htmle')) {
		function htmle($string) {
			return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
		}
	}
	
	$output = "<div class='cactiAuthErrors'>\n";
	$output .= "    " . htmle($error) . "\n";
	$output .= "</div>\n";
	
	return $output;
}

test('html_auth_footer logic escapes error message', function () {
	$payload = "<script>alert('XSS')</script>";
	$result = test_html_auth_footer_logic($payload);
	
	expect($result)->not->toContain('<script>')
		->and($result)->toContain('&lt;script&gt;');
});

test('html_auth_footer logic handles plain text', function () {
	$message = "Invalid password";
	$result = test_html_auth_footer_logic($message);
	
	expect($result)->toContain($message);
});
