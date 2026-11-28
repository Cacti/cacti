<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * api_scheduler_augment_save() turns the posted schedule form into the row
 * the automation network and report editors save. A weekly or monthly
 * schedule without the days it needs can never run, so the save disables it
 * and tells the operator which fields are missing instead of storing a
 * schedule that silently never fires.
 */

require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
require_once dirname(__DIR__, 3) . '/lib/database.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/lib/api_scheduler.php';

/**
 * Builds the posted form for a schedule starting tomorrow at 09:30.
 *
 * @param array $overrides Form keys to replace in the default.
 *
 * @return array The $_POST shape api_scheduler_augment_save() reads.
 */
function scheduler_augment_post(array $overrides = []) : array {
	return array_merge([
		'sched_type'  => (string) SCHEDULE_WEEKLY,
		'start_at'    => date('Y-m-d', time() + 86400) . ' 09:30',
		'recur_every' => '1',
		'run_limit'   => '',
	], $overrides);
}

beforeEach(function () {
	global $config, $no_http_headers;

	$this->scheduler_prior = [
		'session'         => $_SESSION ?? null,
		'options_cli'     => $config[OPTIONS_CLI] ?? null,
		'no_http_headers' => $no_http_headers ?? null,
	];

	// raise_message() must not try to start a real session from the CLI
	$no_http_headers = true;
	$_SESSION        = [OPTIONS_WEB => ['poller_interval' => 300, 'log_validation' => '']];

	$config[OPTIONS_CLI] = array_merge($config[OPTIONS_CLI] ?? [], ['poller_interval' => 300, 'log_validation' => '']);
});

afterEach(function () {
	global $config, $no_http_headers;

	$prior = $this->scheduler_prior;

	if ($prior['session'] === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $prior['session'];
	}

	if ($prior['options_cli'] === null) {
		unset($config[OPTIONS_CLI]);
	} else {
		$config[OPTIONS_CLI] = $prior['options_cli'];
	}

	if ($prior['no_http_headers'] === null) {
		unset($GLOBALS['no_http_headers']);
	} else {
		$no_http_headers = $prior['no_http_headers'];
	}
});

test('a complete weekly schedule stays enabled and joins the chosen days', function () {
	$save = api_scheduler_augment_save(['name' => 'Lab', 'enabled' => 'on'], scheduler_augment_post([
		'day_of_week' => ['2', '4'],
	]));

	expect($save['enabled'])->toBe('on')
		->and($save['day_of_week'])->toBe('2,4')
		->and($save['month'])->toBe('')
		->and($save['next_start'])->toBe(date('Y-m-d', time() + 86400) . ' 09:30:00')
		->and($_SESSION[SESS_MESSAGES] ?? [])->not->toHaveKey('sched_err');
});

test('a weekly schedule without a weekday is disabled with an explanation', function () {
	$save = api_scheduler_augment_save(['name' => 'Lab <one>', 'enabled' => 'on'], scheduler_augment_post());

	expect($save['enabled'])->toBe('')
		->and($_SESSION[SESS_MESSAGES]['sched_err']['level'])->toBe(MESSAGE_LEVEL_ERROR)
		->and($_SESSION[SESS_MESSAGES]['sched_err']['message'])->toBe('ERROR: You must specify the day of the week.  Disabling Network Lab &lt;one&gt;!.');
});

test('a monthly schedule needs both months and days of the month', function () {
	$save = api_scheduler_augment_save(['name' => 'Core', 'enabled' => 'on'], scheduler_augment_post([
		'sched_type' => (string) SCHEDULE_MONTHLY,
		'month'      => ['1', '7'],
	]));

	expect($save['enabled'])->toBe('')
		->and($save['month'])->toBe('1,7')
		->and($_SESSION[SESS_MESSAGES]['sched_err']['message'])->toBe('ERROR: You must specify both the Months and Days of Month.  Disabling Network Core!');
});

test('a monthly schedule on a weekday needs months, weeks and weekdays', function () {
	$save = api_scheduler_augment_save(['name' => 'Edge', 'enabled' => 'on'], scheduler_augment_post([
		'sched_type'   => (string) SCHEDULE_MONTHLY_ON_DAY,
		'month'        => ['3'],
		'monthly_week' => ['2'],
	]));

	expect($save['enabled'])->toBe('')
		->and($save['monthly_week'])->toBe('2')
		->and($save['monthly_day'])->toBe('')
		->and($_SESSION[SESS_MESSAGES]['sched_err']['message'])->toBe('ERROR: You must specify the Months, Weeks of Months, and Days of Week.  Disabling Network Edge!');
});

test('the start picker never offers a time before the next five minute boundary', function () {
	ob_start();
	api_scheduler_javascript();
	$script = ob_get_clean();

	$boundary = intval(ceil(time() / 300)) * 300;

	expect($script)->toContain("$('#start_at').datetimepicker({")
		->and($script)->toContain('minDateTime: new Date(' . date('Y') . ', ' . (date('m') - 1) . ', ' . date('d, H') . ', ' . date('i', $boundary) . ', 0, 0)');
});
