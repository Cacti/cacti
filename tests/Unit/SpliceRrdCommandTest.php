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

use Symfony\Component\Console\Tester\CommandTester;

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/CactiCommand.php';
require_once dirname(__DIR__, 2) . '/lib/SpliceRrdCommand.php';

if (!class_exists(\Symfony\Component\Console\Application::class)) {
	test('symfony/console is available', function () {
		expect(false)->toBeTrue('symfony/console must be installed via composer');
	});

	return;
}

function makeSpliceTester() : CommandTester {
	$cmd = new SpliceRrdCommand();
	$app = new \Symfony\Component\Console\Application();
	$app->add($cmd);

	return new CommandTester($app->find('splice-rrd'));
}

beforeEach(function () {
	$this->oldrrd = sys_get_temp_dir() . '/cacti_splice_old.rrd';
	$this->newrrd = sys_get_temp_dir() . '/cacti_splice_new.rrd';

	// SpliceRrdCommand only validates path strings under PHP_TESTING; it does
	// not actually need the files to exist for these checks.
});

test('splice-rrd accepts safe paths inside the temp dir', function () {
	$tester = makeSpliceTester();

	$tester->execute([
		'--oldrrd' => $this->oldrrd,
		'--newrrd' => $this->newrrd,
	]);

	$tester->assertCommandIsSuccessful();
	expect($tester->getDisplay())->toContain('OK splice-rrd');
});

test('splice-rrd requires --oldrrd', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--newrrd' => $this->newrrd,
	]))->toThrow(\InvalidArgumentException::class, '--oldrrd');
});

test('splice-rrd requires --newrrd', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => $this->oldrrd,
	]))->toThrow(\InvalidArgumentException::class, '--newrrd');
});

test('splice-rrd rejects --oldrrd with semicolon', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => sys_get_temp_dir() . '/foo;rm -rf /tmp.rrd',
		'--newrrd' => $this->newrrd,
	]))->toThrow(\InvalidArgumentException::class, 'shell metacharacter');
});

test('splice-rrd rejects --newrrd with backtick', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => $this->oldrrd,
		'--newrrd' => sys_get_temp_dir() . '/`whoami`.rrd',
	]))->toThrow(\InvalidArgumentException::class, 'shell metacharacter');
});

test('splice-rrd rejects --finrrd with pipe', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => $this->oldrrd,
		'--newrrd' => $this->newrrd,
		'--finrrd' => sys_get_temp_dir() . '/foo|nc.rrd',
	]))->toThrow(\InvalidArgumentException::class, 'shell metacharacter');
});

test('splice-rrd rejects --oldrrd with newline', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => sys_get_temp_dir() . "/foo\nbar.rrd",
		'--newrrd' => $this->newrrd,
	]))->toThrow(\InvalidArgumentException::class, 'shell metacharacter');
});

test('splice-rrd rejects null byte in path', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => sys_get_temp_dir() . "/foo\0bar.rrd",
		'--newrrd' => $this->newrrd,
	]))->toThrow(\InvalidArgumentException::class, 'null byte');
});

test('splice-rrd rejects path outside allowed roots', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => '/etc/passwd',
		'--newrrd' => $this->newrrd,
	]))->toThrow(\InvalidArgumentException::class, 'outside the allowed');
});

test('splice-rrd rejects bogus --owner string', function () {
	$tester = makeSpliceTester();

	expect(fn () => $tester->execute([
		'--oldrrd' => $this->oldrrd,
		'--newrrd' => $this->newrrd,
		'--owner'  => '../etc/passwd',
	]))->toThrow(\InvalidArgumentException::class, 'username');
});
