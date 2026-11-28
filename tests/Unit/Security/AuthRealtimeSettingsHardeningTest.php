<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Security hardening regression tests for authentication, tree/branch/site
 * authorization, realtime graphing, external links, graph source disclosure,
 * session invalidation on password reset, automation credential column
 * filtering, and filepath validation in settings.php.
 */

$repoRoot = dirname(__DIR__, 3);

if (!function_exists('cacti_path_is_within')) {
	require_once $repoRoot . '/lib/functions.php';
}

/**
 * Extract a named PHP function body from a source file for targeted assertions.
 */
function extract_php_function_source(string $filePath, string $functionName) : string {
	$src   = (string) file_get_contents($filePath);
	$start = strpos($src, 'function ' . $functionName . '(');

	if ($start === false) {
		return '';
	}

	$bracePos = strpos($src, '{', $start);

	if ($bracePos === false) {
		return '';
	}

	$depth = 0;
	$len   = strlen($src);

	for ($i = $bracePos; $i < $len; $i++) {
		if ($src[$i] === '{') {
			$depth++;
		} elseif ($src[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($src, $start, $i - $start + 1);
			}
		}
	}

	return '';
}

/**
 * Extract a slice of a source file between two markers or of a fixed length.
 */
function extract_source_slice(string $src, string $startNeedle, ?string $endNeedle = null, ?int $length = null) : string {
	$start = strpos($src, $startNeedle);

	if ($start === false) {
		return '';
	}

	if ($endNeedle !== null) {
		$end = strpos($src, $endNeedle, $start);

		return $end !== false ? substr($src, $start, $end - $start) : '';
	}

	return $length !== null ? substr($src, $start, $length) : substr($src, $start);
}

/**
 * Execute a self-contained PHP snippet in an isolated child process and decode JSON output.
 *
 * @return array<string, mixed>
 */
function run_sec_isolated_php_json(string $code) : array {
	$process = proc_open([PHP_BINARY], [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	], $pipes);

	expect(is_resource($process))->toBeTrue();

	fwrite($pipes[0], "<?php\nerror_reporting(E_ALL);\nini_set('display_errors', '0');\n" . $code);
	fclose($pipes[0]);

	$stdout = (string) stream_get_contents($pipes[1]);
	$stderr = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exitCode = proc_close($process);
	expect($exitCode)->toBe(0, "Child PHP exited with {$exitCode}. STDERR: {$stderr}\nSTDOUT: {$stdout}");

	$decoded = json_decode($stdout, true);
	expect(is_array($decoded))->toBeTrue("Expected JSON object, got: {$stdout}\nSTDERR: {$stderr}");

	return $decoded;
}

describe('lib/auth.php authorization hardening', function () use ($repoRoot) {
	$authFile = $repoRoot . '/lib/auth.php';

	it('requires enabled groups in cacti_authorize_has_realm()', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'cacti_authorize_has_realm');

		expect($fn)->not->toBe('')
			->and($fn)->toContain('INNER JOIN user_auth_group AS uag')
			->and($fn)->toContain('ON uag.id = ugr.group_id')
			->and($fn)->toContain("AND uag.enabled = 'on'");
	});

	it('fails closed in get_allowed_tree_content() when no trees are allowed or requested tree is denied', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'get_allowed_tree_content');

		expect($fn)->not->toBe('')
			->and($fn)->toMatch('/if\s*\(\s*!cacti_sizeof\(\$trees\)\s*\)\s*\{\s*return\s+\[\]\s*;/s')
			->and($fn)->toMatch('/if\s*\(\s*!isset\(\$trees\[\(int\)\s*\$tree_id\]\)\s*\)\s*\{\s*return\s+\[\]\s*;/s')
			->and($fn)->toContain("gt.enabled = 'on'");
	});

	it('fails closed in get_allowed_branches() when no trees or no permitted devices exist', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'get_allowed_branches');

		expect($fn)->not->toBe('')
			->and($fn)->toMatch('/if\s*\(\s*!cacti_sizeof\(\$trees\)\s*\)\s*\{[^}]*return\s+\[\]\s*;/s')
			->and($fn)->toContain("'AND 1 = 0'");
	});

	it('filters get_allowed_sites() by permitted devices and fails closed when none are allowed', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'get_allowed_sites');

		expect($fn)->not->toBe('')
			->and($fn)->toContain('get_simple_device_perms($user_id)')
			->and($fn)->toContain('get_allowed_devices(')
			->and($fn)->toMatch('/if\s*\(\s*!cacti_sizeof\(\$hosts\)\s*\)\s*\{[^}]*return\s+\[\]\s*;/s')
			->and($fn)->toContain('h.id IN (');
	});
});

