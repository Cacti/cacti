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
 * cacti_escapeshellarg() and cacti_escapeshellcmd() are the only escaping the
 * tree's command construction has, and a few hundred call sites depend on them
 * without exercising them. These tests pin the behaviour that is relied on, so
 * a change to either helper shows up here rather than in a poller.
 *
 * Some of what is pinned below is characterisation, not endorsement: the empty
 * string case in particular is recorded because callers concatenate the result,
 * not because dropping an argument is the desired outcome.
 */

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', 'unix');
}

if (!defined('CACTI_ESCAPE_CHARACTER')) {
	define('CACTI_ESCAPE_CHARACTER', '"');
}

require_once CACTI_PATH_LIBRARY . '/functions.php';

beforeEach(function () : void {
	if (CACTI_SERVER_OS !== 'unix') {
		test()->markTestSkipped('the quoting rules asserted here are the unix ones');
	}
});

test('an ordinary argument comes back single quoted', function () {
	expect(cacti_escapeshellarg('public'))->toBe("'public'")
		->and(cacti_escapeshellarg('--flag=x'))->toBe("'--flag=x'");
});

test('shell metacharacters are neutralised rather than removed', function () {
	foreach (['$HOME', '`id`', 'a;b', 'a|b', 'a&b', 'a>b', 'a b'] as $value) {
		$escaped = cacti_escapeshellarg($value);

		// the payload survives verbatim, but only inside the quotes
		expect($escaped)->toBe("'" . $value . "'");
	}
});

test('carriage returns and line feeds are stripped, not quoted', function () {
	/* a newline that reached a command line would start a second command on the
	   line based protocols Cacti drives, so these are removed outright */
	expect(cacti_escapeshellarg("a\nb"))->toBe("'ab'")
		->and(cacti_escapeshellarg("a\rb"))->toBe("'ab'")
		->and(cacti_escapeshellarg("a\r\nb"))->toBe("'ab'");
});

test('an embedded single quote cannot close the quoting', function () {
	$escaped = cacti_escapeshellarg("it's");

	expect($escaped)->toBe("'it'\\''s'")
		->and(substr($escaped, 0, 1))->toBe("'")
		->and(substr($escaped, -1))->toBe("'");
});

test('a zero string is a value, not an absence', function () {
	/* '0' is falsy in PHP, so a guard written as a truth test rather than a
	   comparison against '' would drop a legitimate argument here */
	expect(cacti_escapeshellarg('0'))->toBe("'0'")
		->and(cacti_escapeshellarg('0', false))->toBe('0');
});

test('the unquoted form returns the body without its wrapping quotes', function () {
	expect(cacti_escapeshellarg('public', false))->toBe('public')
		->and(cacti_escapeshellarg('a b', false))->toBe('a b');
});

test('the unquoted form keeps the escaping that only works inside quotes', function () {
	/* Characterisation, and a caution: the caller asked for the quotes to be
	   removed, so what comes back is only safe if the caller supplies its own
	   quoting. Bare concatenation of this into a command line is not safe. */
	expect(cacti_escapeshellarg("it's", false))->toBe("it'\\''s");
});

test('an empty argument stays on the command line as an empty argument', function () {
	/* It used to return early and unquoted, which deleted the argument instead
	   of passing it empty. A separated flag then consumed whatever followed:
	   'spine -C ' . '' . ' --poller 1' left -C reading --poller as its value. */
	expect(cacti_escapeshellarg(''))->toBe("''");

	expect('spine -C ' . cacti_escapeshellarg('') . ' --poller 1')
		->toBe("spine -C '' --poller 1");
});

test('the unquoted form of an empty argument is still empty', function () {
	/* The caller asked for the quotes off and supplies its own, so there is
	   nothing left to return here. */
	expect(cacti_escapeshellarg('', false))->toBe('');
});

test('escapeshellcmd defers to the platform helper and passes an empty string through', function () {
	expect(cacti_escapeshellcmd('/usr/bin/rrdtool'))->toBe('/usr/bin/rrdtool')
		->and(cacti_escapeshellcmd(''))->toBe('')
		->and(cacti_escapeshellcmd('a;b'))->toBe('a\;b');
});

/*
 * The helpers are only worth testing if the tree actually routes through them.
 * This is the ratchet over the call sites: a new raw escapeshellarg() or
 * escapeshellcmd() anywhere outside the helpers themselves fails here.
 */
test('no command construction bypasses the cacti helpers', function () {
	$root = dirname(__DIR__, 2);

	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			function ($current) : bool {
				$name = $current->getFilename();

				if ($current->isDir()) {
					/* Dot directories are developer state, not source: a local
					   .worktrees or .claude tree holds copies of these same
					   files and would report every one of them twice. */
					if (str_starts_with($name, '.')) {
						return false;
					}

					return !in_array($name, ['vendor', 'tests', 'locales', 'log', 'cache'], true);
				}

				return str_ends_with($name, '.php');
			}
		)
	);

	$offenders = [];

	foreach ($iterator as $file) {
		$path = $file->getPathname();

		if ($path === $root . '/lib/functions.php') {
			continue;
		}

		$source = file_get_contents(CACTI_PATH_BASE . '/' . substr($path, strlen(CACTI_PATH_BASE) + 1));

		if ($source === false) {
			continue;
		}

		if (preg_match('/(?<![\w_])escapeshell(arg|cmd)\s*\(/', $source)) {
			$offenders[] = substr($path, strlen($root) + 1);
		}
	}

	expect($offenders)->toBe([]);
});
