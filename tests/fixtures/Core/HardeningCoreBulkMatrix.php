<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiHardeningCoreBulkFixture;

use Cacti\Auth\RememberMePolicy;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/Auth/RememberMePolicy.php';
require_once $root . '/lib/help_error_guard.php';
require_once $root . '/lib/float_rrd_temp.php';
require_once $root . '/lib/package_key_writer.php';

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', $root);
}

if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', $root . '/lib');
}

if (!defined('SIGTERM')) {
	define('SIGTERM', 15);
}

if (!defined('SIGINT')) {
	define('SIGINT', 2);
}

$GLOBALS['passed']        = 0;
$GLOBALS['events']        = [];
$GLOBALS['cookies']       = [];
$GLOBALS['fail_tmpdir']   = false;
$GLOBALS['rra_allow']     = true;
$GLOBALS['tighten_allow'] = true;
$GLOBALS['write_allow']   = true;
$GLOBALS['dir_allow']     = true;
$GLOBALS['remember_me']   = true;
$GLOBALS['auth_cache']    = 'on';
$GLOBALS['realm_cookies'] = true;

function check(mixed $actual, mixed $expected, string $label) : void {
	if ($actual !== $expected) {
		throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
	}
	$GLOBALS['passed']++;
}

function get_guest_account() : int {
	return 2;
}

function isrv(string $name) : bool {
	if ($name === 'remember_me') {
		return (bool) $GLOBALS['remember_me'];
	}

	return isset($_REQUEST[$name]);
}

function read_config_option(string $name) : string {
	if ($name === 'auth_cache_enabled') {
		return (string) $GLOBALS['auth_cache'];
	}

	if ($name === 'path_rrdtool') {
		return '/usr/bin/rrdtool';
	}

	return '';
}

function auth_realm_allows_cookies(int $realm) : bool {
	return (bool) $GLOBALS['realm_cookies'];
}

function set_auth_cookie(array $user) : void {
	$GLOBALS['cookies'][] = $user['id'] ?? null;
}

function data_source_path_within_rra(string $path) : bool {
	return (bool) $GLOBALS['rra_allow'];
}

function cacti_log(string $message, bool $output = false, string $environment = '', int $level = 0) : void {
	$GLOBALS['events'][] = ['log', $environment, $message];
}

function cacti_escapeshellcmd(string $cmd) : string {
	return $cmd;
}

function cacti_escapeshellarg(string $arg) : string {
	return escapeshellarg($arg);
}

function exec(string $command, array &$output = [], int &$result_code = 0) : string {
	$output      = [];
	$result_code = 0;

	return '';
}

function float_rrd_create_private_temp_dir(?string $base_dir = null) : string|false {
	if ($GLOBALS['fail_tmpdir']) {
		return false;
	}

	return \float_rrd_create_private_temp_dir($base_dir);
}

function float_kill_running_processes() : void {
	$GLOBALS['events'][] = ['kill'];
}

function unregister_process(string $tasktype, string $taskname, int $taskid, int $pid) : void {
	$GLOBALS['events'][] = ['unregister', $tasktype, $taskname];
}

function package_key_ensure_directory(string $directory) : bool {
	if (!$GLOBALS['dir_allow']) {
		return false;
	}

	return \package_key_ensure_directory($directory);
}

function package_key_tighten_permissions(string $path) : bool {
	if (!$GLOBALS['tighten_allow']) {
		return false;
	}

	return \package_key_tighten_permissions($path);
}

function package_key_write_private(mixed $private_key, string $destination_path) : bool {
	if (!$GLOBALS['write_allow']) {
		return false;
	}

	return \package_key_write_private($private_key, $destination_path);
}

/**
 * Run a checked-in statement block in this fixture namespace while mapping
 * coverage line numbers back to the original production file.
 */
