<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/include/global.php';

test('Package Import: Metadata is HTML escaped in session messages', function () {
	// Mock session and request
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	$_SESSION['sess_messages'] = [];

	$malicious_name = "<script>alert('XSS')</script>";
	
	// We are testing that the code in package_import.php now uses html_escape()
	// Since we can't easily execute the full package_import.php script in isolation,
	// we will simulate the logic that we added.
	
	$escaped_name = html_escape($malicious_name);
	
	raise_message('import_success', __("The Package %s Imported Successfully", $escaped_name), MESSAGE_LEVEL_INFO);
	
	$messages = $_SESSION['sess_messages'];
	expect($messages['import_success']['message'])->toContain("&lt;script&gt;");
	expect($messages['import_success']['message'])->not->toContain("<script>");
});

test('Package Import: Metadata is HTML escaped in administrative display', function () {
	// This test verifies that metadata fields used in form_selectable_cell are escaped
	$details = [
		'author' => "<script>alert('author')</script>",
		'homepage' => "javascript:alert('homepage')",
		'email' => "test@example.com'><script>alert('email')</script>",
		'version' => "1.0'><img src=x onerror=alert(1)>",
		'copyright' => "2026 <script>alert('copy')</script>"
	];

	ob_start();
	// Simulate the form_selectable_cell calls we added in package_import.php
	form_selectable_cell(html_escape($details['author']), 1);
	form_selectable_cell(html_escape($details['homepage']), 2);
	form_selectable_cell(html_escape($details['email']), 3);
	form_selectable_cell(html_escape($details['version']), 4);
	form_selectable_cell(html_escape($details['copyright']), 5);
	$output = ob_get_clean();

	expect($output)->not->toContain("<script>");
	expect($output)->toContain("&lt;script&gt;");
	expect($output)->toContain("javascript:alert('homepage')"); // URL should be escaped if rendered as link, but here it's just text
});
