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

namespace Cacti\Domain\Installation;

final class InstallRun {
	public private(set) InstallRunState $state = InstallRunState::Ready;

	public function __construct(public readonly InstallRunId $id) {
	}

	public function start(): void {
		$this->transitionTo(InstallRunState::Running);
	}

	public function succeed(): void {
		$this->transitionTo(InstallRunState::Succeeded);
	}

	public function fail(): void {
		$this->transitionTo(InstallRunState::Failed);
	}

	public function cancel(): void {
		$this->transitionTo(InstallRunState::Cancelled);
	}

	public function retry(): void {
		$this->transitionTo(InstallRunState::Ready);
	}

	private function transitionTo(InstallRunState $next): void {
		if (!$this->state->canTransitionTo($next)) {
			throw new InvalidInstallRunTransition(sprintf(
				'Install run "%s" cannot transition from "%s" to "%s".',
				$this->id,
				$this->state->value,
				$next->value
			));
		}

		$this->state = $next;
	}
}
