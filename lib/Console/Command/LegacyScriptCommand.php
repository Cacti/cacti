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
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

final class LegacyScriptCommand extends Command {
	/** @var \Closure(array<int, string>): Process */
	private readonly \Closure $process_factory;
	/** @var \Closure(): bool */
	private readonly \Closure $tty_probe;
	/** @var (\Closure(int, callable): void)|null */
	private readonly ?\Closure $signal_registrar;

	public function __construct(
		string $name,
		private readonly string $script,
		private readonly string $root,
		?callable $process_factory = null,
		?callable $tty_probe = null,
		callable|false|null $signal_registrar = null,
	) {
		parent::__construct($name);
		$this->process_factory = $process_factory === null
			? static fn (array $command): Process => new Process($command, null, null, STDIN, null)
			: \Closure::fromCallable($process_factory);
		$this->tty_probe = $tty_probe === null
			? static fn (): bool => defined('STDIN') && defined('STDOUT') && Process::isTtySupported() && stream_isatty(STDIN) && stream_isatty(STDOUT)
			: \Closure::fromCallable($tty_probe);
		$this->signal_registrar = match (true) {
			$signal_registrar === false                                               => null,
			$signal_registrar !== null                                                => \Closure::fromCallable($signal_registrar),
			function_exists('pcntl_async_signals') && function_exists('pcntl_signal') => static function (int $signal, callable $handler): void {
				pcntl_async_signals(true);
				pcntl_signal($signal, $handler);
			},
			default => null,
		};
		$this->setAliases([$script]);
		$this->setDescription(sprintf('Runs the legacy cli/%s.php command.', $script));
		$this->setHelp(sprintf(
			'This compatibility command forwards arguments, input, output, and the exit status to cli/%s.php.',
			$script
		));
		$this->ignoreValidationErrors();
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$input instanceof RawArgvInput) {
			throw new \LogicException('Legacy commands require RawArgvInput.');
		}

		$arguments = $input->argumentsAfterCommand(array_values(array_merge([$this->getName()], $this->getAliases())));
		/* Inherit the caller's directory. The script path is absolute, and the
		 * legacy scripts resolve relative path arguments against the cwd with no
		 * normalization. Anchoring here would send a relative output into the
		 * web-served root instead of the directory the operator ran it from. */
		$process = ($this->process_factory)(
			array_merge([PHP_BINARY, $this->root . '/cli/' . $this->script . '.php'], $arguments)
		);
		$process->setTimeout(null);

		$this->forwardSignals($process);

		try {
			/* Several scripts under cli/ prompt with fgets(STDIN). Behind a pipe
			 * the prompt sits in a buffer while the child blocks on the read, so
			 * hand the terminal over whenever there is one. */
			if ($this->canUseTty()) {
				$process->setTty(true);

				return $process->run();
			}

			/* Process::buildCallback() appends every byte to a php://temp stream
			 * as well as calling this callback, and nothing ever reads it back.
			 * With no timeout, a long poller run spooled its whole output to disk
			 * for no reader. */
			$process->disableOutput();

			return $process->run(function (string $type, string $buffer) use ($output): void {
				$target = $type === Process::ERR && $output instanceof ConsoleOutputInterface
					? $output->getErrorOutput()
					: $output;
				$target->write($buffer, false, OutputInterface::OUTPUT_RAW);
			});
		} catch (ProcessSignaledException $exception) {
			/* A child the kernel killed has no exit code of its own, and Process
			 * throws rather than returning one. Report it the way a shell does so
			 * cron and systemd see 137 or 143, not a rendered stack trace. */
			return 128 + $exception->getSignal();
		}
	}

	/**
	 * Whether this invocation owns a terminal it can hand to the child.
	 */
	private function canUseTty(): bool {
		return ($this->tty_probe)();
	}

	/**
	 * Pass a termination signal on to the child.
	 *
	 * Without this a SIGTERM to bin/cacti leaves the legacy script running,
	 * possibly mid-write to the database, with nothing left to reap it.
	 */
	private function forwardSignals(Process $process): void {
		if ($this->signal_registrar === null) {
			return;
		}

		foreach ([SIGTERM, SIGINT, SIGHUP] as $signal) {
			($this->signal_registrar)($signal, static function (int $received) use ($process): void {
				if ($process->isRunning()) {
					$process->signal($received);
				}
			});
		}
	}
}
