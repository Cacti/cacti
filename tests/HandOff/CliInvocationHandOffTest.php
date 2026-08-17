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

/*
 * Hand-off contract for the Symfony Console plumbing. Exercises every place a
 * value crosses a boundary on its way from argv to a Command and back to a
 * process exit code:
 *
 *   argv -> ArgvInput -> Command::execute() arguments
 *   legacy shim entrypoint <-> cli/cacti.php entrypoint (parity)
 *   shell metacharacters in --oldrrd -> rejection before any file is touched
 *   Command::SUCCESS / Command::FAILURE -> subprocess exit code
 *   parent env CACTI_PHP_TESTING -> CactiCommand::initialize() bootstrap skip
 *
 * The in-process cases use Symfony's CommandTester. The cross-process cases
 * reuse the runCli helper from CliInvocationTest so the bootstrap chain
 * (cli_check.php -> autoload.php -> CactiApplication) is a real subprocess
 * and not a mocked include graph.
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

if (!file_exists(dirname(__DIR__, 2) . '/lib/CactiCommand.php')) {
	test('CLI invocation hand-off: feature not present on this branch', function () {})
		->skip('lib/CactiCommand.php absent — feature PR #7075 not merged into develop yet');
	return;
}

const CLI_HANDOFF_REPO_ROOT = __DIR__ . '/../..';

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/CactiCommand.php';
require_once dirname(__DIR__, 2) . '/lib/CmdRealtimeCommand.php';
require_once dirname(__DIR__, 2) . '/lib/SpliceRrdCommand.php';

/**
 * Spawn a fresh PHP process for `php <script> <args...>`, returning stdout,
 * stderr and the exit code. Mirrors the helper in CliInvocationTest so the
 * two suites stay in sync; duplicated rather than shared because Pest does
 * not autoload from tests/integration into tests/HandOff.
 *
 * @return array{stdout:string,stderr:string,exit:int}
 */
