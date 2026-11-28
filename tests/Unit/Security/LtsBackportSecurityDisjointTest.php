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
 * Regression tests for disjoint 1.2.31 LTS security backports covering
 * auth_login.php, auth_changepassword.php, cli/audit_database.php,
 * remote_agent.php, spikekill.php, color.php, and graphs_items.php.
 */

$repoRoot = dirname(__DIR__, 3);

/**
 * Extract a named PHP function definition from a source file without using eval().
 */
function lts_extract_function(string $filePath, string $functionName) : string {
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
 * Execute a self-contained PHP script in an isolated child process and decode JSON output.
 *
 * @return array<string, mixed>
 */
function lts_run_isolated_php_json(string $code) : array {
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

test('auth_login.php rejects invalid or forged domain realms and orders remember-me cookies after login transition', function () use ($repoRoot) {
	$file = $repoRoot . '/auth_login.php';
	$src  = (string) file_get_contents($file);

	expect($src)->toContain("get_filter_request_var('realm')");
	expect($src)->toContain('auth_login_session_cookie_failure(!empty($_COOKIE))');
	expect($src)->toContain("!auth_login_is_valid_domain_realm(\$frv_realm) || get_nfilter_request_var('login_password') == ''");

	$transitionPos = strpos($src, "cacti_auth_transition((int)\$user['id'], 'login')");
	$cookiePos     = strpos($src, 'set_auth_cookie($user)');
	expect($transitionPos)->not->toBeFalse();
	expect($cookiePos)->not->toBeFalse();
	expect($cookiePos)->toBeGreaterThan($transitionPos);

	$fnSource = lts_extract_function($file, 'auth_login_is_valid_domain_realm');
	expect($fnSource)->not->toBe('');

	$result = lts_run_isolated_php_json(<<<PHP
\$GLOBALS['mock_realms'] = [
	'0'  => ['name' => 'Local'],
	1001 => ['name' => 'Corp LDAP'],
];
function get_auth_realms(bool \$login = false) {
	return \$GLOBALS['mock_realms'];
}
{$fnSource}
\$withDomains = [
	'realm_0'    => auth_login_is_valid_domain_realm(0),
	'realm_1'    => auth_login_is_valid_domain_realm(1),
	'realm_2'    => auth_login_is_valid_domain_realm(2),
	'realm_3'    => auth_login_is_valid_domain_realm(3),
	'realm_1000' => auth_login_is_valid_domain_realm(1000),
	'realm_1001' => auth_login_is_valid_domain_realm(1001),
	'realm_1002' => auth_login_is_valid_domain_realm(1002),
];
\$GLOBALS['mock_realms'] = null;
\$withoutDomains = auth_login_is_valid_domain_realm(1001);
echo json_encode(['with' => \$withDomains, 'without' => \$withoutDomains]);
PHP);

	expect($result['with'])->toBe([
		'realm_0'    => false,
		'realm_1'    => false,
		'realm_2'    => false,
		'realm_3'    => false,
		'realm_1000' => false,
		'realm_1001' => true,
		'realm_1002' => false,
	]);
	expect($result['without'])->toBeFalse();
});

test('auth_changepassword.php revokes server-side remember-me tokens and escapes the username', function () use ($repoRoot) {
	$src = (string) file_get_contents($repoRoot . '/auth_changepassword.php');

	$clearPos  = strpos($src, 'clear_auth_cookie();');
	$logoutPos = strpos($src, 'cacti_cookie_logout();');
	expect($clearPos)->not->toBeFalse();
	expect($logoutPos)->not->toBeFalse();
	expect($clearPos)->toBeLessThan($logoutPos);

	expect($src)->toContain("html_escape(\$user['username'])");
	expect($src)->not->toContain("print \$user['username'];");
});

test('cli/audit_database.php keeps DB credentials in a 0600 defaults file and escapes shell arguments', function () use ($repoRoot) {
	$file = $repoRoot . '/cli/audit_database.php';
	$src  = (string) file_get_contents($file);

	expect($src)->toContain('--defaults-extra-file=');
	expect($src)->toContain('cacti_escapeshellarg($db_shell)');
	expect($src)->toContain('cacti_escapeshellarg($php_binary)');
	expect($src)->toContain('cacti_escapeshellarg($old)');
	expect($src)->toContain('unlink($defaults_file);');
	expect($src)->not->toContain("' -p' . cacti_escapeshellarg(\$database_password)");
	expect($src)->toContain('cacti_sizeof(report_audit_results()) ? 1 : 0');

	$optFn = lts_extract_function($file, 'audit_database_option_value');
	$defFn = lts_extract_function($file, 'audit_database_defaults_file');
	expect($optFn)->not->toBe('');
	expect($defFn)->not->toBe('');

	$result = lts_run_isolated_php_json(<<<PHP
{$optFn}
{$defFn}
\$escaped = audit_database_option_value("p\\a\"s\ns\rt\t");
\$nul     = audit_database_option_value("bad\0pass");

\$localPath = audit_database_defaults_file('cacti', 's"ecret', 'localhost', '3306');
\$localMode = \$localPath !== false ? (fileperms(\$localPath) & 0777) : -1;
\$localBody = \$localPath !== false ? (string) file_get_contents(\$localPath) : '';
if (\$localPath !== false && file_exists(\$localPath)) {
	unlink(\$localPath);
}

\$remotePath = audit_database_defaults_file('cacti', 'pass', 'db.internal', '3307');
\$remoteBody = \$remotePath !== false ? (string) file_get_contents(\$remotePath) : '';
if (\$remotePath !== false && file_exists(\$remotePath)) {
	unlink(\$remotePath);
}

\$badPath = audit_database_defaults_file("bad\0user", 'pass', 'localhost', '3306');

echo json_encode([
	'escaped'    => \$escaped,
	'nul'        => \$nul,
	'localMode'  => \$localMode,
	'localBody'  => \$localBody,
	'remoteBody' => \$remoteBody,
	'badPath'    => \$badPath,
]);
PHP);

	expect($result['escaped'])->toBe('"p\\\\a\\"s\\ns\\rt\\t"');
	expect($result['nul'])->toBeFalse();
	expect($result['localMode'])->toBe(0600);
	expect($result['localBody'])->toContain('[client]');
	expect($result['localBody'])->toContain('password="s\\"ecret"');
	expect($result['localBody'])->not->toContain('port=');
	expect($result['remoteBody'])->toContain('port="3307"');
	expect($result['badPath'])->toBeFalse();
});

test('remote_agent.php authorizes on REMOTE_ADDR and validates delegated effective_user accounts', function () use ($repoRoot) {
	$file = $repoRoot . '/remote_agent.php';

	$authFn = lts_extract_function($file, 'remote_client_authorized');
	expect($authFn)->toContain("\$_SERVER['REMOTE_ADDR'] ?? false");
	$authCodeOnly = preg_replace('~/\*.*?\*/~s', '', $authFn);
	expect($authCodeOnly)->not->toContain('get_client_addr');

	$graphFn = lts_extract_function($file, 'get_graph_data');
	expect($graphFn)->toContain("remote_agent_validate_effective_user(get_request_var('effective_user'))");
	expect($graphFn)->toContain('http_response_code(403)');

	$valFn = lts_extract_function($file, 'remote_agent_validate_effective_user');
	expect($valFn)->not->toBe('');

	$result = lts_run_isolated_php_json(<<<PHP
function db_fetch_cell_prepared(string \$sql, array \$params) {
	\$users = [1 => 'on', 2 => '', 3 => null];
	return \$users[\$params[0]] ?? false;
}
{$valFn}
echo json_encode([
	'enabled'  => remote_agent_validate_effective_user('1'),
	'disabled' => remote_agent_validate_effective_user(2),
	'missing'  => remote_agent_validate_effective_user(99),
	'zero'     => remote_agent_validate_effective_user(0),
	'negative' => remote_agent_validate_effective_user(-5),
	'alpha'    => remote_agent_validate_effective_user('admin'),
]);
PHP);

	expect($result)->toBe([
		'enabled'  => 1,
		'disabled' => false,
		'missing'  => false,
		'zero'     => false,
		'negative' => false,
		'alpha'    => false,
	]);
});

test('spikekill.php requires is_graph_allowed and accumulates results across RRD files', function () use ($repoRoot) {
	$src = (string) file_get_contents($repoRoot . '/spikekill.php');

	expect($src)->toContain("\$local_graph_id = get_filter_request_var('local_graph_id');");
	expect($src)->toContain('if (is_realm_allowed(1043) && is_graph_allowed($local_graph_id))');
	expect($src)->toContain("\$results .= (\$results !== '' ? \"\\n\" : '') . (\$result ? \$spiker->get_output() : \$spiker->get_errors());");
});

test('color.php hardens CSV import against SQLi/XSS, protects CSV export formulas, and requires POST on mutations', function () use ($repoRoot) {
	$file = $repoRoot . '/color.php';
	$src  = (string) file_get_contents($file);

	expect($src)->toContain('color_require_post();');
	expect($src)->toContain("cacti_csv_safe(\$color['name'])");
	expect($src)->toContain("cacti_csv_safe(\$color['hex'])");

	$fnSource = lts_extract_function($file, 'color_import_processor');
	expect($fnSource)->not->toBe('');

	$result = lts_run_isolated_php_json(<<<PHP
\$GLOBALS['executed'] = [];
\$GLOBALS['existing'] = ['ABCDEF'];
\$GLOBALS['allow_update'] = false;

function cacti_sizeof(\$val) : int {
	return is_array(\$val) ? count(\$val) : 0;
}
function html_escape(string \$val) : string {
	return htmlspecialchars(\$val, ENT_QUOTES, 'UTF-8');
}
function isset_request_var(string \$name) : bool {
	return \$name === 'allow_update' && \$GLOBALS['allow_update'];
}
function db_fetch_row_prepared(string \$sql, array \$params) : array {
	return in_array(\$params[0], \$GLOBALS['existing'], true) ? ['hex' => \$params[0]] : [];
}
function db_execute_prepared(string \$sql, array \$params) : bool {
	\$GLOBALS['executed'][] = ['sql' => trim(preg_replace('/\\s+/', ' ', \$sql)), 'params' => \$params];
	return true;
}
{$fnSource}

\$badHeader = ['onlyname', 'foo'];
\$badHeaderRes = color_import_processor(\$badHeader);

\$rows = [
	'name,hex',
	'"Red, Bright",FF00AA',
	'"<script>alert(1)</script>",ZZZ\' OR 1=1--',
	'"Existing Color",ABCDEF',
];
\$insertRes = color_import_processor(\$rows);
\$insertExecuted = \$GLOBALS['executed'];

\$GLOBALS['executed'] = [];
\$GLOBALS['allow_update'] = true;
\$updateRows = ['hex,name', '123456,"Blue <b>Tag</b>"'];
\$updateRes = color_import_processor(\$updateRows);
\$updateExecuted = \$GLOBALS['executed'];

echo json_encode([
	'badHeaderRes'   => \$badHeaderRes,
	'insertRes'      => \$insertRes,
	'insertExecuted' => \$insertExecuted,
	'updateRes'      => \$updateRes,
	'updateExecuted' => \$updateExecuted,
]);
PHP);

	expect($result['badHeaderRes'][0])->toContain('HEADER LINE PROCESSING ERROR');
	expect($result['insertRes'][0])->toContain('HEADER LINE PROCESSED OK');
	expect($result['insertRes'][1])->toContain('INSERT SUCCEEDED: (FF00AA,Red, Bright)');
	expect($result['insertRes'][2])->toContain('INVALID HEX');
	expect($result['insertRes'][2])->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
	expect($result['insertRes'][2])->not->toContain('<script>');
	expect($result['insertRes'][3])->toContain('INSERT SKIPPED, EXISTING');
	expect($result['insertExecuted'])->toHaveCount(1);
	expect($result['insertExecuted'][0]['params'])->toBe(['FF00AA', 'Red, Bright']);
	expect($result['updateRes'][1])->toContain('INSERT SUCCEEDED: (123456,Blue &lt;b&gt;Tag&lt;/b&gt;)');
	expect($result['updateExecuted'][0]['sql'])->toContain('ON DUPLICATE KEY UPDATE');
	expect($result['updateExecuted'][0]['params'])->toBe(['123456', 'Blue <b>Tag</b>']);
});

test('graphs_items.php validates alpha, dashes, textalign, hard_return, and SHIFT/TICK values', function () use ($repoRoot) {
	$file = $repoRoot . '/graphs_items.php';
	$src  = (string) file_get_contents($file);

	expect($src)->toContain("'alpha', '^[0-9A-Fa-f]{2}\$'");
	expect($src)->toContain("'dashes', '^[0-9]+(?:\\.[0-9]+)?(?:,[0-9]+(?:\\.[0-9]+)?)*\$'");
	expect($src)->toContain("'textalign', '^(left|right|justified|center)\$'");
	expect($src)->toContain("'hard_return', '^((on)|)\$'");

	$fnSource = lts_extract_function($file, 'graphs_items_value_pattern');
	expect($fnSource)->not->toBe('');

	$result = lts_run_isolated_php_json(<<<PHP
define('GRAPH_ITEM_TYPE_TIC', 30);
define('GRAPH_ITEM_TYPE_HRULE', 2);
define('GRAPH_ITEM_TYPE_LINE1', 4);
{$fnSource}

\$shiftPat = '/' . graphs_items_value_pattern(GRAPH_ITEM_TYPE_LINE1, 'on') . '/';
\$tickPat  = '/' . graphs_items_value_pattern(GRAPH_ITEM_TYPE_TIC, '') . '/';
\$hrulePat = graphs_items_value_pattern(GRAPH_ITEM_TYPE_HRULE, '');
\$dashesPat = '/^[0-9]+(?:\\.[0-9]+)?(?:,[0-9]+(?:\\.[0-9]+)?)*\$/';
\$alphaPat  = '/^[0-9A-Fa-f]{2}\$/';
\$alignPat  = '/^(left|right|justified|center)\$/';

echo json_encode([
	'shift_ok'    => (bool) preg_match(\$shiftPat, '-86400'),
	'shift_bad'   => (bool) preg_match(\$shiftPat, '3600:LINE1'),
	'tick_ok'     => (bool) preg_match(\$tickPat, '-0.25'),
	'tick_bad'    => (bool) preg_match(\$tickPat, '0.1;id'),
	'hrule_pat'   => \$hrulePat,
	'dashes_ok'   => (bool) preg_match(\$dashesPat, '5,2.5,1'),
	'dashes_bad'  => (bool) preg_match(\$dashesPat, '5:COMMENT'),
	'alpha_ok'    => (bool) preg_match(\$alphaPat, '7f'),
	'alpha_bad'   => (bool) preg_match(\$alphaPat, 'FF:STACK'),
	'align_ok'    => (bool) preg_match(\$alignPat, 'justified'),
	'align_bad'   => (bool) preg_match(\$alignPat, 'left:HRULE'),
]);
PHP);

	expect($result)->toBe([
		'shift_ok'   => true,
		'shift_bad'  => false,
		'tick_ok'    => true,
		'tick_bad'   => false,
		'hrule_pat'  => '',
		'dashes_ok'  => true,
		'dashes_bad' => false,
		'alpha_ok'   => true,
		'alpha_bad'  => false,
		'align_ok'   => true,
		'align_bad'  => false,
	]);
});
