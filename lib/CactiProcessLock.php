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
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\PdoStore;

/**
 * Short-lived, database-backed mutex for one Cacti process-registry key.
 *
 * The processes table remains the source of lifecycle, PID and timeout
 * information. This lock only serializes admission for one logical registry
 * row so two contenders cannot both observe an empty row and start.
 */
final class CactiProcessLock {
	public const TABLE = 'process_locks';
	public const TTL   = 30;

	private LockInterface $lock;

	public function __construct(LockFactory $factory, string $tasktype, string $taskname, int $taskid) {
		$this->lock = $factory->createLock(self::key($tasktype, $taskname, $taskid), self::TTL);
	}

	public static function fromPdo(PDO $connection, string $tasktype, string $taskname, int $taskid) : self {
		$store = new PdoStore($connection, ['db_table' => self::TABLE], 0.01, self::TTL);

		return new self(new LockFactory($store), $tasktype, $taskname, $taskid);
	}

	public static function key(string $tasktype, string $taskname, int $taskid) : string {
		return 'cacti.process.' . hash('sha256', serialize([$tasktype, $taskname, $taskid]));
	}

	public function acquire() : bool {
		return $this->lock->acquire();
	}

	public function release() : void {
		$this->lock->release();
	}
}
