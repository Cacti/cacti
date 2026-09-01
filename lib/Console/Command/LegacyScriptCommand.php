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

namespace Cacti\Console\Command;

use Cacti\Console\Input\RawArgvInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class LegacyScriptCommand extends Command {
	public function __construct(
		string $name,
		private readonly string $script,
		private readonly string $root,
	) {
		parent::__construct($name);
		$this->setAliases([$script]);
		$this->setDescription(sprintf('Runs the legacy cli/%s.php command.', $script));
		$this->setHelp(sprintf(
			'This compatibility command forwards arguments, input, output, and the exit status to cli/%s.php.',
			$script
		));
		$this->ignoreValidationErrors();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$input instanceof RawArgvInput) {
			throw new \LogicException('Legacy commands require RawArgvInput.');
		}

		$arguments = $input->argumentsAfterCommand(array_merge([$this->getName()], $this->getAliases()));
		$process   = new Process(
			array_merge([PHP_BINARY, $this->root . '/cli/' . $this->script . '.php'], $arguments),
			$this->root,
			null,
			STDIN,
			null
		);
		$process->setTimeout(null);

		return $process->run(function (string $type, string $buffer) use ($output): void {
			$target = $type === Process::ERR && $output instanceof ConsoleOutputInterface
				? $output->getErrorOutput()
				: $output;
			$target->write($buffer, false, OutputInterface::OUTPUT_RAW);
		});
	}
}
