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

use Cacti\Console\CactiApplication;
use Cacti\Console\Command\LegacyScriptCommand;
use Cacti\Console\Input\RawArgvInput;
use Cacti\Console\LegacyCommandMap;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessSignaledException;

final class CactiControllableLegacyProcess extends Process {
	public bool $tty_enabled         = false;
	public bool $output_disabled     = false;
	public bool $running             = false;
	public int|null $received_signal = null;

	public function __construct(private readonly int $result = 0, private readonly int|null $term_signal = null) {
		parent::__construct(['true']);
	}

	public function setTimeout(float|null $timeout): static {
		return $this;
	}

	public function setTty(bool $tty): static {
		$this->tty_enabled = $tty;

		return $this;
	}

	public function disableOutput(): static {
		$this->output_disabled = true;

		return $this;
	}

	public function run(callable|null $callback = null, array $env = []): int {
		if ($this->term_signal !== null) {
			throw new ProcessSignaledException($this);
		}

		return $this->result;
	}

	public function getTermSignal(): int {
		return $this->term_signal ?? 0;
	}

	public function isRunning(): bool {
		return $this->running;
	}

	public function signal(int $signal): static {
		$this->received_signal = $signal;

		return $this;
	}
}

it('registers every executable legacy CLI script exactly once', function (): void {
	$root    = dirname(__DIR__, 4);
	$scripts = array_map(
		static fn (string $path): string => basename($path, '.php'),
		glob($root . '/cli/*.php') ?: []
	);
	$scripts = array_values(array_diff($scripts, ['index']));
	sort($scripts);

	$mapped = array_keys(LegacyCommandMap::commands());
	sort($mapped);

	expect($mapped)->toBe($scripts)
		->and(array_unique(array_values(LegacyCommandMap::commands())))
		->toHaveCount(count($mapped));
});

it('lists commands without bootstrapping the database', function (): void {
	$root        = dirname(__DIR__, 4);
	$application = new CactiApplication($root);
	$application->setAutoExit(false);
	$tester = new ApplicationTester($application);

	$status   = $tester->run(['command' => 'list', '--format' => 'json']);
	$commands = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['commands'];
	$names    = array_column($commands, 'name');

	expect($status)->toBe(0)
		->and($names)->toContain('device:add', 'database:audit', 'poller:reindex', 'rrd:resize');
});

it('reports an unknown version when the version file is unavailable', function (): void {
	$application = new CactiApplication('/path/that/does/not/exist');

	expect($application->getVersion())->toBe('unknown');
});

it('leaves unknown commands to Symfony after legacy lookup fails', function (): void {
	$application = new CactiApplication(dirname(__DIR__, 4));
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);

	expect(fn (): int => $application->run(new RawArgvInput(['bin/cacti', 'not:a:command']), new BufferedOutput()))
		->toThrow(Symfony\Component\Console\Exception\CommandNotFoundException::class);
});

it('hands a terminal directly to a legacy process', function (): void {
	$process = new CactiControllableLegacyProcess(17);
	$command = new LegacyScriptCommand(
		'probe:run',
		'probe',
		dirname(__DIR__, 3) . '/fixtures/Console',
		static fn (array $command): Process => $process,
		static fn (): bool => true,
		false
	);

	expect($command->run(new RawArgvInput(['bin/cacti', 'probe:run']), new BufferedOutput()))->toBe(17)
		->and($process->tty_enabled)->toBeTrue()
		->and($process->output_disabled)->toBeFalse();
});

it('maps a signaled legacy process to the shell exit status', function (): void {
	$process = new CactiControllableLegacyProcess(term_signal: SIGKILL);
	$command = new LegacyScriptCommand(
		'probe:run',
		'probe',
		dirname(__DIR__, 3) . '/fixtures/Console',
		static fn (array $command): Process => $process,
		static fn (): bool => false,
		false
	);

	expect($command->run(new RawArgvInput(['bin/cacti', 'probe:run']), new BufferedOutput()))->toBe(128 + SIGKILL);
});

