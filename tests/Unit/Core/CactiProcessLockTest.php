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
 */

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

require_once dirname(__DIR__, 3) . '/lib/CactiProcessLock.php';

test('task keys are deterministic and preserve field boundaries', function () {
	$key = CactiProcessLock::key('poller', 'child', 7);

	expect($key)->toBe(CactiProcessLock::key('poller', 'child', 7))
		->and($key)->toStartWith('cacti.process.')
		->and(strlen($key))->toBe(78)
		->and($key)->not->toBe(CactiProcessLock::key('poller:child', '7', 0))
		->and($key)->not->toBe(CactiProcessLock::key('poller', 'child', 8));
});

test('only one owner can hold a task lock at a time', function () {
	$factory = new LockFactory(new InMemoryStore());
	$first   = new CactiProcessLock($factory, 'poller', 'master', 0);
	$second  = new CactiProcessLock($factory, 'poller', 'master', 0);

	expect($first->acquire())->toBeTrue()
		->and($second->acquire())->toBeFalse();

	$first->release();

	expect($second->acquire())->toBeTrue();

	$second->release();
});

test('pdo locks use the declared schema and release ownership', function () {
	$connection = new PDO('sqlite::memory:');
	$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

	$first  = CactiProcessLock::fromPdo($connection, 'reports', 'master', 0);
	$second = CactiProcessLock::fromPdo($connection, 'reports', 'master', 0);

	expect($connection->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_SILENT)
		->and($first->acquire())->toBeTrue()
		->and($connection->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_SILENT)
		->and($connection->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'process_locks'")->fetchColumn())->toBe(CactiProcessLock::TABLE)
		->and($second->acquire())->toBeFalse();

	$first->release();

	expect($connection->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_SILENT)
		->and($second->acquire())->toBeTrue();

	$second->release();

	expect($connection->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_SILENT);
});
