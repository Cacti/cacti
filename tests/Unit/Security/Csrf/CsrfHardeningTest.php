<?php

$globalPath = __DIR__ . '/../../../../include/global.php';
$csrfPath = __DIR__ . '/../../../../include/vendor/csrf/csrf-magic.php';

test('state-mutating routes explicitly require POST', function () {
	$base = dirname(__DIR__, 4);
	$routes = array(
		'automation_devices.php'     => array('purge', 'actions'),
		'automation_graph_rules.php' => array('save', 'actions', 'item_movedown', 'item_moveup', 'item_remove', 'qedit', 'remove'),
		'automation_templates.php'   => array('save', 'ajax_dnd', 'actions', 'movedown', 'moveup', 'remove'),
		'automation_tree_rules.php'  => array('save', 'actions', 'change_leaf', 'item_movedown', 'item_moveup', 'item_remove', 'remove'),
		'color.php'                  => array('save', 'actions', 'remove'),
		'data_queries.php'           => array('save', 'actions', 'item_moveup_dssv', 'item_movedown_dssv', 'item_remove_dssv', 'item_moveup_gsv', 'item_movedown_gsv', 'item_remove_gsv', 'item_remove_confirm', 'item_remove', 'remove'),
		'rrdcheck.php'                => array('purge'),
	);

	foreach ($routes as $route => $actions) {
		$source = file_get_contents($base . '/' . $route);
		foreach ($actions as $action) {
			expect($source)->toMatch("/case ['\"]" . preg_quote($action, '/') . "['\"]:\\s+csrf_require_post\\(\\);/");
		}
	}

	$plugins = file_get_contents($base . '/plugins.php');
	expect($plugins)->toMatch("/if \\(\\\$mode !== 'check'\\) \\{\\s+csrf_require_post\\(\\);/");
});

test('csrf-magic fallback cookie sets httponly flag', function () use ($csrfPath) {
    $source = file_get_contents($csrfPath);
    expect($source)->toContain("'httponly'");
});

test('csrf-magic fallback cookie sets samesite flag', function () use ($csrfPath) {
    $source = file_get_contents($csrfPath);
    expect($source)->toContain("'samesite'");
});

test('csrf-magic uses hash_equals for token comparison', function () use ($csrfPath) {
    $source = file_get_contents($csrfPath);
    expect($source)->toContain('hash_equals');
});

test('Cacti selects sha256 and scopes the fallback cookie to its URL path', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/csrf.php');
	expect($source)->toContain("csrf_conf('hash', 'sha256')")
		->toContain("csrf_conf('url_path', \$config['url_path'])");
});

test('csrf-magic never logs raw request arrays or secret values', function () use ($csrfPath) {
	$source = file_get_contents($csrfPath);
	expect($source)->not->toContain("var_export(\$_POST")
		->not->toContain("var_export(\$_GET")
		->not->toContain("var_export(\$secret")
		->not->toContain("var_export(\$buffer");
});

test('browser mutation handoff uses same-origin token-bearing POST helpers', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/layout.js');
	expect($source)->toContain("target.origin !== window.location.origin")
		->toContain('postData.__csrf_magic = csrfMagicToken')
		->toContain('submitPageUsingPost')
		->toContain('loadPageUsingPostUrl');
});

test('clean installs use an in-session bootstrap secret without masking external secret errors', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/csrf.php');
	expect($source)->toContain("\$_SESSION['cacti_bootstrap_csrf_secret']")
		->toContain('(!$external_secret || cacti_csrf_install_pending())')
		->toContain('configured external Cacti CSRF secret is unavailable or invalid');
});

test('installer permission checks use the bounded external secret reader', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/installer.php');
	$permissionCheck = substr(
		$source,
		strpos($source, 'private function getPermissions()'),
		strpos($source, 'private function getPHPModules()') - strpos($source, 'private function getPermissions()')
	);

	expect($permissionCheck)->toContain('cacti_csrf_read_external_secret($path)')
		->toContain('cacti_csrf_external_path_is_safe($path)')
		->not->toContain('file_get_contents($path)');
});
