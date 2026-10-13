<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function cacti_test_public_method_contracts(): array {
	static $contracts = null;

	if ($contracts !== null) {
		return $contracts;
	}

	// Load the production classes in a separate process. Collecting this test
	// must not alter class/function ownership in other Cacti unit test files.
	$probe   = dirname(__DIR__, 2) . '/fixtures/Core/PublicMethodContractProbe.php';
	$process = proc_open([PHP_BINARY, $probe], [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start the public API probe.');
	}

	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0) {
		throw new RuntimeException($errors);
	}

	$contracts = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

	return $contracts;
}

$baseline = json_decode(file_get_contents(
	dirname(__DIR__, 2) . '/fixtures/Core/public-method-signatures.json'
), true, 512, JSON_THROW_ON_ERROR);
$cases = [];

foreach ($baseline as $name => $contract) {
	$cases[$name] = [$name, $contract];
}

test('the public method retains its complete Cacti develop call contract', function (string $name, array $expected) {
	$actual = cacti_test_public_method_contracts();
	expect($actual)->toHaveCount(46);
	expect($actual[$name])->toBe($expected);
})->with($cases);
