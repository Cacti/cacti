<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Only library lines are gated, and whole-file vendor evidence does not fit
// the runner's memory limit.
if (function_exists('xdebug_set_filter')) {
	xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [dirname(__DIR__, 3) . '/lib/']);
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/BufferedPDOStatement.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once CACTI_PATH_LIBRARY . '/api_scheduler.php';

$count = 0;

/**
 * Counts one assertion and throws when the values are not identical.
 *
 * @param mixed  $actual   The observed value
 * @param mixed  $expected The required value
 * @param string $message  What the assertion checks
 *
 * @return void
 */
function scheduler_expect(mixed $actual, mixed $expected, string $message) : void {
	global $count;
	$count++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}

/**
 * Builds the posted form for a schedule starting tomorrow at 09:30.
 *
 * @param array<string,mixed> $overrides Form keys to replace in the default
 *
 * @return array<string,mixed> The $_POST shape api_scheduler_augment_save() reads
 */
function scheduler_post(array $overrides = []) : array {
	return array_merge([
		'sched_type'  => (string) SCHEDULE_WEEKLY,
		'start_at'    => date('Y-m-d', time() + 86400) . ' 09:30',
		'recur_every' => '1',
		'run_limit'   => '',
	], $overrides);
}

$connection = new FakeMySQLPDO();
$connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BufferedPDOStatement::class, []]);
$GLOBALS['database_hostname'] = 'scheduler-fixture';
$GLOBALS['database_port']     = 0;
$GLOBALS['database_default']  = 'scheduler-fixture';
$GLOBALS['database_sessions'] = ['scheduler-fixture:0:scheduler-fixture' => $connection];
$connection->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

// raise_message() must not try to start a real session from the CLI.
$GLOBALS['no_http_headers'] = true;
$_SESSION                   = [OPTIONS_WEB => ['poller_interval' => 300, 'log_validation' => '']];
$config[OPTIONS_CLI]        = array_merge($config[OPTIONS_CLI] ?? [], ['poller_interval' => 300, 'log_validation' => '']);

// The form offers every day of the month plus the last one.
$form = api_scheduler_form();
scheduler_expect(count($form['day_of_month']['array']), 32, 'The day of month list holds 31 days and Last');
scheduler_expect($form['day_of_month']['array'][1], '1', 'The first day of the month is labelled 1');
scheduler_expect($form['day_of_month']['array'][31], 31, 'The 31st keeps its own number');
scheduler_expect($form['day_of_month']['array'][32], 'Last', 'The final entry selects the last day');
scheduler_expect($form['day_of_month']['class'], 'multiselect days_of_month', 'The day of month list is a multiselect');

// The start picker never offers a time before the next five minute boundary.
ob_start();
api_scheduler_javascript();
$script   = (string) ob_get_clean();
$boundary = intval(ceil(time() / 300)) * 300;
scheduler_expect(str_contains($script, "$('#start_at').datetimepicker({"), true, 'The start picker is initialised');
scheduler_expect(str_contains($script, 'minDateTime: new Date(' . date('Y') . ', ' . (date('m') - 1) . ', ' . date('d, H') . ', ' . date('i', $boundary) . ', 0, 0)'), true, 'The start picker floors at the next boundary');

// A complete weekly schedule stays enabled and joins the chosen days.
$save = api_scheduler_augment_save(['name' => 'Lab', 'enabled' => 'on'], scheduler_post(['day_of_week' => ['2', '4']]));
scheduler_expect($save['enabled'], 'on', 'A complete weekly schedule stays enabled');
scheduler_expect($save['day_of_week'], '2,4', 'The weekdays are joined');
scheduler_expect(isset($_SESSION[SESS_MESSAGES]['sched_err']), false, 'A complete weekly schedule raises no error');

// A weekly schedule without a weekday is disabled with an explanation.
$save = api_scheduler_augment_save(['name' => 'Lab <one>', 'enabled' => 'on'], scheduler_post());
scheduler_expect($save['enabled'], '', 'A weekly schedule without a weekday is disabled');
scheduler_expect($_SESSION[SESS_MESSAGES]['sched_err']['level'], MESSAGE_LEVEL_ERROR, 'The weekly error is an error');
scheduler_expect($_SESSION[SESS_MESSAGES]['sched_err']['message'], 'ERROR: You must specify the day of the week.  Disabling Network Lab &lt;one&gt;!.', 'The weekly error names the network');
unset($_SESSION[SESS_MESSAGES]);

// A monthly schedule needs both months and days of the month.
$save = api_scheduler_augment_save(['name' => 'Core', 'enabled' => 'on'], scheduler_post([
	'sched_type' => (string) SCHEDULE_MONTHLY,
	'month'      => ['1', '7'],
]));
scheduler_expect($save['enabled'], '', 'A monthly schedule without days is disabled');
scheduler_expect($save['month'], '1,7', 'The months are joined');
scheduler_expect($_SESSION[SESS_MESSAGES]['sched_err']['message'], 'ERROR: You must specify both the Months and Days of Month.  Disabling Network Core!', 'The monthly error names the network');
unset($_SESSION[SESS_MESSAGES]);

// A monthly schedule on a weekday needs months, weeks and weekdays.
$save = api_scheduler_augment_save(['name' => 'Edge', 'enabled' => 'on'], scheduler_post([
	'sched_type'   => (string) SCHEDULE_MONTHLY_ON_DAY,
	'month'        => ['3'],
	'monthly_week' => ['2'],
]));
scheduler_expect($save['enabled'], '', 'A monthly weekday schedule without weekdays is disabled');
scheduler_expect($save['monthly_week'], '2', 'The weeks are joined');
scheduler_expect($save['monthly_day'], '', 'The missing weekdays stay empty');
scheduler_expect($_SESSION[SESS_MESSAGES]['sched_err']['message'], 'ERROR: You must specify the Months, Weeks of Months, and Days of Week.  Disabling Network Edge!', 'The monthly weekday error names the network');
unset($_SESSION[SESS_MESSAGES]);

// A fixed year and reference time keep the next start deterministic.
$schedule = ['sched_type' => SCHEDULE_MONTHLY, 'month' => '3,1', 'day_of_month' => '15', 'start_at' => '2020-06-01 09:30'];
$now      = strtotime('2030-02-01 00:00:00');
scheduler_expect(api_scheduler_calculate_next_start_for_year($schedule, 2030, $now), strtotime('2030-03-15 09:30:00'), 'The next start skips a date already past');
scheduler_expect(api_scheduler_calculate_next_start_for_year($schedule, 2029, $now), false, 'A year entirely in the past has no next start');

$schedule = ['sched_type' => SCHEDULE_MONTHLY_ON_DAY, 'month' => '4', 'monthly_week' => '32', 'monthly_day' => '6', 'start_at' => '2020-06-01 18:00'];
scheduler_expect(api_scheduler_calculate_next_start_for_year($schedule, 2030, $now), strtotime('last Friday of April 2030 18:00:00'), 'The last weekday of a month resolves');

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