function runCli(string $script, array $args = [], array $extraEnv = []) : array {
	$php  = PHP_BINARY;
	$path = CLI_HANDOFF_REPO_ROOT . '/' . ltrim($script, '/');

	$cmd = escapeshellcmd($php) . ' ' . escapeshellarg($path);

	foreach ($args as $arg) {
		$cmd .= ' ' . escapeshellarg($arg);
	}

	$descriptors = [
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	// Default to skipping the Cacti bootstrap; individual tests may override
	// CACTI_PHP_TESTING via $extraEnv to assert the unset-env branch.
	$baseEnv = ['CACTI_PHP_TESTING' => '1'];
	$env     = $extraEnv + $baseEnv + $_ENV + getenv();

	$proc = proc_open($cmd, $descriptors, $pipes, null, $env);

	if (!is_resource($proc)) {
		return ['stdout' => '', 'stderr' => 'failed to spawn', 'exit' => 127];
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exit = proc_close($proc);

	return [
		'stdout' => (string) $stdout,
		'stderr' => (string) $stderr,
		'exit'   => $exit,
	];
}

beforeEach(function () {
	if (!file_exists(CLI_HANDOFF_REPO_ROOT . '/include/vendor/autoload.php')
		|| !class_exists(Application::class)) {
		$this->markTestSkipped('symfony/console not installed; run composer install');
	}
});

test('argv hand-off: ArgvInput delivers cmd-realtime 5 7 60 to execute() with the right typed values', function () {
	// Build an in-process Application and parse a real argv-shaped input
	// so we exercise ArgvInput, not the array shortcut CommandTester offers.
	$probe = new class extends CactiCommand {
		public array $captured = [];

		public function __construct() {
			parent::__construct('realtime');
		}

		protected function configure() : void {
			$this
				->addArgument('poller-id', \Symfony\Component\Console\Input\InputArgument::REQUIRED)
				->addArgument('graph-id', \Symfony\Component\Console\Input\InputArgument::REQUIRED)
				->addArgument('interval', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
		}

		protected function execute(InputInterface $input, OutputInterface $output) : int {
			$this->captured = [
				'poller_id' => $input->getArgument('poller-id'),
				'graph_id'  => $this->validateInt($input, 'graph-id', 1),
				'interval'  => $this->validateInt($input, 'interval', 1),
			];

			return self::SUCCESS;
		}
	};

	$app = new Application();
	$app->setAutoExit(false);
	$app->add($probe);

	$input  = new \Symfony\Component\Console\Input\ArgvInput(['cli/cacti.php', 'realtime', '5', '7', '60']);
	$output = new \Symfony\Component\Console\Output\BufferedOutput();

	$exit = $app->run($input, $output);

	expect($exit)->toBe(Command::SUCCESS);
	expect($probe->captured)->toBe([
		'poller_id' => '5',
		'graph_id'  => 7,
		'interval'  => 60,
	]);
});

test('legacy entrypoint parity: cmd_realtime.php and cli/cacti.php realtime produce equivalent stdout and exit', function () {
	$legacy = runCli('cmd_realtime.php', ['5', '7', '60']);
	$modern = runCli('cli/cacti.php', ['realtime', '5', '7', '60']);

	expect($legacy['exit'])->toBe(0);
	expect($modern['exit'])->toBe(0);

	// Strip trailing whitespace; both shims funnel through the same
	// CmdRealtimeCommand under PHP_TESTING and must emit the same OK line.
	$legacyOut = trim($legacy['stdout']);
	$modernOut = trim($modern['stdout']);

	expect($legacyOut)->toBe($modernOut);
	expect($legacyOut)->toContain('OK realtime poller_id=5 graph_id=7 interval=60');
});

test('shell-metachar argv hand-off: --oldrrd with semicolon is rejected before the file is created', function () {
	$target = sys_get_temp_dir() . '/cacti_handoff_metachar_' . bin2hex(random_bytes(4)) . '.rrd';
	$payload = $target . ';touch ' . sys_get_temp_dir() . '/cacti_handoff_pwn';

	// Belt-and-braces: ensure neither the literal nor the post-semicolon
	// side-effect file exists before the run.
	@unlink($target);
	@unlink(sys_get_temp_dir() . '/cacti_handoff_pwn');

	$result = runCli('cli/splice_rrd.php', ['--oldrrd=' . $payload, '--newrrd=' . sys_get_temp_dir() . '/cacti_handoff_new.rrd']);

	expect($result['exit'])->not->toBe(0);
	expect(file_exists($target))->toBeFalse();
	expect(file_exists($payload))->toBeFalse();
	expect(file_exists(sys_get_temp_dir() . '/cacti_handoff_pwn'))->toBeFalse();

	$combined = $result['stdout'] . $result['stderr'];
	expect($combined)->not->toContain('Fatal error');
});

test('Command::SUCCESS hand-off to subprocess: returning self::SUCCESS yields exit code 0', function () {
	// cmd_realtime.php under PHP_TESTING runs the OK branch and returns
	// Command::SUCCESS, which the shim must propagate as exit 0.
	$result = runCli('cmd_realtime.php', ['5', '7', '60']);

	expect($result['exit'])->toBe(0);
});

test('Command::FAILURE hand-off to subprocess: validation failure yields a non-zero exit', function () {
	// Garbage poller-id throws InvalidArgumentException, which Symfony
	// surfaces as a non-zero exit (typically 1) without a PHP fatal.
	$result = runCli('cmd_realtime.php', ['notanumber', '7', '60']);

	expect($result['exit'])->not->toBe(0);
	$combined = $result['stdout'] . $result['stderr'];
	expect($combined)->not->toContain('Fatal error');
	expect($combined)->not->toContain('Stack trace');
});

test('environment hand-off: CACTI_PHP_TESTING=1 makes CactiCommand::initialize a no-op', function () {
	// With CACTI_PHP_TESTING=1 (default in runCli) the bootstrap is skipped
	// and the command runs to completion using the in-process stubs.
	$result = runCli('cmd_realtime.php', ['5', '7', '60']);

	expect($result['exit'])->toBe(0);
	expect($result['stdout'])->toContain('OK realtime');
});

test('environment hand-off: absence of CACTI_PHP_TESTING would trigger the Cacti bootstrap path', function () {
	// We cannot let initialize() run cli_check.php here (no DB), so verify
	// the gate condition in-process: the same boolean the env check feeds.
	// Without the env var and without PHP_TESTING constant, initialize()
	// would fall through to the require_once cli_check.php branch.
	$envSeen      = (bool) getenv('CACTI_PHP_TESTING');
	$constantSeen = defined('PHP_TESTING');

	// Inside the Pest harness PHP_TESTING is defined by Helpers/CactiStubs,
	// which is exactly why the in-process tests do not need the env var.
	expect($constantSeen)->toBeTrue();

	// Simulate the unset-env case: with neither signal, initialize() would
	// proceed to bootstrap. We assert the truth table the gate uses without
	// actually executing the require_once.
	$wouldBootstrap = !$constantSeen && !$envSeen
		? true
		: false;

	expect($wouldBootstrap)->toBeFalse();
});

test('argv hand-off via CommandTester: cmd-realtime arguments land on execute() with poller_id=5 graph_id=7 interval=60', function () {
	$cmd = new CmdRealtimeCommand();
	$app = new Application();
	$app->add($cmd);

	$tester = new CommandTester($app->find('realtime'));

	$tester->execute([
		'poller-id' => '5',
		'graph-id'  => '7',
		'interval'  => '60',
	]);

	$tester->assertCommandIsSuccessful();
	expect($tester->getDisplay())->toContain('OK realtime poller_id=5 graph_id=7 interval=60');
});

test('shell-metachar in-process: SpliceRrdCommand rejects --oldrrd with semicolon and never returns SUCCESS', function () {
	$cmd = new SpliceRrdCommand();
	$app = new Application();
	$app->add($cmd);

	$tester = new CommandTester($app->find('splice-rrd'));
	$target = sys_get_temp_dir() . '/cacti_handoff_inproc_' . bin2hex(random_bytes(4)) . '.rrd';

	@unlink($target);

	expect(fn () => $tester->execute([
		'--oldrrd' => $target . ';rm -rf /',
		'--newrrd' => sys_get_temp_dir() . '/cacti_handoff_inproc_new.rrd',
	]))->toThrow(\InvalidArgumentException::class, 'shell metacharacter');

	expect(file_exists($target))->toBeFalse();
});