describe('graph_view.php authorization and CSRF hardening', function () use ($repoRoot) {
	$src = (string) file_get_contents($repoRoot . '/graph_view.php');

	it('enforces POST, CSRF check, and report realms on action=ajax_reports', function () use ($src) {
		$block = extract_source_slice($src, "case 'ajax_reports':", "case 'update_timespan':");

		expect($block)->toContain("(\$_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'")
			->and($block)->toContain('http_response_code(405)')
			->and($block)->toContain('csrf_check(false)')
			->and($block)->toContain('!is_realm_allowed(21) && !is_realm_allowed(22)')
			->and($block)->toContain('!is_graph_allowed($local_graph_id)')
			->and($block)->toContain('http_response_code(403)');
	});

	it('enforces show_tree view and is_tree_allowed() on action=get_node', function () use ($src) {
		$block = extract_source_slice($src, "case 'get_node':", "case 'tree_content':");

		expect($block)->toContain("!is_view_allowed('show_tree')")
			->and($block)->toContain('!is_tree_allowed($tree_id)')
			->and($block)->toContain('http_response_code(403)');

		expect(strpos($block, '!is_tree_allowed($tree_id)'))
			->toBeLessThan(strpos($block, 'api_tree_get_main('));
	});
});

describe('graph_realtime.php hardening', function () use ($repoRoot) {
	$src = (string) file_get_contents($repoRoot . '/graph_realtime.php');

	it('enforces realtime_enabled with HTTP 403 before action switch dispatch', function () use ($src) {
		$enabledPos = strpos($src, "read_config_option('realtime_enabled') != 'on'");
		$switchPos  = strpos($src, "switch (get_request_var('action'))");

		expect($enabledPos)->not->toBeFalse()
			->and($switchPos)->not->toBeFalse()
			->and($enabledPos)->toBeLessThan($switchPos)
			->and(substr($src, $enabledPos, $switchPos - $enabledPos))->toContain('http_response_code(403)');
	});

	it('anchors graph_nolegend regex and validates sess_realtime_hash as hex', function () use ($src) {
		expect($src)->toContain("'/^(true|false)$/'")
			->and($src)->toContain("preg_match('/^[a-fA-F0-9]+$/', \$_SESSION['sess_realtime_hash'])");
	});

	it('validates graph_nolegend and sess_realtime_hash patterns accurately', function (string $pattern, string $input, bool $expected) {
		expect((bool) preg_match($pattern, $input))->toBe($expected);
	})->with([
		'nolegend true'       => ['/^(true|false)$/', 'true', true],
		'nolegend false'      => ['/^(true|false)$/', 'false', true],
		'nolegend suffix'     => ['/^(true|false)$/', 'true_extra', false],
		'hex hash valid'      => ['/^[a-fA-F0-9]+$/', 'deadbeef0123456789', true],
		'hex hash invalid'    => ['/^[a-fA-F0-9]+$/', 'invalid-hash', false],
	]);

	it('checks is_graph_allowed() before shell_exec() and before cache file read in view', function () use ($src) {
		$checkPos = strpos($src, '$local_graph_id <= 0 || !is_graph_allowed($local_graph_id)');
		$shellPos = strpos($src, 'shell_exec(');

		expect($checkPos)->not->toBeFalse()
			->and($shellPos)->not->toBeFalse()
			->and($checkPos)->toBeLessThan($shellPos)
			->and($src)->toContain('cacti_escapeshellarg($hash)')
			->and($src)->toContain("\$ds_step = (int) \$graph_data_array['ds_step'];");

		$viewBlock = extract_source_slice($src, "case 'view':", null, 400);

		expect($viewBlock)->toContain('$local_graph_id <= 0 || !is_graph_allowed($local_graph_id)')
			->and($viewBlock)->toContain('http_response_code(403)')
			->and(strpos($viewBlock, 'is_graph_allowed($local_graph_id)'))
			->toBeLessThan(strpos($viewBlock, 'file_get_contents('));
	});

	it('skips set_user_setting() when Guest account is active', function () use ($src) {
		expect(substr_count($src, 'if (cacti_user_can_save_own_settings())'))->toBeGreaterThanOrEqual(2);
	});
});