it('relays registered termination signals only while the child is running', function (): void {
	$process  = new CactiControllableLegacyProcess();
	$handlers = [];
	$command  = new LegacyScriptCommand(
		'probe:run',
		'probe',
		dirname(__DIR__, 3) . '/fixtures/Console',
		static fn (array $command): Process => $process,
		static fn (): bool => false,
		static function (int $signal, callable $handler) use (&$handlers): void {
			$handlers[$signal] = $handler;
		}
	);

	$command->run(new RawArgvInput(['bin/cacti', 'probe:run']), new BufferedOutput());
	$process->running = true;
	$handlers[SIGTERM](SIGTERM);

	expect($process->received_signal)->toBe(SIGTERM)
		->and($handlers)->toHaveKeys([SIGTERM, SIGINT, SIGHUP]);

	$process->running         = false;
	$process->received_signal = null;
	$handlers[SIGINT](SIGINT);

	expect($process->received_signal)->toBeNull();
});

it('forwards raw legacy arguments and preserves the exit status', function (): void {
	$root        = dirname(__DIR__, 3) . '/fixtures/Console';
	$application = new Application('test');
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', $root));
	$input  = new RawArgvInput(['bin/cacti', 'probe:run', '--arbitrary=value', '-x', 'plain']);
	$output = new BufferedOutput();

	$status = $application->run($input, $output);

	expect($status)->toBe(23)
		->and($output->fetch())->toBe('["--arbitrary=value","-x","plain"]probe-error');
});

it('routes child stderr separately for console outputs and accepts aliases', function (): void {
	$root        = dirname(__DIR__, 3) . '/fixtures/Console';
	$application = new Application('test');
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', $root));
	$output      = new ConsoleOutput(decorated: false);
	$errorOutput = new BufferedOutput();
	$output->setErrorOutput($errorOutput);

	$status = $application->run(
		new RawArgvInput(['bin/cacti', 'probe', 'alias-value']),
		$output
	);

	expect($status)->toBe(23)
		->and($errorOutput->fetch())->toBe('probe-error');
});

it('forwards arguments when the command name is abbreviated', function (): void {
	$root        = dirname(__DIR__, 3) . '/fixtures/Console';
	$application = new Application('test');
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', $root));
	$output = new BufferedOutput();

	$status = $application->run(
		new RawArgvInput(['bin/cacti', 'pro:r', '--arbitrary=value', 'plain']),
		$output
	);

	expect($status)->toBe(23)
		->and($output->fetch())->toBe('["--arbitrary=value","plain"]probe-error');
});

it('leaves --help, -h, --version and -V to the legacy script', function (string $flag): void {
	$application = new CactiApplication(dirname(__DIR__, 4));
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', dirname(__DIR__, 3) . '/fixtures/Console'));
	$output = new BufferedOutput();

	$status = $application->run(new RawArgvInput(['bin/cacti', 'probe:run', $flag]), $output);

	expect($status)->toBe(23)
		->and($output->fetch())->toBe(sprintf('["%s"]probe-error', $flag));
})->with(['--help', '-h', '--version', '-V']);

it('forwards a flag written before the command name', function (string $flag): void {
	$application = new CactiApplication(dirname(__DIR__, 4));
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', dirname(__DIR__, 3) . '/fixtures/Console'));
	$output = new BufferedOutput();

	// getFirstArgument() skips leading options, so the command dispatches with
	// the flag ahead of its name. Slicing only forward handed the script an
	// empty argv, and a script whose flags are all optional then ran its
	// default path.
	$status = $application->run(new RawArgvInput(['bin/cacti', $flag, 'probe:run']), $output);

	expect($status)->toBe(23)
		->and($output->fetch())->toBe(sprintf('["%s"]probe-error', $flag));
})->with(['--help', '-h', '--version', '-V']);

it('preserves the order of flags on both sides of the command name', function (): void {
	$application = new CactiApplication(dirname(__DIR__, 4));
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', dirname(__DIR__, 3) . '/fixtures/Console'));
	$output = new BufferedOutput();

	$status = $application->run(
		new RawArgvInput(['bin/cacti', '-v', 'probe:run', '--arbitrary=value', 'plain']),
		$output
	);

	expect($status)->toBe(23)
		->and($output->fetch())->toBe('["-v","--arbitrary=value","plain"]probe-error');
});

it('still answers --version and --help for the application itself', function (): void {
	$application = new CactiApplication(dirname(__DIR__, 4));
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$output = new BufferedOutput();

	expect($application->run(new RawArgvInput(['bin/cacti', '--version']), $output))->toBe(0)
		->and($output->fetch())->toStartWith('Cacti');
});

