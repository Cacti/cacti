<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\TestCase;

/**
 * Tests for database helper functions in lib/database.php
 */
class DatabaseHelperTest extends TestCase {
	public static function setUpBeforeClass() : void {
		// Stub functions that database.php calls but are defined elsewhere
		if (!function_exists('cacti_sizeof')) {
			function cacti_sizeof($array) {
				return ($array === null || !is_countable($array)) ? 0 : count($array);
			}
		}

		if (!function_exists('cacti_log')) {
			function cacti_log($message, $also_log = false, $log_type = '', $level = 0) {
				return;
			}
		}

		if (!function_exists('clean_up_lines')) {
			function clean_up_lines($string) {
				return $string;
			}
		}

		// Load real database functions (defines db_qstr, array_to_sql_or, etc.)
		require_once CACTI_PATH . '/lib/database.php';
	}

	public function testArrayToSqlOrEmptyArray() : void {
		$this->assertSame('', array_to_sql_or([], 'id'));
	}

	public function testArrayToSqlOrSingleElement() : void {
		$result = array_to_sql_or([1], 'id');
		$this->assertStringContainsString('id IN(', $result);
		$this->assertStringContainsString('1', $result);
	}

	public function testArrayToSqlOrMultipleElements() : void {
		$result = array_to_sql_or([1, 2, 3], 'host_id');
		$this->assertStringContainsString('host_id IN(', $result);
	}

	public function testArrayToSqlOrNullTailTrimmed() : void {
		$result = array_to_sql_or([1, 2, null], 'id');
		$this->assertStringContainsString('id IN(', $result);
		// null tail is stripped, leaving [1, 2]
		$this->assertStringNotContainsString('NULL', $result);
	}

	public function testArrayToSqlOrAllNullsReturnsEmpty() : void {
		$result = array_to_sql_or([null], 'id');
		$this->assertSame('', $result);
	}
}