describe('link.php authentication, enabled check, and content containment', function () use ($repoRoot) {
	$src = (string) file_get_contents($repoRoot . '/link.php');

	it('includes auth.php, requires enabled = on, and enforces cacti_path_is_within()', function () use ($src) {
		expect($src)->toContain("include('./include/auth.php');")
			->and($src)->toContain("\$user_auth_realm_filenames['link.php'] = -1;")
			->and($src)->toContain("AND enabled = 'on'")
			->and($src)->toContain('cacti_path_is_within($file, $basepath)')
			->and($src)->toContain('is_file($file)');
	});

	it('cacti_path_is_within() rejects sibling prefix directories', function () {
		$tmpBase    = sys_get_temp_dir() . '/cacti_link_test_' . uniqid('', true);
		$contentDir = $tmpBase . '/content';
		$siblingDir = $tmpBase . '/content_outside';

		mkdir($contentDir, 0700, true);
		mkdir($siblingDir, 0700, true);

		$insideFile  = $contentDir . '/page.html';
		$siblingFile = $siblingDir . '/page.html';

		file_put_contents($insideFile, 'ok');
		file_put_contents($siblingFile, 'no');

		try {
			expect(cacti_path_is_within($insideFile, $contentDir))->toBeTrue();
			expect(cacti_path_is_within($siblingFile, $contentDir))->toBeFalse();
		} finally {
			@unlink($insideFile);
			@unlink($siblingFile);
			@rmdir($contentDir);
			@rmdir($siblingDir);
			@rmdir($tmpBase);
		}
	});
});

describe('graph source print_source realm 27 gating', function () use ($repoRoot) {
	it('gates action=properties behind is_realm_allowed(27) in graph.php', function () use ($repoRoot) {
		$src   = (string) file_get_contents($repoRoot . '/graph.php');
		$block = extract_source_slice($src, "case 'properties':", null, 350);

		expect($block)->toContain('!is_realm_allowed(27)')
			->and(strpos($block, '!is_realm_allowed(27)'))
			->toBeLessThan(strpos($block, "\$graph_data_array['print_source']"));
	});

	it('gates show_source to print_source behind is_realm_allowed(27)', function (string $file) use ($repoRoot) {
		$src = (string) file_get_contents($repoRoot . '/' . $file);

		expect($src)->toContain("if (!isempty_request_var('show_source') && is_realm_allowed(27))");
	})->with(['graph_image.php', 'graph_json.php', 'graph_xport.php', 'graph_realtime.php']);
});

describe('user_admin.php session invalidation on password reset', function () use ($repoRoot) {
	it('deletes active sessions for target user while preserving self session when resetting own password', function () use ($repoRoot) {
		$src   = (string) file_get_contents($repoRoot . '/user_admin.php');
		$block = extract_source_slice($src, '$old_password = db_fetch_cell_prepared', '/* check duplicate username */');

		expect($block)->toContain('DELETE FROM user_auth_cache')
			->and($block)->toContain('DELETE FROM sessions')
			->and($block)->toContain('AND id != ?')
			->and($block)->toContain('session_id()');
	});
});