it('does not swallow legacy output when -q is passed through', function (): void {
	$application = new CactiApplication(dirname(__DIR__, 4));
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', dirname(__DIR__, 3) . '/fixtures/Console'));
	$output = new BufferedOutput();

	$status = $application->run(new RawArgvInput(['bin/cacti', 'probe:run', '-q', 'plain']), $output);

	expect($status)->toBe(23)
		->and($output->fetch())->toBe('["-q","plain"]probe-error');
});

it('streams large legacy output without retaining it in the parent', function (): void {
	$root        = dirname(__DIR__, 3) . '/fixtures/Console';
	$application = new Application('test');
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('bulk:run', 'bulk', $root));
	$output = new BufferedOutput();

	$status = $application->run(new RawArgvInput(['bin/cacti', 'bulk:run', '20000']), $output);

	// 20000 lines is past the 1MB php://temp threshold Process spools at.
	expect($status)->toBe(0)
		->and(strlen($output->fetch()))->toBe(20000 * 100);
});

it('reports a killed legacy script as the shell does', function (): void {
	$root   = dirname(__DIR__, 3) . '/fixtures/Console';
	$runner = dirname(__DIR__, 3) . '/fixtures/Console/kill_runner.php';

	$descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
	$process    = proc_open([PHP_BINARY, $runner, $root], $descriptor, $pipes);

	expect($process)->toBeResource();

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);

	// 128 + SIGKILL, the status a shell reports for the same death.
	expect(trim($stdout))->toBe('137')
		->and($stderr)->not->toContain('ProcessSignaledException');
});

it('runs the legacy script in the directory the caller was in', function (): void {
	$root      = dirname(__DIR__, 3) . '/fixtures/Console';
	$original  = getcwd();
	$elsewhere = sys_get_temp_dir();

	try {
		chdir($elsewhere);

		$application = new Application('test');
		$application->setAutoExit(false);
		$application->setCatchExceptions(false);
		$application->add(new LegacyScriptCommand('pwd:run', 'pwd', $root));
		$output = new BufferedOutput();

		$application->run(new RawArgvInput(['bin/cacti', 'pwd:run']), $output);

		// A relative path argument has to mean what it would have meant to
		// `php cli/<script>.php` from the same shell.
		expect(realpath(trim($output->fetch())))->toBe(realpath($elsewhere));
	} finally {
		chdir((string) $original);
	}
});

it('rejects parsed inputs that cannot preserve the raw argument vector', function (): void {
	$command = new LegacyScriptCommand('probe:run', 'probe', dirname(__DIR__, 3) . '/fixtures/Console');

	expect(fn () => $command->run(new ArrayInput([]), new BufferedOutput()))
		->toThrow(LogicException::class, 'Legacy commands require RawArgvInput.');
});

it('returns no forwarded arguments when no command token is present', function (): void {
	$input = new RawArgvInput(['bin/cacti', '--version']);

	expect($input->argumentsAfterCommand(['probe:run', 'probe']))->toBe([]);
});

it('uses the server argument vector when one is not supplied', function (): void {
	$original        = $_SERVER['argv'] ?? null;
	$_SERVER['argv'] = ['bin/cacti', 'probe:run', 'server-value'];

	try {
		$input = new RawArgvInput();
		expect($input->argumentsAfterCommand(['probe:run']))->toBe(['server-value']);
	} finally {
		if ($original === null) {
			unset($_SERVER['argv']);
		} else {
			$_SERVER['argv'] = $original;
		}
	}
});

it('runs the installed CLI entry point without application bootstrap', function (): void {
	$root    = dirname(__DIR__, 4);
	$process = new Process([PHP_BINARY, $root . '/bin/cacti', 'list', '--raw']);

	expect($process->run())->toBe(0)
		->and($process->getOutput())->toContain('device:add')
		->and($process->getOutput())->toContain('system:version');
});

it('fails clearly when the CLI entry point has no installed dependencies', function (): void {
	$fixture = sys_get_temp_dir() . '/cacti-cli-no-deps-' . bin2hex(random_bytes(8));
	mkdir($fixture . '/bin', 0777, true);
	copy(dirname(__DIR__, 4) . '/bin/cacti', $fixture . '/bin/cacti');
	$process = new Process([PHP_BINARY, $fixture . '/bin/cacti']);

	expect($process->run())->toBe(1)
		->and($process->getErrorOutput())->toContain('Cacti dependencies are not installed');
});
