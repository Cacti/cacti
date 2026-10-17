<?php
declare(strict_types = 1);
// SPDX-License-Identifier: GPL-2.0-or-later

namespace FetchRelativeTimeBehaviorTest;

function time(): int {
	return 1700001000;
}

function boost_fetch_cache_check(int $id, mixed $pipe): void {
}

function rrdtool_function_get_resstep(int $id, int $start, int $end, string $mode): int {
	\expect([$start, $end])->toBe([1700000400, 1700000700]);

	return 300;
}

function rrdtool_execute(\Cacti\Rrd\RrdCommand $command, mixed ...$options): string {
	\expect($command->arguments)->toBe([
		'/tmp/test.rrd', 'AVERAGE', '-s', '1700000400', '-e', '1700000700', '-r', '300',
	]);

	return "value\n1700000400: 1\n1700000700: 2\n1700001000: 3\n";
}

function rrdtool_parse_fetch_output(string $output, int $end, bool $unknown): array {
	return \rrdtool_parse_fetch_output($output, $end, $unknown);
}

require_once CACTI_PATH_LIBRARY . '/rrd.php';
$source = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');

if (preg_match('/function rrdtool_function_fetch\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract the production fetch function.');
}
$probe = tempnam(sys_get_temp_dir(), 'rrd-fetch');

try {
	file_put_contents($probe, "<?php\nnamespace FetchRelativeTimeBehaviorTest;\n" . $matches[0]);
	include $probe;
} finally {
	unlink($probe);
}

test('relative and absolute fetch windows use the same normalized command and output boundary', function (int $start, int $end) {
	$fetch = rrdtool_function_fetch(1, $start, $end, 0, false, '/tmp/test.rrd');

	\expect(array_keys($fetch['values'][0]))->toBe([1700000400, 1700000700]);
})->with([[-600, -300], [1700000400, 1700000700]]);

test('a control character in the data source path fails the fetch instead of throwing', function () {
	$fetch = rrdtool_function_fetch(1, 1700000400, 1700000700, 300, false, "/tmp/te\tst.rrd");

	\expect($fetch)->toBe([]);
});
