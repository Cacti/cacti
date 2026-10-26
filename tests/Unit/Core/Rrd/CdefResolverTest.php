<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use Cacti\Cdef\CdefRepository;
use Cacti\Cdef\CdefResolver;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 4) . '/lib/Cdef/CdefResolver.php';

test('legacy CDEF functions load before Composer is available', function () : void {
	$path    = var_export(dirname(__DIR__, 4) . '/lib/cdef.php', true);
	$process = new Process([PHP_BINARY, '-n', '-r', 'require ' . $path . ';
		foreach (["get_cdef", "get_cdef_item_name", "get_cdef_recursive", "cdef_is_in_use"] as $function) {
			if (!function_exists($function)) {
				exit(1);
			}
		}
		new \Cacti\Cdef\CdefResolver(new \Cacti\Cdef\DatabaseCdefRepository(), static function (string $message) : void {});
		new \Cacti\Cdef\CdefResolutionState();
		exit(class_exists("Composer\\\\Autoload\\\\ClassLoader", false) ? 1 : 0);
	']);
	$process->run();

	expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
		->and($process->getOutput())->toBe('');
});

test('a reusable CDEF resolver isolates state and reads changed data after success or failure', function () : void {
	$repository = new class implements CdefRepository {
		public array $definitions = [
			1 => [['id' => 10, 'type' => '6', 'value' => '8']],
			2 => [
				['id' => 0, 'type' => '5', 'value' => 1],
				['id' => 0, 'type' => '5', 'value' => 1],
			],
		];
		public ?string $value = '8';
		public array $reads   = [];

		public function items(int $cdef_id) : array|false {
			$this->reads[] = $cdef_id;

			return $this->definitions[$cdef_id] ?? false;
		}

		public function exists(int $cdef_id) : ?bool {
			return array_key_exists($cdef_id, $this->definitions);
		}

		public function itemName(int $cdef_item_id) : ?string {
			return $this->value;
		}
	};
	$logs     = [];
	$resolver = new CdefResolver($repository, static function (string $message) use (&$logs) : void {
		$logs[] = $message;
	});

	expect($resolver->resolve(2))->toBe('8,8')
		->and($repository->reads)->toBe([2, 1]);

	$repository->value = '12';
	expect($resolver->resolve(2))->toBe('12,12')
		->and($repository->reads)->toBe([2, 1, 2, 1]);

	$repository->definitions[1] = [['id' => 0, 'type' => '5', 'value' => 2]];
	expect($resolver->resolve(2))->toBeNull()
		->and($logs)->toBe(['ERROR: CDEF 2 contains a recursive cycle.']);

	$repository->definitions[1] = [['id' => 10, 'type' => '6', 'value' => '12']];
	expect($resolver->resolve(2))->toBe('12,12');

	$repository->value = null;
	expect($resolver->resolve(1))->toBeNull();
	$repository->value = '20';
	expect($resolver->resolve(1))->toBe('20');
});
