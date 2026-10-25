<?php
// Standalone isolated regression: php tests/Unit/Core/AdminAlert/admin_alert_test.php
// Evaluate production functions in a namespace to stub email, database and entropy APIs.
namespace CactiAdminAlertTest;

$root = dirname(__DIR__, 4);
$source = file_get_contents($root . '/lib/functions.php');
$start = strpos($source, 'function admin_email(');
// The next function's docblock separates this function from send_mail.
$end = strpos($source, "\n/**", $start);
$function = substr($source, $start, $end - $start);
eval('namespace ' . __NAMESPACE__ . '; use Throwable;' . $function);
$plugins = file_get_contents($root . '/lib/plugins.php');
$start = strpos($plugins, 'function api_plugin_installed(');
$end = strpos($plugins, "\n/**", $start);
eval('namespace ' . __NAMESPACE__ . ';' . substr($plugins, $start, $end - $start));

function function_exists($name) { return \function_exists(__NAMESPACE__ . '\\' . $name); }
function read_config_option($key) { return $GLOBALS['options'][$key] ?? ''; }
function db_fetch_row_prepared($sql, $args) {
	if (strpos($sql, 'plugin_config') !== false) {
		return $GLOBALS['plugin_status'] === null ? array() : array('directory' => 'alarmmanager', 'status' => $GLOBALS['plugin_status']);
	}
	$GLOBALS['admin_queries']++;
	return $GLOBALS['admin'];
}
function cacti_sizeof($value) { return count($value); }
function cacti_log(...$args) { $GLOBALS['logs'][] = $args; }
function send_mail(...$args) { $GLOBALS['emails'][] = $args; }
function random_bytes($length) {
	if ($GLOBALS['identity_failure']) { throw new \Exception('entropy unavailable'); }
	return \random_bytes($length);
}
function check($condition, $label) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new \RuntimeException($label); }
}
function reset_case() {
	$GLOBALS['options'] = array('notify_admin' => 'on', 'admin_alert_delivery' => 'alarmmanager', 'admin_user' => 1,
		'settings_from_email' => 'cacti@example.test', 'settings_from_name' => 'Cacti');
	$GLOBALS['config'] = array('poller_id' => 7);
	$GLOBALS['plugin_status'] = 1;
	$GLOBALS['admin'] = array('full_name' => 'Admin', 'email_address' => 'admin@example.test');
	$GLOBALS['emails'] = $GLOBALS['hooks'] = $GLOBALS['logs'] = array();
	$GLOBALS['admin_queries'] = 0;
	$GLOBALS['identity_failure'] = false;
	$GLOBALS['handler'] = static function ($alert) { return $alert; };
}
$GLOBALS['checks'] = 0;
reset_case();
check(admin_email('subject', 'message') === null, 'return remains void');
check(count($GLOBALS['emails']) === 1, 'unavailable hook falls back');
// Define the hook only after testing function unavailability.
eval('namespace CactiAdminAlertTest; function api_plugin_hook_function($name, $alert) {
	$GLOBALS["hooks"][] = array($name, $alert);
	return ($GLOBALS["handler"])($alert);
}');
foreach (array('accepted', 'already_accepted', 'unhandled', 'failed', null, true, 1, new \stdClass(), array(), array('status' => true), array('status' => 1), array('status' => 'ACCEPTED')) as $result) {
	reset_case();
	$GLOBALS['handler'] = static function ($alert) use ($result) { return is_string($result) ? array('status' => $result) : $result; };
	admin_email('subject', 'message');
	$accepted = in_array($result, array('accepted', 'already_accepted'), true);
	check(count($GLOBALS['hooks']) === 1, 'dispatch once');
	check(count($GLOBALS['emails']) === ($accepted ? 0 : 1), 'strict result handling');
}
foreach (array('email', 'alarmmanager', '', 'invalid') as $mode) {
	foreach (array('', 'on') as $enabled) {
		reset_case();
		$GLOBALS['options']['notify_admin'] = $enabled;
		$GLOBALS['options']['admin_alert_delivery'] = $mode;
		admin_email('subject', 'message');
		check(count($GLOBALS['hooks']) === ($enabled === 'on' && $mode === 'alarmmanager' ? 1 : 0), 'mode/master switch');
		check(count($GLOBALS['emails']) === ($enabled === 'on' ? 1 : 0), 'master switch email');
	}
}
reset_case();
unset($GLOBALS['options']['admin_alert_delivery']);
admin_email('subject', 'message');
check(count($GLOBALS['hooks']) === 0 && count($GLOBALS['emails']) === 1, 'missing setting uses email');
foreach (array(null, 0, 1, 2) as $status) {
	reset_case();
	$GLOBALS['plugin_status'] = $status;
	admin_email('subject', 'message');
	check(count($GLOBALS['hooks']) === ($status !== null && $status >= 1 ? 1 : 0), 'native installed metadata');
	check(count($GLOBALS['emails']) === 1, 'absent or disabled handler fallback');
	$settings = file_get_contents($root . '/include/global_settings.php');
	$start = strpos($settings, "\t\t'admin_alert_delivery' => array(");
	$end = strpos($settings, "\t\t'settings_test_email'", $start);
	eval('namespace ' . __NAMESPACE__ . '; $definition = array(' . substr($settings, $start, $end - $start) . ');');
	$options = $definition['admin_alert_delivery']['array'];
	check(isset($options['alarmmanager']) === ($status !== null && $status >= 1), 'selector reflects installation');
	check($definition['admin_alert_delivery']['default'] === 'email', 'default email');
}
foreach (array('exception', 'error', 'identity', 'recursion') as $failure) {
	reset_case();
	$GLOBALS['identity_failure'] = $failure === 'identity';
	$GLOBALS['handler'] = static function ($alert) use ($failure) {
		if ($failure === 'exception') { throw new \Exception('hook failure'); }
		if ($failure === 'error') { throw new \Error('hook error'); }
		if ($failure === 'recursion') { admin_email('nested', 'nested body'); }
		return array('status' => 'accepted');
	};
	admin_email('subject', 'message');
	check(count($GLOBALS['emails']) === 1, 'failure fallback or nested email');
	check(count($GLOBALS['hooks']) === ($failure === 'identity' ? 0 : 1), 'no recursive dispatch');
	$GLOBALS['identity_failure'] = false;
	$GLOBALS['handler'] = static function ($alert) { return array('status' => 'accepted'); };
	admin_email('again', 'body');
	check(count($GLOBALS['hooks']) === ($failure === 'identity' ? 1 : 2), 'finally releases guard');
}
foreach (array(0, 1) as $adminId) {
	reset_case();
	$GLOBALS['options']['admin_user'] = $adminId;
	$GLOBALS['admin']['email_address'] = '';
	$GLOBALS['handler'] = static function ($alert) { return array('status' => 'accepted'); };
	admin_email('subject', 'message');
	check(count($GLOBALS['hooks']) === 1 && $GLOBALS['admin_queries'] === 0 && !$GLOBALS['emails'], 'accept without email configuration');
	$GLOBALS['handler'] = static function ($alert) { return $alert; };
	admin_email('subject', 'message');
	check(!$GLOBALS['emails'], 'fallback respects recipient requirements');
}
reset_case();
$GLOBALS['admin'] = array();
admin_email('subject', 'message');
check(!$GLOBALS['emails'], 'invalid admin fallback');
reset_case();
admin_email("subject é", "body\nunaltered");
list($name, $alert) = $GLOBALS['hooks'][0];
check($name === 'admin_alert' && $alert['subject'] === "subject é" && $alert['message'] === "body\nunaltered" && $alert['collector_id'] === 7, 'unchanged payload and local collector');
check($GLOBALS['emails'][0][2] === $alert['subject'] && $GLOBALS['emails'][0][3] === $alert['message'], 'fallback email payload unchanged');
check($alert['contract_version'] === 1 && $alert['status'] === 'unhandled', 'envelope contract');
check(preg_match('/^[a-f0-9]{32}$/D', $alert['event_id']) === 1 && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $alert['occurred_at']) === 1, 'identity and UTC timestamp');
admin_email('second', 'body');
check($GLOBALS['hooks'][1][1]['event_id'] !== $alert['event_id'], 'unique notification identity');
foreach (array(array(str_repeat('s', 513), 'body', 7), array('subject', str_repeat('m', 16385), 7), array('subject', 'body', 0)) as $invalid) {
	reset_case();
	$GLOBALS['config']['poller_id'] = $invalid[2];
	admin_email($invalid[0], $invalid[1]);
	check(!$GLOBALS['hooks'] && count($GLOBALS['emails']) === 1, 'invalid envelope falls back without truncation');
}
reset_case();
admin_email(str_repeat('s', 512), str_repeat('m', 16384));
check(count($GLOBALS['hooks']) === 1, 'byte limits inclusive');
echo $GLOBALS['checks'] . " checks passed\n";
function __($text) { return $text; }
