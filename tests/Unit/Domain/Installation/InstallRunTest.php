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

use Cacti\Domain\Installation\InstallRun;
use Cacti\Domain\Installation\InstallRunId;
use Cacti\Domain\Installation\InstallRunState;
use Cacti\Domain\Installation\InvalidInstallRunTransition;

require_once dirname(__DIR__, 4) . '/include/domain.php';

it('starts a new run in the ready state', function () {
	$run = new InstallRun(new InstallRunId('run-123'));

	expect($run->state)->toBe(InstallRunState::Ready)
		->and((string) $run->id)->toBe('run-123');
});

it('allows a run to complete through the running state', function () {
	$run = new InstallRun(new InstallRunId('run-123'));

	$run->start();
	$run->succeed();

	expect($run->state)->toBe(InstallRunState::Succeeded);
});

it('allows a failed run to be retried explicitly', function () {
	$run = new InstallRun(new InstallRunId('run-123'));

	$run->start();
	$run->fail();

	expect($run->state)->toBe(InstallRunState::Failed)
		->and($run->state->isTerminal())->toBeFalse();

	$run->retry();

	expect($run->state)->toBe(InstallRunState::Ready);
});

it('allows a ready run to be cancelled', function () {
	$run = new InstallRun(new InstallRunId('run-123'));

	$run->cancel();

	expect($run->state)->toBe(InstallRunState::Cancelled)
		->and($run->state->isTerminal())->toBeTrue();
});

it('allows a running run to be cancelled', function () {
	$run = new InstallRun(new InstallRunId('run-123'));

	$run->start();
	$run->cancel();

	expect($run->state)->toBe(InstallRunState::Cancelled)
		->and($run->state->isTerminal())->toBeTrue();
});

it('rejects invalid and terminal state transitions', function () {
	$run = new InstallRun(new InstallRunId('run-123'));

	expect(fn () => $run->succeed())->toThrow(InvalidInstallRunTransition::class);

	$run->start();
	$run->succeed();

	expect(fn () => $run->retry())->toThrow(InvalidInstallRunTransition::class);
});
