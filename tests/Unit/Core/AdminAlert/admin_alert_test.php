<?php
/* From the repository root:
 * php -d sendmail_path=tests/fixtures/Core/admin-alert-sendmail.sh tests/Unit/Core/AdminAlert/admin_alert_test.php
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
$root = dirname(__DIR__, 4);
chdir($root);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';

if (ini_get('sendmail_path') !== 'tests/fixtures/Core/admin-alert-sendmail.sh') {
	throw new RuntimeException('Use the capture-only sendmail_path documented above.');
}
$mailbox = tempnam(sys_get_temp_dir(), 'cacti-admin-alert-');
putenv('CACTI_ADMIN_ALERT_MAILBOX=' . $mailbox);
$cacti_locale = 'en-US';
$mail_methods = array('PHP mail');
$checks = 0;

// Only the recipient database is stubbed. Configuration lookup, admin_email,
// send_mail and PHPMailer run from their production files.
function db_fetch_row_prepared($sql, $args) {
	if (strpos($sql, 'FROM user_auth') === false) {
		throw new RuntimeException('Unexpected query: ' . $sql);
	}
	$GLOBALS['admin_queries']++;
	return $GLOBALS['admin'];
}
function check($condition, $label) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($label); }
}
function reset_case() {
	$GLOBALS['config'] = array(
		'is_web' => false, 'poller_id' => 7,
		'base_path' => $GLOBALS['root'], 'include_path' => $GLOBALS['root'] . '/include',
		'config_options_array' => array(
			'notify_admin' => 'on', 'admin_user' => 1,
			'settings_from_email' => 'cacti@example.test', 'settings_from_name' => 'Cacti',
			'settings_how' => 0, 'settings_smtp_timeout' => 5, 'settings_wordwrap' => 76,
			'selective_debug' => '', 'client_timezone_support' => '',
			'log_destination' => 0, 'path_cactilog' => '',
		),
	);
	$GLOBALS['admin'] = array('full_name' => 'Admin', 'email_address' => 'admin@example.test');
	$GLOBALS['hooks'] = array();
	$GLOBALS['admin_queries'] = 0;
	$GLOBALS['handler'] = static function ($alert) { return $alert; };
	file_put_contents($GLOBALS['mailbox'], '');
}
function email_count() {
	return substr_count(file_get_contents($GLOBALS['mailbox']), 'CACTI-END-MESSAGE');
}
function api_plugin_hook_function($name, $alert) {
	$GLOBALS['hooks'][] = array($name, $alert);
	return ($GLOBALS['handler'])($alert);
}

try {
	reset_case();
	check(admin_email('subject', 'message') === null, 'return remains void');
	check(email_count() === 1, 'unhandled hook uses real email fallback');
	foreach (array('accepted', 'already_accepted', 'unhandled', 'failed', null, true, 1, new stdClass(), array(), array('status' => true), array('status' => 1), array('status' => 'ACCEPTED')) as $result) {
		reset_case();
		$GLOBALS['handler'] = static function ($alert) use ($result) { return is_string($result) ? array('status' => $result) : $result; };
		admin_email('subject', 'message');
		$accepted = in_array($result, array('accepted', 'already_accepted'), true);
		check(count($GLOBALS['hooks']) === 1, 'generic hook dispatches once');
		check(email_count() === ($accepted ? 0 : 1), 'strict acceptance controls email fallback');
	}
	reset_case();
	$GLOBALS['config']['config_options_array']['notify_admin'] = '';
	admin_email('subject', 'message');
	check(!$GLOBALS['hooks'] && email_count() === 0 && $GLOBALS['admin_queries'] === 0, 'master switch disables both delivery paths');
	foreach (array('exception', 'error', 'recursion') as $failure) {
		reset_case();
		$GLOBALS['handler'] = static function ($alert) use ($failure) {
			if ($failure === 'exception') { throw new Exception('hook failure'); }
			if ($failure === 'error') { throw new Error('hook error'); }
			admin_email('nested', 'nested body');
			return array('status' => 'accepted');
		};
		admin_email('subject', 'message');
		check(email_count() === 1 && count($GLOBALS['hooks']) === 1, 'exception or recursion falls back without redispatch');
		$GLOBALS['handler'] = static function ($alert) { return array('status' => 'accepted'); };
		admin_email('again', 'body');
		check(count($GLOBALS['hooks']) === 2 && email_count() === 1, 'guard is released for subsequent alerts');
	}
	foreach (array(0, 1) as $adminId) {
		reset_case();
		$GLOBALS['config']['config_options_array']['admin_user'] = $adminId;
		$GLOBALS['admin']['email_address'] = '';
		$GLOBALS['handler'] = static function ($alert) { return array('status' => 'accepted'); };
		admin_email('subject', 'message');
		check(count($GLOBALS['hooks']) === 1 && $GLOBALS['admin_queries'] === 0 && email_count() === 0, 'plugin acceptance needs no email recipient');
		$GLOBALS['handler'] = static function ($alert) { return $alert; };
		admin_email('subject', 'message');
		check(email_count() === 0, 'fallback respects recipient requirements');
	}
	reset_case();
	$GLOBALS['admin'] = array();
	admin_email('subject', 'message');
	check(email_count() === 0, 'invalid admin does not receive email');
	reset_case();
	admin_email('original subject', "body\nunaltered");
	list($name, $alert) = $GLOBALS['hooks'][0];
	check($name === 'admin_alert' && $alert['subject'] === 'original subject' && $alert['message'] === "body\nunaltered" && $alert['collector_id'] === 7, 'original payload and local collector');
	$mail = file_get_contents($mailbox);
	check(strpos($mail, 'Subject: original subject') !== false && strpos($mail, base64_encode("body\r\nunaltered")) !== false, 'real fallback preserves subject and body');
	check(strpos($mail, 'admin@example.test') !== false, 'real fallback uses the configured administrator');
	check($alert['contract_version'] === 1 && $alert['status'] === 'unhandled', 'versioned envelope starts unhandled');
	check(preg_match('/^[a-f0-9]{32}$/D', $alert['event_id']) === 1 && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $alert['occurred_at']) === 1, 'event identity and UTC timestamp');
	admin_email('second', 'body');
	check($GLOBALS['hooks'][1][1]['event_id'] !== $alert['event_id'], 'unique notification identity');
	foreach (array(array(str_repeat('s', 513), 'body', 7), array('subject', str_repeat('m', 16385), 7), array('subject', 'body', 0)) as $invalid) {
		reset_case();
		$GLOBALS['config']['poller_id'] = $invalid[2];
		admin_email($invalid[0], $invalid[1]);
		check(!$GLOBALS['hooks'] && email_count() === 1, 'invalid envelope retains email fallback');
	}
	reset_case();
	admin_email(str_repeat('s', 512), str_repeat('m', 16384));
	check(count($GLOBALS['hooks']) === 1, 'byte limits are inclusive');
	echo $checks . " checks passed\n";
} finally {
	unlink($mailbox);
	putenv('CACTI_ADMIN_ALERT_MAILBOX');
}
