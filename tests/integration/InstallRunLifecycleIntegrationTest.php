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

// Exercise the production wiring file the installer loads, not a hand-picked
// require list, so a broken require chain surfaces here.
require_once dirname(__DIR__, 2) . '/include/domain.php';

it('drives a full install run lifecycle through the domain bootstrap', function () {
	$run = new InstallRun(new InstallRunId('bootstrap-run'));

	expect($run->state())->toBe(InstallRunState::Ready);

	$run->start();
	expect($run->state())->toBe(InstallRunState::Running);

	$run->fail();
	expect($run->state())->toBe(InstallRunState::Failed);

	$run->retry();
	$run->start();
	$run->succeed();

	expect($run->state())->toBe(InstallRunState::Succeeded)
		->and($run->state()->isTerminal())->toBeTrue();

	expect(fn () => $run->cancel())->toThrow(InvalidInstallRunTransition::class);
});
