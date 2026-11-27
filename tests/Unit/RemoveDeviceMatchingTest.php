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
*/

use PHPUnit\Framework\TestCase;

class RemoveDeviceMatchingTest extends TestCase {
	private function runCommand($arguments) {
		$fixture = <<<'PHP'
$source = file_get_contents($argv[1]);
$_SERVER['argv'] = array_merge(array('remove_device.php'), array_slice($argv, 2));
$config = array('poller_id' => 1);
function cacti_sizeof($value) { return count($value); }
function getHostsByDescription() {
	return array('router.1' => 1, 'routerX1' => 2, 'router.10' => 3, 'site/router' => 4);
}
function getAddresses() {
	return array('1.2.3.4' => 1, '1.2.3.5' => 2, '1x2x3x4' => 3, '11.2.3.4' => 4, '1.2.3.40' => 5);
}
function db_fetch_assoc($sql) { return array(); }
function api_device_remove_multi($ids) { print 'REMOVED:' . json_encode($ids) . PHP_EOL; }
function is_error_message() { return false; }
function cacti_log($message, $echo, $subsystem) {}
function get_cacti_cli_version() { return 'test'; }
define('COPYRIGHT_YEARS', 'test');
eval(substr($source, strpos($source, '/* switch to main database for cli')));
PHP;
		$command = array_merge(
			array(PHP_BINARY, '-r', $fixture, __DIR__ . '/../../cli/remove_device.php'),
			$arguments
		);
		$process = proc_open($command, array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		), $pipes);
		$this->assertIsResource($process);
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$status = proc_close($process);
		$this->assertSame('', $errors);

		return array($status, $output);
	}

	public function testLiteralIpAndDescriptionMatchExactly() {
		foreach (array('--ip=1.2.3.4', '--description=router.1', '--description=site/router') as $argument) {
			list($status, $output) = $this->runCommand(array($argument, '--confirm'));
			$this->assertSame(0, $status);
			$this->assertStringContainsString($argument === '--description=site/router' ? 'REMOVED:[4]' : 'REMOVED:[1]', $output);
		}
	}

	public function testLiteralSubstringDoesNotMatch() {
		list($status, $output) = $this->runCommand(array('--description=router', '--confirm'));
		$this->assertSame(1, $status);
		$this->assertStringContainsString('ERROR: Unable to find host', $output);
		$this->assertStringNotContainsString('REMOVED:', $output);
	}

	public function testRegexModeMatchesPatternsForBothSelectors() {
		list($status, $output) = $this->runCommand(array('--regex', '--ip=^1\.2\.3\.[45]$', '--confirm'));
		$this->assertSame(0, $status);
		$this->assertStringContainsString('REMOVED:[1,2]', $output);

		list($status, $output) = $this->runCommand(array('--description=^router[.X]1$', '--regex', '--confirm'));
		$this->assertSame(0, $status);
		$this->assertStringContainsString('REMOVED:[1,2]', $output);
	}

	public function testMultipleSelectorsRetainTheirUnion() {
		list($status, $output) = $this->runCommand(array('--ip=1.2.3.4', '--description=routerX1', '--id=3', '--confirm'));
		$this->assertSame(0, $status);
		$this->assertStringContainsString('REMOVED:[2,1,"3"]', $output);
	}

	public function testInvalidRegexStopsBeforeRemoval() {
		list($status, $output) = $this->runCommand(array('--regex', '--description=[', '--id=1', '--confirm'));
		$this->assertSame(1, $status);
		$this->assertStringContainsString('ERROR: Invalid regular expression', $output);
		$this->assertStringNotContainsString('REMOVED:', $output);
	}

	public function testPreviewDoesNotRemoveDevices() {
		list($status, $output) = $this->runCommand(array('--ip=1.2.3.4'));
		$this->assertSame(0, $status);
		$this->assertStringContainsString('Please use --confirm', $output);
		$this->assertStringNotContainsString('REMOVED:', $output);
	}
}
