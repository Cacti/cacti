<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';

if (!defined('RRDTOOL_OUTPUT_STDOUT')) {
	define('RRDTOOL_OUTPUT_STDOUT', 1);
}

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', PHP_OS_FAMILY === 'Windows' ? 'win32' : 'unix');
}

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg(string $value) : string {
		return escapeshellarg(str_replace(["\r", "\n"], '', $value));
	}
}

require_once dirname(__DIR__, 2) . '/lib/rrd.php';

function rrd_mutation_test_directory() : string {
	$directory = sys_get_temp_dir() . '/cacti-rrd-mutation-' . bin2hex(random_bytes(6));

	if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
		throw new RuntimeException('Unable to create RRD mutation test directory.');
	}

	return $directory;
}

function rrd_mutation_test_document() : DOMDocument {
	$dom = new DOMDocument();
	$dom->loadXML('<rrd><version>0003</version></rrd>');

	return $dom;
}

test('RRD dump loader validates execution output and XML', function () {
	$command = null;
	$valid   = rrdtool_dump_document(
		"/tmp/device's metrics.rrd",
		null,
		static function (string $value) use (&$command) : string {
			$command = $value;

			return '<rrd><version>0003</version></rrd>';
		}
	);

	expect($valid)->toBeInstanceOf(DOMDocument::class)
		->and($valid->getElementsByTagName('version')->item(0)?->nodeValue)->toBe('0003')
		->and($command)->toBe("dump '/tmp/device'\\''s metrics.rrd'")
		->and(rrdtool_dump_document('/tmp/file', null, static fn () : bool => false))->toBeFalse()
		->and(rrdtool_dump_document('/tmp/file', null, static fn () : string => ''))->toBeFalse()
		->and(rrdtool_dump_document('/tmp/file', null, static fn () : string => '<rrd>'))->toBeFalse();
});

test('RRD restore installs a complete replacement and preserves mode', function () {
	$directory = rrd_mutation_test_directory();
	$file      = $directory . '/source.rrd';
	file_put_contents($file, 'original');
	chmod($file, 0640);

	$executor = static function (string $command) : string|false {
		if (!preg_match("/^restore -f '([^']+)' '([^']+)'$/", $command, $matches)) {
			return false;
		}

		return file_put_contents($matches[2], 'replacement') === false ? false : '';
	};

	try {
		expect(rrdtool_restore_document(rrd_mutation_test_document(), $file, null, $executor))->toBeTrue()
			->and(file_get_contents($file))->toBe('replacement')
			->and(fileperms($file) & 0777)->toBe(0640)
			->and(glob($directory . '/.cacti-rrd-restore-*'))->toBe([]);
	} finally {
		unlink($file);
		rmdir($directory);
	}
});

test('RRD restore leaves the original intact when execution or rename fails', function () {
	$directory = rrd_mutation_test_directory();
	$file      = $directory . '/source.rrd';
	file_put_contents($file, 'original');

	$failedExecution     = static fn () : bool => false;
	$successfulExecution = static function (string $command) : string|false {
		if (!preg_match("/^restore -f '([^']+)' '([^']+)'$/", $command, $matches)) {
			return false;
		}

		return file_put_contents($matches[2], 'replacement') === false ? false : '';
	};

	try {
		expect(rrdtool_restore_document(rrd_mutation_test_document(), $file, null, $failedExecution))->toBeFalse()
			->and(file_get_contents($file))->toBe('original')
			->and(rrdtool_restore_document(rrd_mutation_test_document(), $file, null, $successfulExecution, static fn () : bool => false))->toBeFalse()
			->and(file_get_contents($file))->toBe('original')
			->and(glob($directory . '/.cacti-rrd-restore-*'))->toBe([]);
	} finally {
		unlink($file);
		rmdir($directory);
	}
});
