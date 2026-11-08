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

use Cacti\Console\Command\ShowPermissionsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/*
 * ShowPermissionsCommand runs the real db_get_permissions()/db_get_grants()
 * against an in-memory SQLite handle installed as the default Cacti
 * connection. MySQL's SHOW GRANTS has no SQLite equivalent, so the handle
 * answers it from a fixture table; everything else in lib/database.php runs
 * unmodified.
 */

/**
 * SQLite handle that answers SHOW GRANTS FOR CURRENT_USER from a fixture table.
 */
final class ShowPermsGrantsPDO extends PDO {
	/**
	 * Open the in-memory database and create the grants fixture table.
	 */
	public function __construct() {
		parent::__construct('sqlite::memory:');
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->exec('CREATE TABLE grants_fixture (id INTEGER PRIMARY KEY, grant_text TEXT)');
	}

	/**
	 * Rewrite SHOW GRANTS to a SELECT over the fixture table.
	 *
	 * @param string $query   The SQL Cacti prepares
	 * @param array  $options PDO prepare options
	 *
	 * @return PDOStatement|false The prepared statement
	 */
	#[\Override]
	public function prepare(string $query, array $options = []): PDOStatement|false {
		if (preg_match('/^\s*SHOW\s+GRANTS\s+FOR\s+CURRENT_USER\s*$/i', $query)) {
			$query = 'SELECT grant_text AS "Grants for cactiuser@localhost" FROM grants_fixture ORDER BY id';
		}

		return parent::prepare($query, $options);
	}

	/**
	 * Add one GRANT line to the fixture.
	 *
	 * @param string $grant The GRANT statement SHOW GRANTS would return
	 *
	 * @return void
	 */
	public function addGrant(string $grant): void {
		$stmt = parent::prepare('INSERT INTO grants_fixture (grant_text) VALUES (?)');
		$stmt->execute([$grant]);
	}
}

/**
 * Run the command with the given options and return the tester.
 *
 * @param array<string, bool> $options Command options, e.g. ['--json' => true]
 *
 * @return CommandTester The tester after execution
 */
function show_perms_run(array $options = []): CommandTester {
	$tester = new CommandTester(new ShowPermissionsCommand());
	$tester->execute($options);

	return $tester;
}

beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$this->db_globals = [$database_sessions ?? null, $database_hostname ?? null, $database_port ?? null, $database_default ?? null];

	$database_hostname = 'unit-local';
	$database_port     = 0;
	$database_default  = 'cacti';

	$this->conn = new ShowPermsGrantsPDO();

	$database_sessions = ["$database_hostname:$database_port:$database_default" => $this->conn];
});

// Put the real db_* globals back so later tests in the run do not hit this handle.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('the command is registered as permissions:show with the legacy option names', function () {
	$command    = new ShowPermissionsCommand();
	$definition = $command->getDefinition();

	expect($command->getName())->toBe('permissions:show')
		->and($definition->hasOption('json'))->toBeTrue()
		->and($definition->hasOption('grants'))->toBeTrue()
		->and($definition->getOption('grants')->getShortcut())->toBe('g|G')
		->and($command->getHelp())->toContain('auditing your Cacti database permissions');
});

test('--json prints the lower-cased permission map', function () {
	$this->conn->addGrant('GRANT SELECT, INSERT ON `cacti`.* TO `cactiuser`@`localhost`');

	$tester  = show_perms_run(['--json' => true]);
	$decoded = json_decode($tester->getDisplay(), true);

	expect($tester->getStatusCode())->toBe(Command::SUCCESS)
		->and($tester->getDisplay())->toBe(cacti_strtolower((string) json_encode(db_get_permissions(true), JSON_PRETTY_PRINT)))
		->and($decoded['cacti']['select on *'])->toBeTrue()
		->and($decoded['cacti']['insert on *'])->toBeTrue()
		->and($decoded['cacti']['delete'])->toBeFalse();
});

test('the summary closes an odd final row with a newline', function () {
	$this->conn->addGrant('GRANT SELECT ON `cacti`.* TO `cactiuser`@`localhost`');

	$tester = show_perms_run();

	// The rows are keyed by db_get_permissions()' top-level keys, exactly as
	// the legacy script printed them.
	expect($tester->getStatusCode())->toBe(Command::SUCCESS)
		->and($tester->getDisplay())->toBe(sprintf('%25s %5s    ', 'cacti', 'Yes') . PHP_EOL);
});

test('--grants prints the raw grants before a two-column summary', function () {
	$this->conn->addGrant('GRANT SELECT ON `cacti`.* TO `cactiuser`@`localhost`');
	$this->conn->addGrant('GRANT SELECT ON `mysql`.* TO `cactiuser`@`localhost`');

	$tester = show_perms_run(['--grants' => true]);
	$pad    = str_repeat('-', 20);

	$expected = sprintf("\n %20s %-12s %20s\n\n", $pad, '   Grants', $pad) .
		'GRANT SELECT ON `cacti`.* TO `cactiuser`@`localhost`' . PHP_EOL .
		'GRANT SELECT ON `mysql`.* TO `cactiuser`@`localhost`' . PHP_EOL .
		sprintf("\n %20s %-12s %20s\n\n", $pad, ' Permission', $pad) .
		sprintf('%25s %5s    ', 'cacti', 'Yes') .
		sprintf('%25s %5s    ', 'mysql', 'Yes') . PHP_EOL;

	// An even row count ends on the in-loop newline, with no extra one.
	expect($tester->getStatusCode())->toBe(Command::SUCCESS)
		->and($tester->getDisplay())->toBe($expected);
});

test('grant text is written raw, not parsed as console markup', function () {
	$this->conn->addGrant('GRANT SELECT ON `cacti`.* TO `<info>user</info>`@`localhost`');

	$tester = show_perms_run(['--grants' => true]);

	expect($tester->getDisplay())->toContain('`<info>user</info>`@`localhost`');
});