function slice(string $file, string $first, ?string $last, array $variables) : mixed {
	global $root;

	$path  = $root . '/' . $file;
	$lines = file($path, FILE_IGNORE_NEW_LINES);
	$found = [];

	foreach ($lines as $index => $text) {
		if (trim($text) === $first) {
			$found[] = $index;
		}
	}

	if (count($found) !== 1) {
		throw new RuntimeException('Cannot uniquely locate "' . $first . '" in ' . $file);
	}
	$start = $found[0];
	$end   = $start;

	if ($last !== null) {
		$indent = strlen($lines[$start]) - strlen(ltrim($lines[$start]));
		$end    = null;

		for ($next = $start + 1; $next < count($lines); $next++) {
			if (trim($lines[$next]) === $last && strlen($lines[$next]) - strlen(ltrim($lines[$next])) === $indent) {
				$end = $next;

				break;
			}
		}

		if ($end === null) {
			throw new RuntimeException('Cannot find the end "' . $last . '" of "' . $first . '" in ' . $file);
		}
	}
	$code      = implode("\n", array_slice($lines, $start, $end - $start + 1));
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-hardening-slice-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . '; use Cacti\Auth\RememberMePolicy; return static function (array $__v) { extract($__v, EXTR_SKIP); ' . str_repeat("\n", $start) . $code . ' return get_defined_vars(); };');
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($path);

	try {
		$closure = require $temporary;
	} finally {
		unlink($temporary);
	}

	return $closure($variables);
}

function expect_exit(callable $verify) : void {
	$GLOBALS['exit_probe'] = 'armed';
	register_shutdown_function(static function () use ($verify) : void {
		if (($GLOBALS['exit_probe'] ?? '') !== 'armed') {
			return;
		}
		$verify();
		print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";

		exit(0);
	});
}

$mode = $argv[1] ?? 'main';

if ($mode === 'float-sig') {
	cacti_fixture_source_function($root . '/cli/float_rrdfiles.php', 'sig_handler', __NAMESPACE__);
	$sig_dir = \float_rrd_create_private_temp_dir();
	check(is_string($sig_dir) && is_dir($sig_dir), true, 'sig temp dir created');
	$GLOBALS['type']          = 'child';
	$GLOBALS['thread_id']     = 1;
	$GLOBALS['seebug']        = false;
	$GLOBALS['float_tmp_dir'] = $sig_dir;
	expect_exit(static function () use ($sig_dir) : void {
		check(is_dir($sig_dir), false, 'sig_handler cleaned float temp dir');
	});
	sig_handler(SIGTERM);
	$GLOBALS['exit_probe'] = 'fell-through';

	throw new RuntimeException('sig_handler did not exit');
}

if ($mode === 'genkey-dir-fail') {
	$GLOBALS['dir_allow'] = false;
	expect_exit(static function () : void {
		check(str_contains((string) ob_get_clean(), 'Unable to prepare package key directory'), true, 'genkey dir failure printed');
	});
	ob_start();
	slice('cli/genkey.php', 'if (!package_key_ensure_directory($keypair_path)) {', '}', ['keypair_path' => '/nonexistent/pki']);
	$GLOBALS['exit_probe'] = 'fell-through';

	throw new RuntimeException('genkey dir guard did not exit');
}

if ($mode === 'genkey-tighten-fail') {
	$GLOBALS['tighten_allow'] = false;
	$tmp                      = sys_get_temp_dir() . '/cacti-gk-tf-' . bin2hex(random_bytes(4));
	mkdir($tmp, 0700);
	$key_file = $tmp . '/package.key';
	file_put_contents($key_file, 'key');
	expect_exit(static function () use ($tmp, $key_file) : void {
		check(str_contains((string) ob_get_clean(), 'Unable to set restrictive permissions on package.key'), true, 'genkey tighten failure printed');
		@unlink($key_file);
		@rmdir($tmp);
	});
	ob_start();
	slice('cli/genkey.php', '$key_file = "$keypair_path/package.key";', null, ['keypair_path' => $tmp]);
	slice('cli/genkey.php', 'if ($provided && package_key_paths_match($privkey, $key_file)) {', '}', [
		'provided' => true,
		'privkey'  => $key_file,
		'privKey'  => 'key',
		'key_file' => $key_file,
	]);
	$GLOBALS['exit_probe'] = 'fell-through';

	throw new RuntimeException('genkey tighten guard did not exit');
}

