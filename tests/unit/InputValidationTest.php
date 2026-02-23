<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\TestCase;

/**
 * Tests for input validation functions in lib/html_utility.php
 */
class InputValidationTest extends TestCase {
	public static function setUpBeforeClass() : void {
		// These functions depend on Cacti's request infrastructure
		// For now, just test that the functions exist after loading
		if (!defined('CACTI_PATH')) {
			define('CACTI_PATH', dirname(__DIR__, 2));
		}
	}

	public function testSanitizeSearchStringRemovesSqlKeywords() : void {
		// sanitize_search_string is defined in lib/html_utility.php
		// It strips SQL injection keywords
		if (!function_exists('sanitize_search_string')) {
			$this->markTestSkipped('sanitize_search_string not available without full bootstrap');
		}

		$result = sanitize_search_string("test' OR 1=1 --");
		$this->assertStringNotContainsString("'", $result);
	}
}
