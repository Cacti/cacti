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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Installer;

require_once __DIR__ . '/RunState.php';

/** An allowlisted diagnostic projection; never serialize options, SQL, or exception messages. */
final class RunReport {
	private RunState $state = RunState::Running;
	/** @var list<array{operation: string, duration_seconds: float, succeeded: bool, version: ?string}> */
	private array $operations = [];
	private readonly string $id;
	private readonly string $started;
	private ?CompletionWarnings $warnings = null;

	public function __construct() {
		$this->id      = bin2hex(random_bytes(16));
		$this->started = gmdate('c');
	}

	public function record(string $operation, float $seconds, bool $succeeded, ?string $version = null) : void {
		if ($this->state !== RunState::Running || !in_array($operation, ['preflight', 'schema', 'csrf', 'conversion', 'migration', 'templates', 'server', 'poller', 'plugins', 'version', 'collectors', 'authentication'], true)) {
			throw new \LogicException('Invalid installer operation or terminal report');
		}

		if ($version !== null && !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+[a-z]*$/D', $version)) {
			throw new \LogicException('Invalid migration version');
		}
		$this->operations[] = ['operation' => $operation, 'duration_seconds' => max(0, $seconds), 'succeeded' => $succeeded, 'version' => $version];

		if (!$succeeded) {
			$this->state = RunState::Failed;
		}
	}

	public function finish(bool $success, CompletionWarnings $warnings) : void {
		if ($this->state !== RunState::Running) {
			return;
		}
		$this->warnings = $warnings;
		$this->state    = !$success ? RunState::Failed : ($warnings->hasWarnings() ? RunState::Warnings : RunState::Succeeded);
	}

	public function encode() : string {
		$data = ['run_id'     => $this->id, 'started_at' => $this->started, 'state' => $this->state->value,
			'warnings'           => $this->warnings === null ? null : ['collectors' => $this->warnings->collectors], 'operations' => $this->operations,
			'operations_omitted' => 0, 'warning_ids_omitted' => 0,
			'recovery'           => 'If a migration failed, stop polling and web writes. Back up the failed database before investigating or retrying. Restore the matching pre-upgrade database, configuration, plugins, application files and RRD data together. See docs/INSTALLER_RECOVERY.md.'];

		// settings.value is varchar(4096). Trim whole entries, never JSON bytes,
		// and retain the newest operation, including the migration that failed.
		do {
			$encoded = json_encode($data, JSON_THROW_ON_ERROR);

			if (strlen($encoded) <= 4096) {
				return $encoded;
			}

			if (count($data['operations']) > 1) {
				array_shift($data['operations']);
				$data['operations_omitted']++;

				continue;
			}

			foreach ($data['warnings']['collectors'] ?? [] as $state => $ids) {
				if ($ids !== []) {
					array_pop($data['warnings']['collectors'][$state]);
					$data['warning_ids_omitted']++;

					continue 2;
				}
			}

			throw new \LogicException('Installer report exceeds its storage limit');
		} while (true);
	}
}