if ($mode === 'genkey-write-fail') {
	$GLOBALS['write_allow'] = false;
	expect_exit(static function () : void {
		check(str_contains((string) ob_get_clean(), 'Unable to generate private key file package.key'), true, 'genkey write failure printed');
	});
	ob_start();
	slice('cli/genkey.php', 'if ($provided && package_key_paths_match($privkey, $key_file)) {', '}', [
		'provided' => false,
		'privkey'  => '',
		'privKey'  => 'key',
		'key_file' => '/tmp/unused/package.key',
	]);
	$GLOBALS['exit_probe'] = 'fell-through';

	throw new RuntimeException('genkey write guard did not exit');
}

// ---- 1. RememberMePolicy and auth_login.php ----

$local_user = ['id' => 1, 'must_change_password' => ''];
check(RememberMePolicy::canIssueToken($local_user, AUTH_METHOD_CACTI, 2, true, true, true, true), false, 'error blocks remember-me');
check(RememberMePolicy::canIssueToken($local_user, AUTH_METHOD_BASIC, 2, false, true, true, true), false, 'basic auth blocks remember-me');
check(RememberMePolicy::canIssueToken(['id' => 2, 'must_change_password' => ''], AUTH_METHOD_CACTI, 2, false, true, true, true), false, 'guest blocks remember-me');
check(RememberMePolicy::canIssueToken(['id' => 1, 'must_change_password' => 'on'], AUTH_METHOD_CACTI, 2, false, true, true, true), false, 'must_change_password blocks remember-me');
check(RememberMePolicy::canIssueToken($local_user, AUTH_METHOD_CACTI, 2, false, true, true, true), true, 'eligible local user allows remember-me');

slice('auth_login.php', "if (RememberMePolicy::canIssueToken(\$user, (int) \$auth_method, get_guest_account(), (bool) \$error, isrv('remember_me'), read_config_option('auth_cache_enabled') == 'on', auth_realm_allows_cookies((int) \$realm))) {", '}', [
	'user'        => $local_user,
	'auth_method' => AUTH_METHOD_CACTI,
	'error'       => false,
	'realm'       => 0,
]);
check($GLOBALS['cookies'], [1], 'auth_login issues cookie for eligible user');

// ---- 2. help_error_guard.php and help.php ----

$known = ['graphs.php' => 7];
check(\help_resolve_error_page('', $known, $root), null, 'empty page rejected');
check(\help_resolve_error_page("graphs.php\0", $known, $root), null, 'null byte rejected');
check(\help_resolve_error_page('?foo=bar', $known, $root), null, 'missing path rejected');
check(\help_resolve_error_page('notes.txt', $known, $root), null, 'non-php rejected');
check(\help_resolve_error_page('/cacti/graphs.php?action=view', $known, $root), 'graphs.php', 'known page resolved');
check(\help_resolve_error_page('about.php', $known, $root), 'about.php', 'top-level php resolved');
check(\help_resolve_error_page('does_not_exist_xyz.php', $known, $root), null, 'unknown php rejected');
check(\help_error_status_is_valid(500), true, '500 valid');
check(\help_error_status_is_valid(200), false, '200 invalid');

$work_help = sys_get_temp_dir() . '/cacti-help-fixture-' . bin2hex(random_bytes(6));
mkdir($work_help . '/include', 0700, true);
file_put_contents($work_help . '/include/auth.php', "<?php\n");
$orig_cwd = getcwd();

