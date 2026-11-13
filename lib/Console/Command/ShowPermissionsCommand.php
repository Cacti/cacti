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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Native port of cli/show_perms.php.
 *
 * Reference migration from hand-rolled getopt() parsing to a symfony/console
 * command. The behaviour, output bytes, and option names match the legacy
 * script so cli/show_perms.php can forward to this command unchanged. Cacti's
 * global.php bootstrap (via cli_check.php) still supplies db_get_permissions(),
 * db_get_grants(), and the cacti_* helpers this command calls.
 */
#[AsCommand(
	name: 'permissions:show',
	description: 'Audit the permissions granted to the Cacti database user.'
)]
final class ShowPermissionsCommand extends Command {
	#[\Override]
	protected function configure() : void {
		$this
			->addOption('json', null, InputOption::VALUE_NONE, 'Report the discovered permissions as JSON.')
			->addOption('grants', ['g', 'G'], InputOption::VALUE_NONE, 'Print the raw database GRANT statements before the permission summary.')
			->setHelp(
				'Cacti utility for auditing your Cacti database permissions.  This utility' . PHP_EOL .
				'will scan your Cacti database and report any permissions that it finds.'
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) : int {
		$json   = (bool) $input->getOption('json');
		$grants = (bool) $input->getOption('grants');

		$perms = db_get_permissions(true);

		$count  = 0;
		$buffer = '';

		if ($json) {
			$buffer .= cacti_strtolower((string) json_encode($perms, JSON_PRETTY_PRINT));
		} else {
			if ($grants) {
				$pad = str_repeat('-', 20);
				$buffer .= sprintf("\n %20s %-12s %20s\n\n", $pad, '   Grants', $pad);

				foreach (db_get_grants() as $grant_row) {
					foreach ($grant_row as $grant) {
						$buffer .= $grant . PHP_EOL;
					}
				}

				$buffer .= sprintf("\n %20s %-12s %20s\n\n", $pad, ' Permission', $pad);
			}

			foreach ($perms as $perm => $value) {
				$count++;
				$buffer .= sprintf('%25s %5s    ', $perm, $value ? 'Yes' : 'No');

				if ($count % 2 == 0) {
					$buffer .= PHP_EOL;
				}
			}
		}

		/* Pair the trailing newline the legacy script emitted when an odd number
		 * of permissions left the two-column layout mid-row. */
		if ($count % 2 == 1) {
			$buffer .= PHP_EOL;
		}

		/* OUTPUT_RAW: grant strings and permission names are data, not Symfony
		 * markup, and the byte-for-byte layout is the script's public contract. */
		$output->write($buffer, false, OutputInterface::OUTPUT_RAW);

		return Command::SUCCESS;
	}
}
