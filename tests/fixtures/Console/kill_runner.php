<?php
/*
 * Runs a legacy command whose child is killed mid-flight, and prints the status
 * LegacyScriptCommand reports. Its own process because the kill has to land on
 * a child this script owns.
 */

declare(strict_types = 1);

require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';

use Cacti\Console\Command\LegacyScriptCommand;
use Cacti\Console\Input\RawArgvInput;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\BufferedOutput;

$root    = $argv[1];
$pidfile = tempnam(sys_get_temp_dir(), 'cacti-sleeper-');

$application = new Application('test');
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->add(new LegacyScriptCommand('sleeper:run', 'sleeper', $root));

if (function_exists('pcntl_fork') && function_exists('posix_kill')) {
	$pid = pcntl_fork();

	if ($pid === 0) {
		/* Signal the pid the child published, never a pattern match: pgrep
		 * would reach every matching process on the host, so two suite runs on
		 * one runner would kill each other's children. */
		$deadline = microtime(true) + 5;
		$target   = 0;

		while ($target === 0 && microtime(true) < $deadline) {
			$published = is_file($pidfile) ? trim((string) file_get_contents($pidfile)) : '';

			if ($published !== '') {
				$target = (int) $published;
			} else {
				usleep(20000);
			}
		}

		if ($target > 0) {
			posix_kill($target, SIGKILL);
		}

		exit(0);
	}
}

$status = $application->run(new RawArgvInput(['bin/cacti', 'sleeper:run', $pidfile]), new BufferedOutput());

@unlink($pidfile);

print $status . "\n";
