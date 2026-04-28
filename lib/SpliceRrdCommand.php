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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Splice two RRDfiles. The legacy cli/splice_rrd.php parsed --oldrrd, --newrrd,
 * --finrrd, --owner, --debug, --dryrun by hand and pushed the resulting paths
 * straight into shell_exec strings. This Command keeps the same flag surface
 * but rejects shell metacharacters before any path reaches rrdtool, even
 * though we also escape downstream.
 */
#[AsCommand(name: 'splice-rrd', description: 'Splice two RRDfiles together')]
class SpliceRrdCommand extends CactiCommand {
	/**
	 * Reject any byte that has meaning to /bin/sh. escapeshellarg is also
	 * applied by callers, but this gives us a fail-fast layer that does not
	 * depend on locale or shell version.
	 */
	private const SHELL_METACHAR_RE = '/[\;\|\&\$\`\<\>\n\r\*\?\(\)\{\}\[\]\!\\\\\'\"]/';

	protected function configure() : void {
		$this
			->addOption('oldrrd', null, InputOption::VALUE_REQUIRED, 'Old RRDfile that holds historical data')
			->addOption('newrrd', null, InputOption::VALUE_REQUIRED, 'New RRDfile that holds recent data')
			->addOption('finrrd', null, InputOption::VALUE_REQUIRED, 'Final RRDfile path; defaults to newrrd.new')
			->addOption('owner', 'R', InputOption::VALUE_REQUIRED, 'chown the resulting file to this user (root only)')
			->addOption('backup', 'B', InputOption::VALUE_NONE, 'Backup originals before writing')
			->addOption('overwrite', 'O', InputOption::VALUE_NONE, 'Overwrite newrrd in place')
			->addOption('debug', 'd', InputOption::VALUE_NONE, 'Verbose debug output')
			->addOption('dryrun', 'D', InputOption::VALUE_NONE, 'Process but do not write the final file');
	}

	protected function execute(InputInterface $input, OutputInterface $output) : int {
		$oldrrd    = (string) $input->getOption('oldrrd');
		$newrrd    = (string) $input->getOption('newrrd');
		$finrrd    = (string) $input->getOption('finrrd');
		$owner     = (string) ($input->getOption('owner') ?? '');
		$overwrite = (bool) $input->getOption('overwrite');
		$dryrun    = (bool) $input->getOption('dryrun');
		$debug     = (bool) $input->getOption('debug');

		if ($oldrrd === '') {
			throw new \InvalidArgumentException('--oldrrd is required.');
		}

		if ($newrrd === '') {
			throw new \InvalidArgumentException('--newrrd is required.');
		}

		$this->assertSafePath($oldrrd, '--oldrrd');
		$this->assertSafePath($newrrd, '--newrrd');

		if ($finrrd !== '') {
			$this->assertSafePath($finrrd, '--finrrd');
		}

		if ($owner !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $owner) !== 1) {
			throw new \InvalidArgumentException(sprintf('--owner %s is not a valid Unix username.', var_export($owner, true)));
		}

		$this->assertWithinAllowedRoots($oldrrd, '--oldrrd');
		$this->assertWithinAllowedRoots($newrrd, '--newrrd');

		if ($finrrd !== '') {
			$this->assertWithinAllowedRoots($finrrd, '--finrrd');
		}

		// In tests we stop here. We have already exercised every validation
		// branch, and exec'ing rrdtool is not the unit under test.
		if (defined('PHP_TESTING')) {
			$output->writeln(sprintf('OK splice-rrd oldrrd=%s newrrd=%s finrrd=%s%s%s', $oldrrd, $newrrd, $finrrd, $overwrite ? ' overwrite' : '', $dryrun ? ' dryrun' : ''));

			return self::SUCCESS;
		}

		require_once __DIR__ . '/splice_rrd_functions.php';

		$rc = splice_rrd_run($oldrrd, $newrrd, $finrrd, $owner, $overwrite, $dryrun, $debug);

		return $rc === 0 ? self::SUCCESS : self::FAILURE;
	}

	/**
	 * Reject any path containing shell metacharacters or null bytes. This is
	 * a defense layer in front of escapeshellarg; any future change that
	 * forgets to quote a path will still hit this guard.
	 */
	private function assertSafePath(string $path, string $label) : void {
		if (strpos($path, "\0") !== false) {
			throw new \InvalidArgumentException(sprintf('%s contains a null byte.', $label));
		}

		if (preg_match(self::SHELL_METACHAR_RE, $path) === 1) {
			throw new \InvalidArgumentException(sprintf('%s contains a shell metacharacter.', $label));
		}
	}

	/**
	 * Constrain RRD operations to a small set of roots. A misconfigured cron
	 * with --finrrd=/etc/passwd should fail before rrdtool ever runs.
	 *
	 * NOTE: this is a behaviour change vs the legacy cli/splice_rrd.php,
	 * which accepted arbitrary paths. Operators who used the legacy script
	 * to splice RRDs outside CACTI_PATH_RRA must now move those files into
	 * the standard tree first or run the legacy script directly. Documented
	 * in docs/security/cli-commands.md under the splice-rrd Command entry.
	 */
	private function assertWithinAllowedRoots(string $path, string $label) : void {
		$resolved = realpath($path);

		if ($resolved === false) {
			$resolved = realpath(dirname($path));

			if ($resolved !== false) {
				$resolved .= DIRECTORY_SEPARATOR . basename($path);
			} else {
				$resolved = $path;
			}
		}

		$roots = [];

		if (defined('CACTI_PATH_RRA')) {
			$roots[] = (string) CACTI_PATH_RRA;
		}

		// Tests and ad-hoc operator runs land under sys_get_temp_dir(); allow
		// it but only when running outside the Cacti bootstrap so production
		// paths cannot point at /tmp.
		if (!defined('CACTI_PATH_RRA') || defined('PHP_TESTING')) {
			$tmp = realpath(sys_get_temp_dir());

			if ($tmp !== false) {
				$roots[] = $tmp;
			}
		}

		if ($roots === []) {
			return;
		}

		foreach ($roots as $root) {
			if ($root === '') {
				continue;
			}

			$prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			if (str_starts_with($resolved, $prefix) || $resolved === $root) {
				return;
			}
		}

		throw new \InvalidArgumentException(sprintf('%s "%s" is outside the allowed RRA path(s): %s', $label, $path, implode(', ', $roots)));
	}
}
