<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
$root = dirname(__DIR__, 4);
require $root . '/tests/Helpers/RequestCoverage.php';
require $root . '/tests/bootstrap-unit.php';
require $root . '/tests/Helpers/FakeMySQLPDO.php';
require $root . '/tests/Helpers/SourceSlice.php';
require_once $root . '/lib/auth.php';
require_once $root . '/lib/import.php';
require_once $root . '/lib/xml.php';

$database = new FakeMySQLPDO();
$database->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
$database->exec('CREATE TABLE settings_user (user_id INTEGER, name TEXT, value TEXT)');
$database->exec('CREATE TABLE user_auth_realm (user_id INTEGER, realm_id INTEGER)');
$database->exec('CREATE TABLE user_auth_group_realm (group_id INTEGER, realm_id INTEGER)');
$database->exec('CREATE TABLE user_auth_group_members (group_id INTEGER, user_id INTEGER)');
$database->exec('CREATE TABLE user_auth_group (id INTEGER, enabled TEXT)');
$database->exec('CREATE TABLE user_auth (id INTEGER PRIMARY KEY, username TEXT, tfa_secret TEXT)');
$database->exec("INSERT INTO user_auth VALUES (1, 'realm:alice', '')");
$database->exec('CREATE TABLE package_repositories (id INTEGER PRIMARY KEY, repo_type INTEGER, repo_location TEXT, name TEXT)');
$database_sessions["$database_hostname:$database_port:$database_default"] = $database;
$settings_user                                                            = [];
$_SESSION[SESS_USER_ID]                                                   = 1;
$_SESSION['selected_theme']                                               = 'modern';
$config[CACTI_SESSION_NAME]                                               = 'CactiBoundaryFixture';

foreach (['cacti.example:8443', '[2001:db8::1]', '[2001:db8::1]:8443'] as $host) {
	$_SERVER['HTTP_HOST'] = $host;
	$result               = json_decode(enable_2fa(1), true, 512, JSON_THROW_ON_ERROR);
	parse_str(parse_url($result['link'] ?? '', PHP_URL_QUERY) ?? '', $query);

	if ($result['status'] !== 200 || !str_contains($query['data'] ?? '', str_replace(':', '_', 'realm:alice@' . $host))) {
		throw new RuntimeException('Native enrollment rejected a port or IPv6 account label.');
	}
}

$options               = ['auth_method' => AUTH_METHOD_BASIC, 'basic_auth_fail_message' => '<b>Contact</b> support'];
$config[OPTIONS_CLI]   = array_replace($config[OPTIONS_CLI] ?? [], $options);
$_SESSION[OPTIONS_WEB] = array_replace($_SESSION[OPTIONS_WEB] ?? [], $options);
unset($_SESSION[SESS_USER_ID]);
ob_start();
auth_display_custom_error_message('<script>alert(2)</script>');
$error = ob_get_clean();

if (str_contains($error, '<script>alert(2)</script>') || !str_contains($error, '&lt;script&gt;') || !str_contains($error, '<b>Contact</b> support')) {
	throw new RuntimeException('Native authentication error rendering must escape the message and keep the administrator HTML.');
}

$directory = sys_get_temp_dir() . '/cacti-boundaries-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
mkdir($directory . '/repo', 0700);
mkdir($directory . '/outside', 0700);
file_put_contents($directory . '/repo/package.manifest', 'repo manifest');
file_put_contents($directory . '/outside/package.manifest', 'outside fixture');
symlink($directory . '/outside', $directory . '/repo/escape');
$database->prepare('INSERT INTO package_repositories VALUES (1, 1, ?, ?)')->execute([$directory . '/repo', 'fixture']);

try {
	cacti_fixture_source_function($root . '/package_import.php', 'get_repo_file', 'CactiBoundaryFixture');

	foreach (['package.manifest' => 'repo manifest', '../outside/package.manifest' => false, 'escape/package.manifest' => false] as $filename => $expected) {
		if (\CactiBoundaryFixture\get_repo_file('1', $filename, true) !== $expected) {
			throw new RuntimeException('Native repository lookup escaped its directory or rejected its manifest.');
		}
	}

	$package = $directory . '/package.xml.gz';
	file_put_contents($package, gzencode('<cacti><info><name>Fixture</name><version>1</version><copyright>Fixture</copyright><author>Fixture</author><email>fixture@example.invalid</email><homepage>&lt;img src=x onerror=alert(3)&gt;</homepage></info><publickey>' . base64_encode('fixture public key') . '</publickey></cacti>'));
	cacti_fixture_source_function($root . '/package_import.php', 'import_display_package_data', 'CactiBoundaryFixture');
	ob_start();
	\CactiBoundaryFixture\import_display_package_data([], [], 'Fixture', $package, [], false);
	$markup = ob_get_clean();

	if (str_contains($markup, '<img src=x') || !str_contains($markup, '&lt;img src=x')) {
		throw new RuntimeException('Native package metadata rendering did not escape its homepage.');
	}

	$source = file_get_contents($root . '/include/global.php');

	if (!preg_match("/\tif \(isrv\('newtheme'\)\) \{.*?\n\t\}/s", $source, $block, PREG_OFFSET_CAPTURE)) {
		throw new RuntimeException('The theme selection block is missing.');
	}
	$line = substr_count(substr($source, 0, $block[0][1]), "\n") + 1;

	foreach (['modern', '../themes/modern', '../../../tmp/modern', ['modern']] as $requested) {
		$_REQUEST['newtheme'] = $requested;
		$temporary            = tempnam(sys_get_temp_dir(), 'cacti-theme-source-');
		file_put_contents($temporary, '<?php' . str_repeat("\n", $line - 1) . $block[0][0]);
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/include/global.php');

		try {
			require $temporary;
		} finally {
			unlink($temporary);
		}
		$theme = $_SESSION['selected_theme'];

		if ($newtheme !== $requested) {
			throw new RuntimeException('Native theme selection did not receive the requested value.');
		}

		if (!is_string($theme) || str_contains($theme, '/') || !is_dir($root . '/include/themes/' . $theme)) {
			throw new RuntimeException('Native theme selection stored an unsafe path.');
		}
	}

	print "Native authentication, repository, metadata, and theme contracts passed.\n";
} finally {
	unlink($directory . '/repo/escape');
	unlink($directory . '/repo/package.manifest');
	unlink($directory . '/outside/package.manifest');

	if (isset($package)) {
		unlink($package);
	}
	rmdir($directory . '/repo');
	rmdir($directory . '/outside');
	rmdir($directory);
}