describe('automation rules sensitive host credential column rejection', function () use ($repoRoot) {
	it('rejects all sensitive host SNMP credential columns and allows normal columns', function () use ($repoRoot) {
		$authFile = var_export($repoRoot . '/lib/auth.php', true);

		$res = run_sec_isolated_php_json(<<<PHP
require_once {$authFile};

\$sensitive = [
	'snmp_community',
	'snmp_username',
	'snmp_password',
	'snmp_auth_protocol',
	'snmp_priv_passphrase',
	'snmp_priv_protocol',
	'snmp_context',
	'snmp_engine_id',
];

\$results = [
	'sensitive' => [],
	'safe'      => [
		'h.description' => cacti_automation_is_sensitive_host_field('h.description'),
		'empty'         => cacti_automation_is_sensitive_host_field(''),
		'null'          => cacti_automation_is_sensitive_host_field(null),
	],
];

foreach (\$sensitive as \$col) {
	\$results['sensitive'][\$col] = [
		'bare'    => cacti_automation_is_sensitive_host_field(\$col),
		'aliased' => cacti_automation_is_sensitive_host_field('h.' . \$col),
		'upper'   => cacti_automation_is_sensitive_host_field(' H.' . strtoupper(\$col) . ' '),
	];
}

echo json_encode(\$results);
PHP);

		foreach ($res['sensitive'] as $checks) {
			expect($checks['bare'])->toBeTrue();
			expect($checks['aliased'])->toBeTrue();
			expect($checks['upper'])->toBeTrue();
		}

		expect($res['safe'])->toBe([
			'h.description' => false,
			'empty'         => false,
			'null'          => false,
		]);
	});

	it('enforces cacti_automation_is_sensitive_host_field() across tree and graph rule save handlers', function (string $file) use ($repoRoot) {
		$src = (string) file_get_contents($repoRoot . '/' . $file);

		expect(substr_count($src, 'cacti_automation_is_sensitive_host_field('))->toBeGreaterThanOrEqual(2);
	})->with(['automation_tree_rules.php', 'automation_graph_rules.php']);
});

describe('settings.php filepath validation persistence guard', function () use ($repoRoot) {
	it('sets $continue = false when is_valid_pathname() fails', function () use ($repoRoot) {
		$src   = (string) file_get_contents($repoRoot . '/settings.php');
		$block = extract_source_slice(
			$src,
			"elseif (\$field_array['method'] == 'filepath')",
			"elseif (\$field_array['method'] == 'textbox_password')"
		);

		expect($block)->toMatch('/!is_valid_pathname\([^{]+\)\s*\{[^}]*\$errors\[36\]\s*=\s*36;[^}]*\$continue\s*=\s*false;/s');
	});

	it('validates clean and disallowed paths via is_valid_pathname()', function (string $path, bool $expected) {
		expect(is_valid_pathname($path))->toBe($expected);
	})->with([
		'cacti log'    => ['/var/log/cacti/cacti.log', true],
		'rrdtool bin'  => ['/usr/bin/rrdtool', true],
		'semicolon'    => ['/tmp/bad;path', false],
		'whitespace'   => ['/tmp/bad path', false],
	]);
});