try {
	chdir($work_help);
	require_once $root . '/tests/fixtures/Core/HardeningHelpStubs.php';
	$GLOBALS['user_auth_realm_filenames'] = ['graphs.php' => 7];
	$GLOBALS['help_emails']               = [];
	$GLOBALS['help_logs']                 = [];
	$_SESSION                             = ['sess_user_id' => 1];
	$_REQUEST                             = ['error' => 500, 'page' => '<b>bad</b>'];
	require $root . '/help.php';
	check($GLOBALS['help_emails'], [], 'help.php skips invalid page');

	$_REQUEST = ['error' => 500, 'page' => 'graphs.php?id=1'];
	require $root . '/help.php';
	check(count($GLOBALS['help_emails']), 1, 'help.php sends escaped admin_email');
} finally {
	chdir($orig_cwd);
	@unlink($work_help . '/include/auth.php');
	@rmdir($work_help . '/include');
	@rmdir($work_help);
}

// ---- 3. float_rrd_temp.php and cli/float_rrdfiles.php ----

$work_float = sys_get_temp_dir() . '/cacti-float-fixture-' . bin2hex(random_bytes(6));
mkdir($work_float, 0700);
$ro_base = $work_float . '/readonly';
mkdir($ro_base, 0500);
$sym_base = $work_float . '/sym_base';
symlink($work_float, $sym_base);

try {
	check(\float_rrd_create_private_temp_dir($work_float . '/missing'), false, 'missing base rejected');
	check(\float_rrd_create_private_temp_dir($sym_base), false, 'symlink base rejected');
	check(\float_rrd_create_private_temp_dir($ro_base), false, 'readonly base rejected');

	$priv_dir = \float_rrd_create_private_temp_dir($work_float);
	check(is_string($priv_dir) && is_dir($priv_dir), true, 'private temp dir created');
	$xml_path = \float_rrd_build_xml_path($priv_dir, 42);
	check(str_starts_with($xml_path, $priv_dir . '/42_'), true, 'xml path inside private dir');

	check(\float_rrd_open_private_file('', 'wb'), false, 'empty file rejected');
	$sym_file = $priv_dir . '/sym.xml';
	symlink($work_float, $sym_file);
	check(\float_rrd_open_private_file($sym_file, 'wb'), false, 'symlink file rejected');
	check(\float_rrd_open_private_file($ro_base . '/cannot.xml', 'wb'), false, 'unwritable open rejected');
	check(\float_rrd_open_private_file('php://memory', 'wb'), false, 'unchmodable stream rejected');

	$fh = \float_rrd_open_private_file($xml_path, 'wb');
	check(is_resource($fh), true, 'private file opened');
	fwrite($fh, 'xml');
	fclose($fh);

	$log_fh = \float_rrd_open_private_file($priv_dir . '/cacti_float_rrdfiles.log', 'ab');
	check(is_resource($log_fh), true, 'debug log opened');
	fclose($log_fh);

	\float_rrd_cleanup_temp_dir('');
	\float_rrd_cleanup_temp_dir($priv_dir, true);
	check(is_file($priv_dir . '/cacti_float_rrdfiles.log'), true, 'debug log preserved');
	check(is_file($xml_path), false, 'xml removed');
	\float_rrd_cleanup_temp_dir($priv_dir, false);
	check(is_dir($priv_dir), false, 'private dir removed');

	slice('cli/float_rrdfiles.php', "require_once(CACTI_PATH_LIBRARY . '/float_rrd_temp.php');", null, []);
	cacti_fixture_source_function($root . '/cli/float_rrdfiles.php', 'float_rrdfile', __NAMESPACE__);

	$GLOBALS['rra_allow'] = false;
	check(float_rrdfile('/etc/passwd', 1, 300, 1000, 2000), false, 'outside RRA rejected');

	$fake_rrd = $work_float . '/test.rrd';
	file_put_contents($fake_rrd, 'rrd');
	$GLOBALS['rra_allow']   = true;
	$GLOBALS['fail_tmpdir'] = true;
	check(float_rrdfile($fake_rrd, 1, 300, 1000, 2000), false, 'tmpdir failure rejected');

	$GLOBALS['fail_tmpdir'] = false;
	$GLOBALS['seebug']      = true;
	float_rrdfile($fake_rrd, 1, 300, 1000, 2000);
	$GLOBALS['seebug'] = false;
	check(is_string($GLOBALS['float_tmp_dir'] ?? null) && is_dir($GLOBALS['float_tmp_dir']), true, 'float_rrdfile created private dir');
	@unlink($fake_rrd);
} finally {
	@chmod($ro_base, 0700);
	@unlink($sym_base);
	@rmdir($ro_base);
	@rmdir($work_float);
}

