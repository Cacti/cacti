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
require_once dirname(__DIR__, 2) . '/lib/CmdRealtimeCommand.php';

if (!class_exists(\Symfony\Component\Console\Application::class)) {
	test('symfony/console is available', function () {
		expect(false)->toBeTrue('symfony/console must be installed via composer');
	});

	return;
}

function makeRealtimeTester() : CommandTester {
	$cmd = new CmdRealtimeCommand();
	$app = new \Symfony\Component\Console\Application();
	$app->add($cmd);

	return new CommandTester($app->find('realtime'));
}

test('realtime succeeds with valid positional arguments', function () {
	$tester = makeRealtimeTester();

	$tester->execute([
		'poller-id' => '1',
		'graph-id'  => '42',
		'interval'  => '5',
	]);

	$tester->assertCommandIsSuccessful();
	expect($tester->getDisplay())->toContain('OK realtime poller_id=1 graph_id=42 interval=5');
});

test('realtime succeeds with session token poller-id', function () {
	$tester = makeRealtimeTester();
	$poller_id = str_repeat('a', 64);

	$tester->execute([
		'poller-id' => $poller_id,
		'graph-id'  => '42',
		'interval'  => '5',
	]);

	$tester->assertCommandIsSuccessful();
	expect($tester->getDisplay())->toContain('OK realtime poller_id=' . $poller_id . ' graph_id=42 interval=5');
});

test('realtime rejects malformed poller-id with a clear error', function () {
	$tester = makeRealtimeTester();

	expect(fn () => $tester->execute([
		'poller-id' => 'abc',
		'graph-id'  => '42',
		'interval'  => '5',
	]))->toThrow(\InvalidArgumentException::class, 'poller-id');
});

test('realtime rejects negative graph-id', function () {
	$tester = makeRealtimeTester();

	expect(fn () => $tester->execute([
		'poller-id' => '1',
		'graph-id'  => '-1',
		'interval'  => '5',
	]))->toThrow(\InvalidArgumentException::class, 'graph-id');
});

test('realtime rejects missing required argument with usage hint', function () {
	$tester = makeRealtimeTester();

	expect(fn () => $tester->execute([
		'poller-id' => '1',
		// graph-id and interval intentionally omitted
	]))->toThrow(\Symfony\Component\Console\Exception\RuntimeException::class);
});

test('realtime rejects float-like value 3.14 for interval', function () {
	$tester = makeRealtimeTester();

	expect(fn () => $tester->execute([
		'poller-id' => '1',
		'graph-id'  => '42',
		'interval'  => '3.14',
	]))->toThrow(\InvalidArgumentException::class, 'interval');
});
