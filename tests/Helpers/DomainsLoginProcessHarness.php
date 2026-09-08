<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/**
 * Run the shipped domains_login_process() in a child process with LDAP and
 * database calls stubbed. Request values come from get_nfilter_request_var().
 *
 * @param array<string, mixed> $scenario
 *
 * @return array<string, mixed>
 */
function cacti_test_load_cacti_ldap_filter(string $src) : void {
	if (function_exists('cacti_ldap_filter')) {
		return;
	}

	$start = strpos($src, 'function cacti_ldap_filter(');

	if ($start === false) {
		throw new RuntimeException('cacti_ldap_filter() not found');
	}

	$depth = 0;
	$len   = strlen($src);

	for ($i = strpos($src, '{', $start); $i < $len; $i++) {
		if ($src[$i] === '{') {
			$depth++;
		} elseif ($src[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				eval(substr($src, $start, $i - $start + 1));

				return;
			}
		}
	}

	throw new RuntimeException('cacti_ldap_filter() is unbalanced');
}

function cacti_test_run_domains_login_process_1_2(array $scenario) : array {
	$root = dirname(__DIR__, 2);
	$src  = file_get_contents($root . '/lib/auth.php');
	$start = strpos($src, 'function domains_login_process(');

	if ($start === false) {
		throw new RuntimeException('domains_login_process() not found');
	}

	$depth = 0;
	$len   = strlen($src);

	for ($i = strpos($src, '{', $start); $i < $len; $i++) {
		if ($src[$i] === '{') {
			$depth++;
		} elseif ($src[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				$body = substr($src, $start, $i - $start + 1);

				break;
			}
		}
	}

	if (!isset($body)) {
		throw new RuntimeException('domains_login_process() is unbalanced');
	}

	$harness = <<<'PHP'
<?php
$scenario = json_decode($argv[1], true);

$GLOBALS['req']              = $scenario['request'];
$GLOBALS['domains']          = $scenario['domains'];
$GLOBALS['ldap_ok']          = $scenario['ldap_ok'];
$GLOBALS['search_ok']        = $scenario['search_ok'];
$GLOBALS['search_false']     = $scenario['search_false'];
$GLOBALS['auth_false']       = $scenario['auth_false'];
$GLOBALS['auth_error_num']   = $scenario['auth_error_num'];
$GLOBALS['user_row']         = $scenario['user_row'];
$GLOBALS['after_copy_row']   = $scenario['after_copy_row'];
$GLOBALS['template_user']    = $scenario['template_user'];
$GLOBALS['template_row']     = $scenario['template_row'];
$GLOBALS['cn_full_name']     = $scenario['cn_full_name'];
$GLOBALS['cn_email']         = $scenario['cn_email'];
$GLOBALS['cn_response']      = $scenario['cn_response'];
$GLOBALS['lockout']          = $scenario['lockout'];
$GLOBALS['copied']           = false;
$GLOBALS['ldap_calls']       = 0;
$GLOBALS['lockout_calls']    = 0;
$GLOBALS['copy_calls']       = 0;

$realm     = 0;
$error     = false;
$error_msg = '';

function get_nfilter_request_var($name, $default = '') {
	return $GLOBALS['req'][$name] ?? $default;
}

function __(...$args) {
	return vsprintf((string) $args[0], array_slice($args, 1));
}

function get_client_addr() {
	return '203.0.113.9';
}

function cacti_log(...$args) {
}

function cacti_sizeof($array) {
	return is_array($array) ? count($array) : 0;
}

function get_auth_realms($login = false) {
	$realms = array('0' => array('name' => 'Local', 'selected' => false));

	foreach ($GLOBALS['domains'] as $domain_id) {
		$realms[1000 + $domain_id] = array('name' => 'Domain ' . $domain_id, 'selected' => false);
	}

	return $realms;
}

function auth_checkclear_lockout($username, $realm) {
}

function auth_process_lockout_check($username, $realm) {
	return !empty($GLOBALS['lockout']);
}

function auth_process_lockout($username, $realm) {
	$GLOBALS['lockout_calls']++;
}

function domains_ldap_search_dn($username, $realm) {
	$GLOBALS['ldap_calls']++;

	if (!empty($GLOBALS['search_false'])) {
		return false;
	}

	if (empty($GLOBALS['search_ok'])) {
		return array('error_num' => '14', 'error_text' => 'Unable to find users DN');
	}

	return array('error_num' => '0', 'error_text' => '', 'dn' => 'uid=' . $username . ',dc=example,dc=com');
}

function domains_ldap_auth($username, $password = '', $dn = '', $realm = 0) {
	$GLOBALS['ldap_calls']++;

	if (!empty($GLOBALS['auth_false'])) {
		return false;
	}

	if (!empty($GLOBALS['ldap_ok'])) {
		return array('error_num' => '0', 'error_text' => '');
	}

	return array('error_num' => $GLOBALS['auth_error_num'], 'error_text' => 'Authentication Failure');
}

function domains_ldap_search_cn($username, $cn, $realm) {
	return $GLOBALS['cn_response'];
}

function user_copy(...$args) {
	$GLOBALS['copy_calls']++;
	$GLOBALS['copied'] = true;

	return true;
}

function db_fetch_row_prepared($sql, $params = array()) {
	if (strpos($sql, 'WHERE username = ?') !== false) {
		if (!empty($GLOBALS['copied']) && is_array($GLOBALS['after_copy_row'])) {
			return $GLOBALS['after_copy_row'];
		}

		return $GLOBALS['user_row'];
	}

	if (strpos($sql, 'WHERE id = ?') !== false) {
		return $GLOBALS['template_row'];
	}

	return array();
}

function db_fetch_cell_prepared($sql, $params = array()) {
	if (strpos($sql, 'domain_name') !== false) {
		return 'ExampleDomain';
	}

	if (strpos($sql, 'user_id') !== false) {
		return $GLOBALS['template_user'];
	}

	if (strpos($sql, 'cn_full_name') !== false) {
		return $GLOBALS['cn_full_name'];
	}

	if (strpos($sql, 'cn_email') !== false) {
		return $GLOBALS['cn_email'];
	}

	if (strpos($sql, 'username') !== false) {
		return 'template';
	}

	return 0;
}

PHP;

	$harness .= $body . "\n\n";
	$harness .= '$user = domains_login_process($scenario[\'username\']);' . "\n";
	$harness .= 'print json_encode([' . "\n";
	$harness .= "\t'error'         => \$error,\n";
	$harness .= "\t'error_msg'     => \$error_msg,\n";
	$harness .= "\t'user'          => \$user,\n";
	$harness .= "\t'ldap_calls'    => \$GLOBALS['ldap_calls'],\n";
	$harness .= "\t'lockout_calls' => \$GLOBALS['lockout_calls'],\n";
	$harness .= "\t'copy_calls'    => \$GLOBALS['copy_calls']\n";
	$harness .= ']);' . "\n";

	$file = tempnam(sys_get_temp_dir(), 'cacti_login_');
	file_put_contents($file, $harness);

	$scenario += [
		'username'        => 'attacker',
		'request'         => [],
		'domains'         => [1],
		'ldap_ok'         => true,
		'search_ok'       => true,
		'search_false'    => false,
		'auth_false'      => false,
		'auth_error_num'  => 1,
		'user_row'        => [],
		'after_copy_row'  => ['id' => 9, 'username' => 'attacker', 'realm' => 1001],
		'template_user'   => 0,
		'template_row'    => [],
		'cn_full_name'    => '',
		'cn_email'        => '',
		'cn_response'     => ['error_num' => '0'],
		'lockout'         => false,
	];

	$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' ' . escapeshellarg(json_encode($scenario)) . ' 2>&1';
	$output = shell_exec($cmd);
	unlink($file);

	$result = json_decode((string) $output, true);

	if (!is_array($result)) {
		throw new RuntimeException('harness failed: ' . $output);
	}

	return $result;
}