// ---- 4. package_key_writer.php and cli/genkey.php ----

$work_pk = sys_get_temp_dir() . '/cacti-pk-fixture-' . bin2hex(random_bytes(6));
mkdir($work_pk, 0700);
$pk_file = $work_pk . '/blocker';
file_put_contents($pk_file, 'x');
$pk_sym = $work_pk . '/sym';
symlink($work_pk, $pk_sym);

try {
	check(\package_key_ensure_directory(''), false, 'empty dir rejected');
	check(\package_key_ensure_directory($pk_sym), false, 'symlink dir rejected');
	check(\package_key_ensure_directory($work_pk), true, 'existing dir accepted');
	check(\package_key_ensure_directory($pk_file . '/child'), false, 'mkdir under file fails');
	$new_pk_dir = $work_pk . '/pki';
	check(\package_key_ensure_directory($new_pk_dir), true, 'new dir created');

	check(\package_key_paths_match($pk_file, $pk_file), true, 'same real path matches');
	check(\package_key_paths_match($work_pk . '/missing_a', $work_pk . '/missing_a'), true, 'missing identical path matches');

	check(\package_key_tighten_permissions(''), false, 'empty tighten fails');
	check(\package_key_tighten_permissions($pk_file), true, 'tighten on file succeeds');

	check(\package_key_write_private('pem', ''), false, 'empty dest fails');
	check(\package_key_write_private(123, $new_pk_dir . '/package.key'), false, 'non-key type fails');
	check(\package_key_write_private('invalid-pem', $new_pk_dir . '/package.key'), false, 'invalid pem fails');

	$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
	check($key !== false, true, 'rsa key generated');
	check(\package_key_write_private($key, $pk_file . '/child/package.key'), false, 'uncreatable parent fails');

	$dir_target = $new_pk_dir . '/existing_dir';
	mkdir($dir_target, 0700);
	check(\package_key_write_private($key, $dir_target), false, 'rename onto directory fails and cleans temp');
	rmdir($dir_target);

	$dest_key = $new_pk_dir . '/package.key';
	check(\package_key_write_private($key, $dest_key), true, 'private key written');
	check((fileperms($dest_key) & 0777), 0600, 'private key has 0600 permissions');

	slice('cli/genkey.php', "require_once(CACTI_PATH_LIBRARY . '/package_key_writer.php');", null, []);
	slice('cli/genkey.php', 'if (!package_key_ensure_directory($keypair_path)) {', '}', ['keypair_path' => $new_pk_dir]);

	ob_start();
	slice('cli/genkey.php', '$key_file = "$keypair_path/package.key";', null, ['keypair_path' => $new_pk_dir]);
	slice('cli/genkey.php', 'if ($provided && package_key_paths_match($privkey, $key_file)) {', '}', [
		'provided' => true,
		'privkey'  => $dest_key,
		'privKey'  => $key,
		'key_file' => $dest_key,
	]);
	$out1 = (string) ob_get_clean();
	check(str_contains($out1, 'existing packaging private key'), true, 'genkey tighten path succeeded');

	ob_start();
	slice('cli/genkey.php', 'if ($provided && package_key_paths_match($privkey, $key_file)) {', '}', [
		'provided' => false,
		'privkey'  => '',
		'privKey'  => $key,
		'key_file' => $dest_key,
	]);
	$out2 = (string) ob_get_clean();
	check(str_contains($out2, 'Generated private key file package.key'), true, 'genkey write path succeeded');
	@unlink($dest_key);
	@rmdir($new_pk_dir);
} finally {
	@unlink($pk_sym);
	@unlink($pk_file);
	@rmdir($work_pk);
}

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
