<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace ShelllessBackgroundProcessTest;

$GLOBALS['config'] = array('cacti_server_os' => 'unix');
$GLOBALS['shellless_force_proc_failure'] = false;
$GLOBALS['shellless_process_calls']       = array();
$GLOBALS['shellless_process_logs']        = array();

/**
 * Captures process-launch warnings.
 *
 * @param string $message Log message.
 * @param bool   $output  Whether to print the message.
 * @param string $environ Log subsystem.
 *
 * @return void
 */
function cacti_log($message, $output, $environ) {
	$GLOBALS['shellless_process_logs'][] = array($message, $output, $environ);
}

/**
 * Captures process options and delegates successful launches to PHP.
 *
 * @param array      $command     Executable and argument list.
 * @param array      $descriptors Child file-descriptor configuration.
 * @param array      $pipes       Child pipes populated by proc_open.
 * @param string|null $cwd        Child working directory.
 * @param array|null $environment Child environment.
 * @param array      $options     Process creation options.
 *
 * @return resource|false Process resource, or false for a forced failure.
 */
function proc_open($command, $descriptors, &$pipes, $cwd = null, $environment = null, $options = array()) {
	$GLOBALS['shellless_process_calls'][] = array($command, $descriptors, $options);

	if ($GLOBALS['shellless_force_proc_failure']) {
		return false;
	}

	return \proc_open($command, $descriptors, $pipes, $cwd, $environment, $options);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/poller.php');
preg_match('/function exec_background_process\(.*?^}\R/ms', $source, $matches);
eval('namespace ShelllessBackgroundProcessTest;' . $matches[0]);
preg_match('/function poller_enable_child_reaping\(.*?^}\R/ms', $source, $matches);
eval('namespace ShelllessBackgroundProcessTest;' . $matches[0]);
preg_match('/function poller_php_binary\(.*?^}\R/ms', $source, $matches);
eval('namespace ShelllessBackgroundProcessTest;' . $matches[0]);
preg_match('/function poller_cactid_arguments\(.*?^}\R/ms', $source, $matches);
eval('namespace ShelllessBackgroundProcessTest;' . $matches[0]);

beforeEach(function () {
	$GLOBALS['config']['cacti_server_os']    = 'unix';
	$GLOBALS['shellless_force_proc_failure'] = false;
	$GLOBALS['shellless_process_calls']       = array();
	$GLOBALS['shellless_process_logs']        = array();
});

test('background arguments are passed without shell interpretation', function () {
	$output = tempnam(sys_get_temp_dir(), 'cacti-process-output-');
	$marker = tempnam(sys_get_temp_dir(), 'cacti-process-marker-');
	unlink($marker);
	$script = tempnam(sys_get_temp_dir(), 'cacti-process-script-') . '.php';
	file_put_contents($script, '<?php file_put_contents($argv[1], json_encode(array_slice($argv, 2)));');

	$payload = '; touch ' . $marker;
	$started = exec_background_process(PHP_BINARY, array($script, $output, $payload));

	for ($attempt = 0; $attempt < 100 && filesize($output) === 0; $attempt++) {
		clearstatcache(true, $output);
		usleep(20000);
	}

	expect($started)->toBeTrue()
		->and(json_decode(file_get_contents($output), true))->toBe(array($payload))
		->and(file_exists($marker))->toBeFalse()
		->and($GLOBALS['shellless_process_calls'][0][0])->toBe(array(realpath(PHP_BINARY), $script, $output, $payload))
		->and($GLOBALS['shellless_process_calls'][0][2])->toBe(array('bypass_shell' => true));

	unlink($script);
	unlink($output);
});

test('process creation failures are logged without a shell fallback', function () {
	$GLOBALS['config']['cacti_server_os']    = 'win32';
	$GLOBALS['shellless_force_proc_failure'] = true;

	expect(exec_background_process(PHP_BINARY, array('argument')))->toBeFalse()
		->and($GLOBALS['shellless_process_calls'][0][1][0][1])->toBe('NUL')
		->and($GLOBALS['shellless_process_logs'])->toHaveCount(1)
		->and($GLOBALS['shellless_process_logs'][0][0])->toContain('Unable to start');
});

test('background launches do not wait for the child to exit', function () {
	$started_at = microtime(true);

	$started = exec_background_process(PHP_BINARY, array('-r', 'usleep(1000000);'));
	$elapsed = microtime(true) - $started_at;

	expect($started)->toBeTrue()
		->and($elapsed)->toBeLessThan(0.5);
});

test('cactid poller options cover configured and fallback binaries', function () {
	expect(poller_php_binary('/configured/php'))->toBe('/configured/php')
		->and(poller_php_binary(''))->toBe(PHP_BINARY)
		->and(poller_cactid_arguments('/srv/cacti', false))->toBe(array('-q', '/srv/cacti/poller.php', '--force'))
		->and(poller_cactid_arguments('/srv/cacti', true))->toBe(array('-q', '/srv/cacti/poller.php', '--force', '--debug'));
});

test('cactid enables automatic Unix child reaping', function () {
	$signals = array();

	expect(poller_enable_child_reaping('win32', function () {
		throw new \RuntimeException('Windows must not install a Unix signal handler.');
	}))->toBeFalse()
		->and(poller_enable_child_reaping('unix', function ($signal, $handler) use (&$signals) {
			$signals[] = array($signal, $handler);

			return true;
		}))->toBeTrue()
		->and($signals)->toBe(array(array(SIGCHLD, SIG_IGN)));
});

test('invalid executables fail closed', function () {
	expect(exec_background_process('/path/that/does/not/exist', array()))->toBeFalse()
		->and($GLOBALS['shellless_process_logs'])->toHaveCount(1)
		->and($GLOBALS['shellless_process_logs'][0][0])->toContain('Refusing to start');
});

test('cactid supplies the poller command as discrete arguments', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/cactid.php');

	expect($source)->toContain("exec_background_process(\$php_binary, \$command)")
		->and($source)->toContain('poller_enable_child_reaping()')
		->and($source)->toContain("poller_php_binary(read_config_option('path_php_binary'))")
		->and($source)->toContain("poller_cactid_arguments(\$config['base_path'], \$debug)");
});