describe('lib/auth.php cookie lifecycle, basic auth, permission reset, and domain realm hardening', function () use ($repoRoot) {
	$authFile = var_export($repoRoot . '/lib/auth.php', true);

	it('hardens remember-me cookie parsing, revocation order, disabled-account rejection, and 90-day retention', function () use ($authFile) {
		$res = run_sec_isolated_php_json(<<<PHP
\$GLOBALS['events']         = [];
\$GLOBALS['cell_queries']   = [];
\$GLOBALS['exec_queries']   = [];
\$GLOBALS['user_row']       = [];
\$GLOBALS['cache_found']    = 7;

function cacti_sizeof(mixed \$v) : int { return is_array(\$v) ? count(\$v) : 0; }
function read_config_option(string \$k) : string { return \$k === 'auth_cache_enabled' ? 'on' : ''; }
function db_table_exists(string \$t) : bool { return true; }
function get_guest_account() : int { return 0; }
function get_client_addr() : string { return '192.0.2.10'; }
function cacti_log(string \$m, bool \$o = false, string \$f = '') : void {}
function cacti_cookie_session_set(int \$u, int \$r, string \$s) : void { \$GLOBALS['events'][] = 'cookie_set'; }
function cacti_cookie_session_logout() : void { \$GLOBALS['events'][] = 'cookie_logout'; }
function db_fetch_row_prepared(string \$sql, array \$params = []) : array { return \$GLOBALS['user_row']; }
function db_fetch_cell_prepared(string \$sql, array \$params = []) : mixed {
	\$GLOBALS['cell_queries'][] = ['sql' => \$sql, 'params' => \$params];
	return str_contains(\$sql, 'FROM user_auth_cache') ? \$GLOBALS['cache_found'] : 0;
}
function db_execute_prepared(string \$sql, array \$params = []) : void {
	\$GLOBALS['exec_queries'][] = ['sql' => \$sql, 'params' => \$params];
	if (str_contains(\$sql, 'DELETE FROM user_auth_cache')) {
		\$GLOBALS['events'][] = 'db_delete';
	}
}

require_once {$authFile};

\$_COOKIE['cacti_remembers'] = ['not-a-string'];
clear_auth_cookie();
\$nonStringEvents = \$GLOBALS['events'];
\$nonStringCheck  = check_auth_cookie();

\$GLOBALS['events'] = [];
\$_COOKIE['cacti_remembers'] = 'malformed_single_part';
clear_auth_cookie();
\$malformedEvents = \$GLOBALS['events'];
\$malformedCheck  = check_auth_cookie();

\$GLOBALS['events'] = [];
\$_COOKIE['cacti_remembers'] = '7,badrealm,tok';
clear_auth_cookie();
\$badRealmEvents = \$GLOBALS['events'];
\$badRealmCheck  = check_auth_cookie();

\$GLOBALS['events'] = [];
\$_COOKIE['cacti_remembers'] = '7,0,validtoken';
clear_auth_cookie();
\$validClearOrder = \$GLOBALS['events'];

\$GLOBALS['exec_queries'] = [];
\$GLOBALS['cell_queries'] = [];
\$GLOBALS['user_row']     = ['id' => 7, 'realm' => 0, 'username' => 'alice', 'enabled' => ''];
\$disabledCheck           = check_auth_cookie();
\$disabledDeletes         = \$GLOBALS['exec_queries'];

\$GLOBALS['exec_queries'] = [];
\$GLOBALS['cell_queries'] = [];
\$GLOBALS['user_row']     = ['id' => 7, 'realm' => 0, 'username' => 'alice', 'enabled' => 'on'];
\$enabledCheck            = check_auth_cookie();
\$cacheSelectSql          = \$GLOBALS['cell_queries'][0]['sql'] ?? '';

echo json_encode([
	'nonStringEvents' => \$nonStringEvents,
	'nonStringCheck'  => \$nonStringCheck,
	'malformedEvents' => \$malformedEvents,
	'malformedCheck'  => \$malformedCheck,
	'badRealmEvents'  => \$badRealmEvents,
	'badRealmCheck'   => \$badRealmCheck,
	'validClearOrder' => \$validClearOrder,
	'disabledCheck'   => \$disabledCheck,
	'disabledDeletes' => \$disabledDeletes,
	'enabledCheck'    => \$enabledCheck,
	'cacheSelectSql'  => \$cacheSelectSql,
]);
PHP);

		expect($res['nonStringEvents'])->toBe(['cookie_logout'])
			->and($res['nonStringCheck'])->toBeFalse()
			->and($res['malformedEvents'])->toBe(['cookie_logout'])
			->and($res['malformedCheck'])->toBeFalse()
			->and($res['badRealmEvents'])->toBe(['cookie_logout'])
			->and($res['badRealmCheck'])->toBeFalse()
			->and($res['validClearOrder'])->toBe(['db_delete', 'cookie_logout'])
			->and($res['disabledCheck'])->toBeFalse()
			->and($res['disabledDeletes'])->toHaveCount(1)
			->and($res['disabledDeletes'][0]['sql'])->toContain('DELETE FROM user_auth_cache')
			->and($res['disabledDeletes'][0]['params'])->toBe([7])
			->and($res['enabledCheck'])->toBe(7)
			->and($res['cacheSelectSql'])->toContain('INTERVAL 90 DAY');
	});

	it('revokes auth cache tokens on permission resets and ignores disabled groups in auth_user_has_access()', function () use ($authFile) {
		$res = run_sec_isolated_php_json(<<<PHP
\$_SESSION = ['sess_user_id' => 99];
\$GLOBALS['exec_queries']  = [];
\$GLOBALS['assoc_queries'] = [];
\$GLOBALS['group_rows']    = [];

function cacti_sizeof(mixed \$v) : int { return is_array(\$v) ? count(\$v) : 0; }
function read_config_option(string \$k) : string { return \$k === 'guest_user' ? '1' : ''; }
function kill_session_var(string \$k) : void {}
function array_rekey(array \$arr, string \$k, string \$v) : array {
	\$out = [];
	foreach (\$arr as \$r) { \$out[\$r[\$k]] = \$r[\$v]; }
	return \$out;
}
function db_fetch_assoc_prepared(string \$sql, array \$params = []) : array {
	\$GLOBALS['assoc_queries'][] = ['sql' => \$sql, 'params' => \$params];
	if (str_contains(\$sql, 'FROM user_auth_group_members') && !str_contains(\$sql, 'INNER JOIN')) {
		return [['user_id' => 11], ['user_id' => 12]];
	}
	return \$GLOBALS['group_rows'];
}
function db_fetch_cell_prepared(string \$sql, array \$params = []) : int { return 0; }
function db_execute_prepared(string \$sql, array \$params = []) : void {
	\$GLOBALS['exec_queries'][] = ['sql' => \$sql, 'params' => \$params];
}

require_once {$authFile};

reset_group_perms(5);
reset_user_perms(11);

\$noGroupAccess = auth_user_has_access(['id' => 11, 'show_tree' => '', 'show_list' => '', 'show_preview' => '']);
\$GLOBALS['group_rows'] = [['group_id' => 5, 'show_tree' => 'on', 'show_list' => '', 'show_preview' => '']];
\$enabledGroupAccess = auth_user_has_access(['id' => 11, 'show_tree' => '', 'show_list' => '', 'show_preview' => '']);

echo json_encode([
	'exec_queries'       => \$GLOBALS['exec_queries'],
	'group_access_sql'   => \$GLOBALS['assoc_queries'][1]['sql'] ?? '',
	'noGroupAccess'      => \$noGroupAccess,
	'enabledGroupAccess' => \$enabledGroupAccess,
]);
PHP);

		expect($res['exec_queries'])->toHaveCount(4)
			->and($res['exec_queries'][0]['sql'])->toContain('DELETE FROM user_auth_cache')
			->and($res['exec_queries'][0]['params'])->toBe([11, 12])
			->and($res['exec_queries'][1]['sql'])->toContain('UPDATE user_auth')
			->and($res['exec_queries'][1]['params'])->toBe([11, 12])
			->and($res['exec_queries'][2]['sql'])->toContain('DELETE FROM user_auth_cache')
			->and($res['exec_queries'][2]['params'])->toBe([11])
			->and($res['group_access_sql'])->toContain('INNER JOIN user_auth_group AS uag')
			->and($res['group_access_sql'])->toContain("AND uag.enabled = 'on'")
			->and($res['group_access_sql'])->toContain('uag.show_tree, uag.show_list, uag.show_preview')
			->and($res['noGroupAccess'])->toBeFalse()
			->and($res['enabledGroupAccess'])->toBeTrue();
	});

	it('hardens get_basic_auth_username(), auth_basic_session_recheck(), and cacti_auth_transition()', function () use ($authFile) {
		$res = run_sec_isolated_php_json(<<<PHP
\$config = [];
\$GLOBALS['auth_method'] = '2';
\$GLOBALS['guest_id']    = 50;
\$GLOBALS['destroyed']   = 0;
\$GLOBALS['restarted']   = 0;

function cacti_sizeof(mixed \$v) : int { return is_array(\$v) ? count(\$v) : 0; }
function read_config_option(string \$k) : string { return \$k === 'auth_method' ? \$GLOBALS['auth_method'] : ''; }
function get_guest_account() : int { return \$GLOBALS['guest_id']; }
function cacti_log(string \$m, bool \$o = false, string \$f = '', int \$l = 0) : void {}
function kill_session_var(string \$k) : void {}
function db_fetch_cell_prepared(string \$sql, array \$params = []) : string { return ''; }
function cacti_session_destroy() : void {
	\$GLOBALS['destroyed']++;
	\$_SESSION = [];
}
function cacti_session_start(bool \$regen = false) : void {
	if (\$regen) {
		\$GLOBALS['restarted']++;
	}
}
if (!defined('POLLER_VERBOSITY_MEDIUM')) { define('POLLER_VERBOSITY_MEDIUM', 3); }

require_once {$authFile};

\$_SERVER = ['HTTP_REMOTE_USER' => 'spoof', 'HTTP_PHP_AUTH_USER' => 'spoof2'];
\$forgedHeaders = get_basic_auth_username();

\$_SERVER = ['REMOTE_USER' => 'realuser', 'PHP_AUTH_USER' => 'fallback'];
\$prefersRemote = get_basic_auth_username();

\$_SERVER = ['PHP_AUTH_USER' => 'phpuser'];
\$allowsPhpAuth = get_basic_auth_username();
\$config['auth_basic_allow_php_auth_user'] = false;
\$blocksPhpAuth = get_basic_auth_username();
\$config['auth_basic_allow_php_auth_user'] = true;

\$_SERVER = ['REMOTE_USER' => 'alice'];
\$_SESSION = [];
cacti_auth_transition(10, 'login');
\$transitionBound = \$_SESSION['sess_basic_auth_user'] ?? null;

\$_SESSION = ['sess_user_id' => 10, 'sess_basic_auth_user' => 'alice'];
\$keepValid = auth_basic_session_recheck('alice', ['id' => 10, 'enabled' => 'on', 'locked' => '']);

\$_SESSION = ['sess_user_id' => 50, 'sess_basic_auth_user' => 'guest-up'];
\$keepGuest = auth_basic_session_recheck('guest-up', []);

\$_SESSION = ['sess_user_id' => 10, 'sess_basic_auth_user' => 'alice'];
\$dropChanged = auth_basic_session_recheck('bob', ['id' => 10, 'enabled' => 'on', 'locked' => '']);

\$_SESSION = ['sess_user_id' => 10, 'sess_basic_auth_user' => 'alice'];
\$dropDisabled = auth_basic_session_recheck('alice', ['id' => 10, 'enabled' => '', 'locked' => '']);

\$_SESSION = ['sess_user_id' => 10, 'sess_basic_auth_user' => 'alice'];
\$dropLocked = auth_basic_session_recheck('alice', ['id' => 10, 'enabled' => 'on', 'locked' => 'on']);

\$_SESSION = ['sess_user_id' => 10];
\$dropUnbound = auth_basic_session_recheck('alice', ['id' => 10, 'enabled' => 'on', 'locked' => '']);

echo json_encode([
	'forgedHeaders'   => \$forgedHeaders,
	'prefersRemote'   => \$prefersRemote,
	'allowsPhpAuth'   => \$allowsPhpAuth,
	'blocksPhpAuth'   => \$blocksPhpAuth,
	'transitionBound' => \$transitionBound,
	'keepValid'       => \$keepValid,
	'keepGuest'       => \$keepGuest,
	'dropChanged'     => \$dropChanged,
	'dropDisabled'    => \$dropDisabled,
	'dropLocked'      => \$dropLocked,
	'dropUnbound'     => \$dropUnbound,
	'destroyed'       => \$GLOBALS['destroyed'],
	'restarted'       => \$GLOBALS['restarted'],
]);
PHP);

		expect($res['forgedHeaders'])->toBeFalse()
			->and($res['prefersRemote'])->toBe('realuser')
			->and($res['allowsPhpAuth'])->toBe('phpuser')
			->and($res['blocksPhpAuth'])->toBeFalse()
			->and($res['transitionBound'])->toBe('alice')
			->and($res['keepValid'])->toBeTrue()
			->and($res['keepGuest'])->toBeTrue()
			->and($res['dropChanged'])->toBeFalse()
			->and($res['dropDisabled'])->toBeFalse()
			->and($res['dropLocked'])->toBeFalse()
			->and($res['dropUnbound'])->toBeFalse()
			->and($res['destroyed'])->toBe(4)
			->and($res['restarted'])->toBe(4);
	});

	it('rejects non-domain realms and unconfigured templates in domains_login_process()', function () use ($authFile) {
		$res = run_sec_isolated_php_json(<<<PHP
\$GLOBALS['req']        = [];
\$GLOBALS['ldap_calls'] = 0;

function cacti_sizeof(mixed \$v) : int { return is_array(\$v) ? count(\$v) : 0; }
function __(string \$s, mixed ...\$a) : string { return \$a ? vsprintf(\$s, \$a) : \$s; }
function get_client_addr() : string { return '192.0.2.20'; }
function cacti_log(string \$m, bool \$o = false, string \$f = '') : void {}
function read_config_option(string \$k) : string { return \$k === 'auth_method' ? '4' : ''; }
function get_filter_request_var(string \$k) : int { return (int) (\$GLOBALS['req'][\$k] ?? 0); }
function get_nfilter_request_var(string \$k) : string { return (string) (\$GLOBALS['req'][\$k] ?? ''); }
function db_fetch_assoc(string \$sql) : array { return [['domain_id' => 1, 'domain_name' => 'Corp']]; }
function db_fetch_cell(string \$sql) : int { return 1; }
function db_fetch_cell_prepared(string \$sql, array \$params = []) : mixed {
	return str_contains(\$sql, 'SELECT domain_name') ? 'Corp' : 0;
}
function db_fetch_row_prepared(string \$sql, array \$params = []) : array {
	if (str_contains(\$sql, 'FROM user_domains_ldap')) {
		return ['server' => 'ldap.example.org', 'group_require' => ''];
	}
	return [];
}
class Ldap {
	public string \$username = '';
	public string \$password = '';
	public string \$dn = '';
	public string \$host = '';
	public bool \$group_require = false;
	public function Search() : array {
		\$GLOBALS['ldap_calls']++;
		return ['error_num' => 0, 'dn' => 'uid=alice,dc=example,dc=org'];
	}
	public function Authenticate() : array {
		\$GLOBALS['ldap_calls']++;
		return ['error_num' => 0];
	}
}

require_once {$authFile};

\$runCase = static function (int \$realmVal, string \$cred) : array {
	global \$realm, \$error, \$error_msg;
	\$error     = false;
	\$error_msg = '';
	\$GLOBALS['req'] = ['realm' => \$realmVal, 'login_password' => \$cred];
	\$user = domains_login_process('alice');
	return ['user' => \$user, 'error' => \$error, 'msg' => \$error_msg];
};

\$sampleCred   = str_repeat('x', 8);
\$unknownRealm = \$runCase(9999, \$sampleCred);
\$realmTwo     = \$runCase(2, \$sampleCred);
\$realmZero    = \$runCase(0, \$sampleCred);
\$callsBefore  = \$GLOBALS['ldap_calls'];
\$noTemplate   = \$runCase(1001, \$sampleCred);

echo json_encode([
	'unknownRealm' => \$unknownRealm,
	'realmTwo'     => \$realmTwo,
	'realmZero'    => \$realmZero,
	'callsBefore'  => \$callsBefore,
	'noTemplate'   => \$noTemplate,
	'callsAfter'   => \$GLOBALS['ldap_calls'],
]);
PHP);

		expect($res['unknownRealm']['error'])->toBeTrue()
			->and($res['unknownRealm']['user'])->toBe([])
			->and($res['realmTwo']['error'])->toBeTrue()
			->and($res['realmTwo']['user'])->toBe([])
			->and($res['realmZero']['error'])->toBeTrue()
			->and($res['realmZero']['user'])->toBe([])
			->and($res['callsBefore'])->toBe(0)
			->and($res['noTemplate']['error'])->toBeTrue()
			->and($res['noTemplate']['user'])->toBe([])
			->and($res['callsAfter'])->toBe(2);
	});
});

describe('include/auth.php Web Basic Auth and realm check enforcement', function () use ($repoRoot) {
	$src = (string) file_get_contents($repoRoot . '/include/auth.php');

	it('rechecks upstream Basic Auth identity, refuses disabled accounts, and halts on locked accounts', function () use ($src) {
		$basicBlock = extract_source_slice($src, 'if ($auth_method == 2) {', 'if (isset($guest_account))');

		expect($basicBlock)->toContain('sanitize_search_string($username)')
			->and($basicBlock)->toContain('auth_basic_session_recheck($username, $current_user)')
			->and($basicBlock)->toContain("\$current_user['enabled'] != 'on'")
			->and($basicBlock)->toContain("\$_SESSION['sess_basic_auth_user'] = \$username;")
			->and($basicBlock)->toContain('auth_display_custom_error_message(')
			->and($basicBlock)->not->toContain('return false;')
			->and($basicBlock)->not->toContain('return true;');
	});

	it('falls through to per-page realm authorization from the guest account branch', function () use ($src) {
		$guestBlock = extract_source_slice($src, 'if (isset($guest_account))', 'if (!isset($guest_account)');

		expect($guestBlock)->not->toBe('')
			->and($guestBlock)->not->toContain('return true;');
	});
});
