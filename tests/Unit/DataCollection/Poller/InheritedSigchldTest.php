<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('CLI startup restores inherited SIGCHLD without changing daemon child reaping', function () {
	if (PHP_OS_FAMILY === 'Windows' || !function_exists('pcntl_signal') || !function_exists('pcntl_signal_get_handler') || !function_exists('proc_open') || !function_exists('system')) {
		$this->markTestSkipped('Unix pcntl and process execution are required.');
	}

	$root = dirname(__DIR__, 4);
	$directory = tempnam(sys_get_temp_dir(), 'cacti-sigchld-');
	unlink($directory);
	mkdir($directory);

	try {
		// Run the unchanged CLI bootstrap with only its database bootstrap replaced.
		copy($root . '/include/cli_check.php', $directory . '/cli_check.php');
		file_put_contents($directory . '/global.php', '<?php ob_start(); system($good_command, $bootstrap_exit); ob_end_clean();');
		file_put_contents($directory . '/good.php', '<?php $value = 1;');
		file_put_contents($directory . '/bad.php', '<?php $value = ;');

		$child = <<<'PHP'
$root = $argv[1];
$directory = $argv[2];
$good_command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($directory . '/good.php') . ' 2>&1';
$bad_command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($directory . '/bad.php') . ' 2>&1';
ob_start();
system($good_command, $inherited_exit);
require $directory . '/cli_check.php';
system($good_command, $good_exit);
system($bad_command, $bad_exit);
ob_end_clean();
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
// Suppress incidental logging notices from the database-free test bootstrap.
set_error_handler(function () { return true; });
$output = array();
$good_cacti_exit = cacti_exec(PHP_BINARY, array('-l', $directory . '/good.php'), $output);
$good_output = implode("\n", $output);
$bad_cacti_exit = cacti_exec(PHP_BINARY, array('-l', $directory . '/bad.php'), $output);
$command_exit = cacti_exec(PHP_BINARY, array('-r', 'exit(42);'), $output);
restore_error_handler();
echo json_encode(array(
	'inherited_exit' => $inherited_exit,
	'bootstrap_exit' => $bootstrap_exit,
	'system_exits' => array($good_exit, $bad_exit),
	'cacti_exits' => array($good_cacti_exit, $bad_cacti_exit, $command_exit),
	'lint_output' => $good_output,
));
PHP;

		$parent = <<<'PHP'
require $argv[1] . '/lib/poller.php';
if (!poller_enable_child_reaping('unix')) {
	exit(1);
}
$process = proc_open(array(PHP_BINARY, '-r', $argv[3], $argv[1], $argv[2]), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
if (!is_resource($process)) {
	exit(2);
}
$result = json_decode(stream_get_contents($pipes[1]), true);
fwrite(STDERR, stream_get_contents($pipes[2]));
fclose($pipes[1]);
fclose($pipes[2]);
// The daemon intentionally discards its children's exit statuses.
proc_close($process);
$result['parent_ignores'] = pcntl_signal_get_handler(SIGCHLD) === SIG_IGN;
echo json_encode($result);
PHP;

		$process = proc_open(array(PHP_BINARY, '-r', $parent, $root, $directory, $child), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		expect($process)->not->toBeFalse();
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		expect(proc_close($process))->toBe(0, $error);
		$result = json_decode($output, true);
		expect($result['inherited_exit'])->toBe(-1)
			->and($result['bootstrap_exit'])->toBe(0)
			->and($result['system_exits'])->toBe(array(0, 255))
			->and($result['cacti_exits'])->toBe(array(0, 255, 42))
			->and($result['lint_output'])->toContain('No syntax errors detected')
			->and($result['parent_ignores'])->toBeTrue();
	} finally {
		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
});
