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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Console;

use Cacti\Console\Command\LegacyScriptCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CactiApplication extends Application {
	public function __construct(private readonly string $root) {
		parent::__construct('Cacti', $this->readVersion());

		foreach (LegacyCommandMap::commands() as $script => $command) {
			$this->add(new LegacyScriptCommand($command, $script, $this->root));
		}
	}

	/**
	 * Dispatch a legacy command before Symfony can claim its flags.
	 *
	 * Application::doRun() answers --version/-V itself and rewrites --help/-h
	 * into the help command, and it reads both straight off the token vector,
	 * so dropping them from the input definition changes nothing. Forty-five
	 * scripts under cli/ carry their own --version and --help handling, so a
	 * legacy command owns its whole flag namespace and Symfony must not read
	 * any of it.
	 */
	#[\Override]
	public function doRun(InputInterface $input, OutputInterface $output): int {
		$name = $this->getCommandName($input);

		if ($name !== null && $name !== '') {
			try {
				$command = $this->find($name);
			} catch (\Throwable) {
				// Unknown or ambiguous; let Symfony report it as it always has.
				$command = null;
			}

			if ($command instanceof LegacyScriptCommand) {
				/* -q belongs to the legacy script too. Symfony has already applied
				 * it to this output, which would swallow the child's stream while
				 * the flag is also forwarded to the child. */
				$output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

				return $command->run($input, $output);
			}
		}

		return parent::doRun($input, $output);
	}

	private function readVersion(): string {
		$version = @file_get_contents($this->root . '/include/cacti_version');

		return $version === false ? 'unknown' : trim($version);
	}
}
