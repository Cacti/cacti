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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

use Cacti\Domain\Installation\InstallRunId;

require_once CACTI_PATH_INCLUDE . '/domain.php';

it('keeps a non-empty identifier verbatim', function () {
	$id = new InstallRunId('run-123');

	expect($id->value)->toBe('run-123')
		->and((string) $id)->toBe('run-123');
});

it('trims surrounding whitespace from the identifier', function () {
	$id = new InstallRunId("  run-123\t\n");

	expect($id->value)->toBe('run-123');
});

it('rejects an empty identifier', function () {
	expect(fn () => new InstallRunId(''))->toThrow(InvalidArgumentException::class);
});

it('rejects a whitespace-only identifier', function () {
	expect(fn () => new InstallRunId("   \t"))->toThrow(InvalidArgumentException::class);
});

it('enforces the readonly identifier value', function () {
	$id = new InstallRunId('run-123');

	expect(function () use ($id) {
		$id->value = 'mutated';
	})->toThrow(Error::class);
});
