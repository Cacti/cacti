<?php
/* Native audit-log fixture: CLI or an explicitly enabled loopback test server. */
if (PHP_SAPI !== 'cli' && !(PHP_SAPI === 'cli-server' &&
	getenv('CACTI_USER_AUDIT_PROBE') === '1' &&
	($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1')) {
	http_response_code(404);
	exit;
}

// PHP's built-in router does not apply auto_prepend_file to the router itself.
// Use the same optional native request recorder when the coverage image runs it.
if (PHP_SAPI === 'cli-server' && extension_loaded('pcov')) {
	$coverage = ini_get('auto_prepend_file');
	if ($coverage !== '') {
		require_once $coverage;
	}
}

chdir(dirname(__DIR__, 3));
$_SERVER['REQUEST_URI'] = '/user_admin.php';
$_SERVER['PHP_SELF'] = '/user_admin.php';
$_SERVER['SERVER_NAME'] = 'localhost';
require './include/global.php';

function probeUserActionLog(string $action, string $actor): void {
	$before = file_get_contents(cacti_log_file());
	log_user_action('e2e-log-probe', $action);
	$log = file_get_contents(cacti_log_file());
	$message = "User 'e2e-log-probe' was $action by user '$actor'";
	if ($before === false || $log === false || strpos(substr($log, strlen($before)), $message) === false) {
		throw new RuntimeException('Expected native audit log record is missing.');
	}
}

if (!is_file(cacti_log_file())) {
	cacti_log('NOTE: Native audit fixture started', false, 'AUTHC');
}

if (PHP_SAPI === 'cli-server') {
	unset($_SESSION['sess_user_id']);
	probeUserActionLog('web probe', 'unknown user');
	print "PASS: anonymous web audit actor\n";
	exit;
}

$before = file_get_contents(cacti_log_file());
if (log_user_action('', 'empty-user probe') !== false ||
	log_user_action('e2e-log-probe', '') !== false ||
	file_get_contents(cacti_log_file()) !== $before) {
	throw new RuntimeException('Empty audit inputs must not append a record.');
}

$_SESSION['sess_user_id'] = 1;
probeUserActionLog('admin probe', 'admin');
$_SESSION['sess_user_id'] = 2147483647;
probeUserActionLog('missing actor probe', 'user ID 2147483647');

unset($_SESSION['sess_user_id']);
$actor = get_execution_user();
probeUserActionLog('cli probe', $actor . ' (CLI)');

// Exercise the real whoami fallback when the execution environment cannot
// resolve it. The runner disables POSIX lookup for this isolated process.
$path = getenv('PATH');
putenv('PATH=');
try {
	probeUserActionLog('unknown cli probe', 'unknown user (CLI)');
} finally {
	$path === false ? putenv('PATH') : putenv('PATH=' . $path);
}
print "PASS: empty, authenticated, missing, and CLI audit actors\n";
