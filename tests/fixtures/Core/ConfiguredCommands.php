<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ConfiguredCommands;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/lib/functions.php';
require $root . '/include/global_constants.php';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

function read_config_option(string $name) : string {
	return $GLOBALS['settings'][$name] ?? '';
}

function cacti_log(string $message, mixed ...$args) : void {
	$GLOBALS['messages'][] = $message;
}

function rrdtool_execute(string $command, mixed ...$args) : string {
	$GLOBALS['tune'] = $command;

	return '';
}

function process_args(mixed $process, array $pipes) : array {
	check(is_resource($process), 'Unable to start configured binary');
	fclose($pipes[0]);
	stream_set_blocking($pipes[1], true);
	$output = stream_get_contents($pipes[1]);
	fclose($pipes[1]);

	if (isset($pipes[2])) {
		fclose($pipes[2]);
	}
	check(proc_close($process) === 0, 'Configured binary returned an error');

	return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}

$directory = realpath(sys_get_temp_dir()) . '/cacti configured ' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
define('CACTI_SERVER_OS', 'unix');
define('CACTI_PATH_BASE', $directory);
define('CACTI_PATH_CLI', $directory);
define('CACTI_PATH_PLUGINS', $directory);
define('CACTI_CONNECTION', 'online');
$php     = $directory . '/php cli';
$rrdtool = $directory . '/rrd tool';
$printer = '<?php echo json_encode(array_slice($argv, 1));';
symlink(PHP_BINARY, $php);
file_put_contents($rrdtool, '#!' . PHP_BINARY . "\n" . $printer);
chmod($rrdtool, 0700);
file_put_contents($directory . '/script_server.php', $printer);
file_put_contents($directory . '/rebuild_poller_cache.php', '<?php file_put_contents(__DIR__ . "/received.json", json_encode(array_slice($argv, 1)));');
mkdir($directory . '/Demo Plugin', 0700);
file_put_contents($directory . '/Demo Plugin/database_upgrade.php', $printer);
register_shutdown_function(static function () use ($directory) : void {
	unlink($directory . '/Demo Plugin/database_upgrade.php');
	rmdir($directory . '/Demo Plugin');

	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}
	rmdir($directory);
});

$settings = ['path_php_binary' => $php, 'path_rrdtool' => $rrdtool];

foreach (['cli/push_out_hosts.php' => ['push_out_rebuild_cache'], 'lib/poller.php' => ['exec_poll_php'],
	'lib/dsstats.php'                 => ['dsstats_rrdtool_init'], 'lib/rrdcheck.php' => ['rrdcheck_rrdtool_init', 'rrdcheck_valid_tune_spec', 'rrdcheck_tune_rrdfile'],
	'cli/rrdresize.php'               => ['rrdtool_pipe_init'], 'cli/splice_rrd.php' => ['createRRDFileFromXML']] as $file => $functions) {
	foreach ($functions as $function) {
		\cacti_fixture_source_function($root . '/' . $file, $function, __NAMESPACE__);
	}
}

if (($argv[1] ?? '') === 'deprecated') {
	$parms = ['--description', 'North Campus'];
	register_shutdown_function(static function () use ($directory, $parms) : void {
		check($GLOBALS['received'] === $parms, 'Deprecated CLI lost its operands');
	});
	// Capture the real child result before this CLI exits and the fixture directory is removed.
	function passthru(string $command, int &$status) : void {
		\passthru($command, $status);
		$GLOBALS['received'] = json_decode(file_get_contents(CACTI_PATH_CLI . '/received.json'), true, flags: JSON_THROW_ON_ERROR);
	}
	cacti_fixture_source_block($root . '/cli/push_out_hosts.php', __NAMESPACE__, '~(print \x27WARNING: Deprecated.*?exit\(push_out_rebuild_cache\(\$parms, \$php_binary\)\);)~s', ['parms' => $parms, 'php_binary' => $php]);

	throw new \RuntimeException('Deprecated CLI did not exit');
}

foreach (['', '-'] as $invalid) {
	check(push_out_rebuild_cache([], $invalid) === 1, 'An incomplete executable setting must fail');
	check(str_contains(end($GLOBALS['messages']), 'Rejected'), 'Missing configuration diagnostic');
}
check(exec_poll_php(escapeshellarg($directory . '/script_server.php') . ' 42', false, [], null) === '["42"]', 'Direct polling command split the executable path');

foreach ([rrdtool_pipe_init($rrdtool), dsstats_rrdtool_init(), rrdcheck_rrdtool_init()] as [$process, $pipes]) {
	check(process_args($process, $pipes) === ['-'], 'RRDtool must receive its pipe operand');
}
$pipes = [];
$file  = $directory . '/device.rrd';
$spec  = 'DS:load:GAUGE:300:0:U';
check(str_contains(rrdcheck_tune_rrdfile($file, $spec, 7, true, $pipes), 'correct the issue'), 'RRD tune did not complete');
check($GLOBALS['tune'] === 'tune ' . escapeshellarg($file) . ' ' . escapeshellarg($spec), 'RRD tune operands changed');

function exec(string $command, array &$output, ?int &$status) : string|false {
	$result                    = \exec($command, $output, $status);
	$GLOBALS['command_output'] = $output;

	return $result;
}
createRRDFileFromXML('input file.xml', 'output file.rrd');
check(json_decode(end($GLOBALS['command_output']), true, flags: JSON_THROW_ON_ERROR) === ['restore', '-f', '-r', 'input file.xml', 'output file.rrd'], 'RRD restore split its operands');

$cactides = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

foreach (['cmd.php', 'cmd_realtime.php', 'remote_agent.php'] as $file) {
	$pattern = $file === 'cmd.php' ? '~(\$php_path = .*?\$cactiphp = proc_open\([^;]+;)~s' : '~(\$command = cacti_escapeshellarg\(\(string\) read_config_option\(\x27path_php_binary\x27\)\) .*?\$cactiphp = proc_open\([^;]+;)~s';

	foreach (['7', str_repeat('af', 32)] as $pollerId) {
		$locals   = cacti_fixture_source_block($root . '/' . $file, __NAMESPACE__, $pattern, ['poller_id' => $pollerId, 'cactides' => $cactides]);
		$expected = $file === 'cmd.php' ? ['--environ=cmd', '--poller=' . $pollerId, '--mode=online'] : ['realtime', $pollerId];
		check(process_args($locals['cactiphp'], $locals['pipes']) === $expected, $file . ' split script-server operands');
	}
}

foreach ([$php, ''] as $setting) {
	$settings['path_php_binary'] = $setting;
	$locals                      = cacti_fixture_source_block($root . '/cli/audit_database.php', __NAMESPACE__, '~(\$php_binary = \(string\).*?\n\t})~s');
	check($locals['php_binary'] === ($setting ?: PHP_BINARY), 'Audit selected the wrong PHP executable');
	$result = cacti_fixture_source_block($root . '/cli/audit_database.php', __NAMESPACE__, '~(exec\(cacti_escapeshellarg\(\$php_binary\).*?\$return_var\);)~s',
		['php_binary' => $locals['php_binary'], 'pname' => 'Demo Plugin', 'old' => '1.2', 'output' => [], 'return_var' => 0]);
	check($result['return_var'] === 0 && json_decode(end($result['output']), true, flags: JSON_THROW_ON_ERROR) === ['--type=large', '--force-ver=1.2'], 'Plugin database upgrade split its operands');
}
