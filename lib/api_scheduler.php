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

/**
 * Generates the form configuration array for the API scheduler.
 *
 * @return array The form configuration array.
 */
function api_scheduler_form() : array {
	global $sched_types, $heartbeats;

	return [
		'spacer2' => [
			'method'        => 'spacer',
			'friendly_name' => __('Schedule'),
			'collapsible'   => 'true'
		],
		'sched_type' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Schedule Type'),
			'description'   => __('Define the collection frequency.'),
			'value'         => '|arg1:sched_type|',
			'array'         => $sched_types,
			'default'       => SCHEDULE_MANUAL
		],
		'start_at' => [
			'method'        => 'textbox',
			'friendly_name' => __('Starting Date/Time'),
			'description'   => __('What time will this Network discover item start?'),
			'value'         => '|arg1:start_at|',
			'max_length'    => '30',
			'default'       => date('Y-m-d H:i:s'),
			'size'          => 60
		],
		'recur_every' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Rerun Every'),
			'description'   => __('Rerun discovery for this Network Range every X.'),
			'value'         => '|arg1:recur_every|',
			'default'       => '1',
			'array'         => [
				1 => '1',
				2 => '2',
				3 => '3',
				4 => '4',
				5 => '5',
				6 => '6',
				7 => '7'
			],
		],
		'day_of_week' => [
			'method'        => 'drop_multi',
			'friendly_name' => __('Days of Week'),
			'description'   => __('What Day(s) of the week will this Network Range be discovered.'),
			'array'         => [
				1 => __('Sunday'),
				2 => __('Monday'),
				3 => __('Tuesday'),
				4 => __('Wednesday'),
				5 => __('Thursday'),
				6 => __('Friday'),
				7 => __('Saturday')
			],
			'value' => '|arg1:day_of_week|',
			'class' => 'day_of_week'
		],
		'month' => [
			'method'        => 'drop_multi',
			'friendly_name' => __('Months of Year'),
			'description'   => __('What Months(s) of the Year will this Network Range be discovered.'),
			'array'         => [
				1  => __('January'),
				2  => __('February'),
				3  => __('March'),
				4  => __('April'),
				5  => __('May'),
				6  => __('June'),
				7  => __('July'),
				8  => __('August'),
				9  => __('September'),
				10 => __('October'),
				11 => __('November'),
				12 => __('December')
			],
			'value' => '|arg1:month|',
			'class' => 'month'
		],
		'day_of_month' => [
			'method'        => 'drop_multi',
			'friendly_name' => __('Days of Month'),
			'description'   => __('What Day(s) of the Month will this Network Range be discovered.'),
			'array'         => [1 => '1', 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32 => __('Last')],
			'value'         => '|arg1:day_of_month|',
			'class'         => 'days_of_month'
		],
		'monthly_week' => [
			'method'        => 'drop_multi',
			'friendly_name' => __('Week(s) of Month'),
			'description'   => __('What Week(s) of the Month will this Network Range be discovered.'),
			'array'         => [
				1    => __('First'),
				2    => __('Second'),
				3    => __('Third'),
				'32' => __('Last')
			],
			'value' => '|arg1:monthly_week|',
			'class' => 'monthly_week'
		],
		'monthly_day' => [
			'method'        => 'drop_multi',
			'friendly_name' => __('Day(s) of Week'),
			'description'   => __('What Day(s) of the week will this Network Range be discovered.'),
			'array'         => [
				1 => __('Sunday'),
				2 => __('Monday'),
				3 => __('Tuesday'),
				4 => __('Wednesday'),
				5 => __('Thursday'),
				6 => __('Friday'),
				7 => __('Saturday')
			],
			'value' => '|arg1:monthly_day|',
			'class' => 'monthly_day'
		],
		'run_limit' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Run Limit'),
			'description'   => __('Define the maximum allowed runtime of this scheduled task.'),
			'value'         => '|arg1:run_limit|',
			'array'         => $heartbeats,
			'default'       => '20'
		],
		'next_start' => [
			'method' => 'hidden',
			'value'  => '|arg1:next_start|'
		],
		'orig_sched_type' => [
			'method' => 'hidden',
			'value'  => '|arg1:sched_type|'
		],
		'orig_start_at' => [
			'method' => 'hidden',
			'value'  => '|arg1:start_at|'
		],
	];
}

/**
 * Outputs the JavaScript code for the scheduler functionality.
 *
 * @return void
 */
function api_scheduler_javascript() : void {
	?>
	<script type='text/javascript'>
	$(function() {
		$('#day_of_week').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the days(s) of the week'); ?>',
			header: false,
			height: 54,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 385
		});

		$('#month').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the month(s) of the year'); ?>',
			header: false,
			height: 82,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 380
		});

		$('#day_of_month').multiselect({
			selectedList: 15,
			noneSelectedText: '<?php print __('Select the day(s) of the month'); ?>',
			header: false,
			height: 162,
			groupColumns: true,
			groupColumnsWidth: 50,
			menuWidth: 275
		});

		$('#monthly_week').multiselect({
			selectedList: 4,
			noneSelectedText: '<?php print __('Select the week(s) of the month'); ?>',
			header: false,
			height: 28,
			groupColumns: true,
			groupColumnsWidth: 70,
			menuWidth: 300
		});

		$('#monthly_day').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the day(s) of the week'); ?>',
			header: false,
			height: 54,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 385
		});

		$('#start_at').datetimepicker({
			minuteGrid: 10,
			stepMinute: 5,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			minDateTime: new Date(<?php print date('Y') . ', ' . (date('m') - 1) . ', ' . date('d, H') . ', ' . date('i', intval(ceil(time() / 300)) * 300) . ', 0, 0'; ?>)
		});

		$('#sched_type').change(function() {
			setSchedule();
		});

		setSchedule();
	});

	function setSchedule() {
		var schedType = $('#sched_type').val();

		toggleFields({
			start_at: schedType > 1,
			recur_every: (schedType > 1 && schedType < 4) || schedType == 6,
			day_of_week: schedType == 3,
			month: schedType > 3 && schedType != 6,
			day_of_month: schedType == 4,
			monthly_week: schedType == 5,
			monthly_day: schedType == 5,
		});

		if (schedType == 2) { // Daily
			$('#row_recur_every').find('div:first').each(function() {
				var html = $(this).html();

				if (html.indexOf('X Weeks') >= 0) {
					html = html.replace('<?php print __('every X Weeks'); ?>', '<?php print __('every X Days'); ?>');
					html = html.replace('<?php print __('Rerun Every X Weeks'); ?>', '<?php print __('Rerun Every X Days'); ?>');
				} else if (html.indexOf('X Hours') >= 0) {
					html = html.replace('<?php print __('Rerun Every X Hours'); ?>', '<?php print __('Rerun Every X Days'); ?>');
					html = html.replace('<?php print __('every X Hours'); ?>', '<?php print __('every X Days'); ?>');
				} else if (html.indexOf('X Days') < 0) {
					html = html.replace('<?php print __('Rerun Every'); ?>', '<?php print __('Rerun Every X Days'); ?>');
					html = html.replace('<?php print __('every X'); ?>', '<?php print __('every X Days'); ?>');
				}

				$(this).html(html);
			});
		} else if (schedType == 6) { // Hourly
			$('#row_recur_every').find('div:first').each(function() {
				var html = $(this).html();

				if (html.indexOf('X Weeks') >= 0) {
					html = html.replace('<?php print __('every X Weeks'); ?>', '<?php print __('every X Hours'); ?>');
					html = html.replace('<?php print __('Rerun Every X Weeks'); ?>', '<?php print __('Rerun Every X Hours'); ?>');
				} else if (html.indexOf('X Days') >= 0) {
					html = html.replace('<?php print __('Rerun Every X Days'); ?>', '<?php print __('Rerun Every X Hours'); ?>');
					html = html.replace('<?php print __('every X Days'); ?>', '<?php print __('every X Hours'); ?>');
				} else if (html.indexOf('X Hours') < 0) {
					html = html.replace('<?php print __('Rerun Every'); ?>', '<?php print __('Rerun Every X Hours'); ?>');
					html = html.replace('<?php print __('every X'); ?>', '<?php print __('every X Hours'); ?>');
				}

				$(this).html(html);
			});
		} else if (schedType == 3) { //Weekly
			$('#row_recur_every').find('div:first').each(function() {
				var html = $(this).html();

				if (html.indexOf('X Days') >= 0) {
					html = html.replace('<?php print __('every X Days'); ?>', '<?php print __('every X Weeks'); ?>');
					html = html.replace('<?php print __('Rerun Every X Days'); ?>', '<?php print __('Rerun Every X Weeks'); ?>');
				} else if (html.indexOf('X Hours') >= 0) {
					html = html.replace('<?php print __('Rerun Every X Hours'); ?>', '<?php print __('Rerun Every X Weeks'); ?>');
					html = html.replace('<?php print __('every X Hours'); ?>', '<?php print __('every X Weeks'); ?>');
				} else if (html.indexOf('X Weeks') < 0) {
					html = html.replace('<?php print __('Rerun Every'); ?>', '<?php print __('Rerun Every X Weeks'); ?>');
					html = html.replace('<?php print __('every X'); ?>', '<?php print __('every X Weeks'); ?>');
				}

				$(this).html(html);
			});
		}
	}
	</script>
	<?php
}

/**
 * Augments the save array with scheduler settings and validates the input data.
 *
 * @param array $save The array to be augmented with scheduler settings.
 * @param array $post The array containing the input data to be validated and saved.
 *
 * @return array The augmented save array with validated scheduler settings.
 */
function api_scheduler_augment_save(array $save, array $post) : array {
	// scheduler settings
	$save['sched_type']    = form_input_validate($post['sched_type'], 'sched_type', '^[0-9]+$', false, 3);
	$save['start_at']      = form_input_validate($post['start_at'], 'start_at', '', false, 3);

	// accommodate a schedule start change
	if (isset($post['orig_start_at']) && $post['orig_start_at'] != $post['start_at']) {
		$save['next_start'] = '0000-00-00';
	}

	if (isset($post['orig_sched_type']) && $post['orig_sched_type'] != $post['sched_type']) {
		$save['next_start'] = '0000-00-00';
	}

	$save['recur_every'] = form_input_validate($post['recur_every'], 'recur_every', '', true, 3);
	$save['run_limit']   = form_input_validate($post['run_limit'], 'run_limit', '', true, 3);

	// convert arrays to strings
	$variables = ['day_of_week', 'month', 'day_of_month', 'monthly_week', 'monthly_day'];
	$aposts    = [];

	foreach ($variables as $v) {
		if (isset($post[$v])) {
			$save[$v] = form_input_validate(implode(',', $post[$v]), $v, '', true, 3);
		} else {
			$save[$v] = '';
		}
	}

	// check for bad rules
	if ($save['sched_type'] == SCHEDULE_WEEKLY) {
		if ($save['day_of_week'] == '') {
			$save['enabled'] = '';

			raise_message('sched_err',  __esc('ERROR: You must specify the day of the week.  Disabling Network %s!.', $save['name']), MESSAGE_LEVEL_ERROR);
		}
	} elseif ($save['sched_type'] == SCHEDULE_MONTHLY) {
		if ($save['month'] == '' || $save['day_of_month'] == '') {
			$save['enabled'] = '';

			raise_message('sched_err',  __esc('ERROR: You must specify both the Months and Days of Month.  Disabling Network %s!', $save['name']), MESSAGE_LEVEL_ERROR);
		}
	} elseif ($save['sched_type'] == SCHEDULE_MONTHLY_ON_DAY) {
		if ($save['month'] == '' || $save['monthly_day'] == '' || $save['monthly_week'] == '') {
			$save['enabled'] = '';

			raise_message('sched_err', __esc('ERROR: You must specify the Months, Weeks of Months, and Days of Week.  Disabling Network %s!', $save['name']), MESSAGE_LEVEL_ERROR);
		}
	}

	$now_time   = time();

	if (isset($save['next_start'])) {
		$next_start = strtotime($save['next_start']);
	} else {
		$next_start = 0;
	}

	$start_at   = strtotime($save['start_at']);
	$poller_int = read_config_option('poller_interval');

	/**
	 * The next_start is really when the report will be checked if it's time to
	 * start not the time the report will actually be run.  So, the numbers can
	 * be a little loose up front.
	 *
	 * The schedulers check will actually adjust the actual next start
	 * when it performs the first check.
	 */

	if ($save['sched_type'] != 1) {
		if ($next_start === '0000-00-00 00:00:00') {
			$save['next_start'] = date('Y-m-d H:i:s', $start_at);
		}

		if ($start_at + $poller_int < $now_time) {
			// adjust to todays date and check if it's in the past
			$timestamp = strtotime('12:00am') + date('H', $start_at) * 3600 + date('i', $start_at) * 60 + date('s', $start_at);

			if ($timestamp < $now_time + $poller_int) {
				// if the time is in the past, adjust forward by one day
				$timestamp += 86400;
			}

			$save['next_start'] = date('Y-m-d H:i:s', $timestamp);
		} else {
			// the time is in the future, we are safe to store it
			$save['next_start'] = date('Y-m-d H:i:s', $start_at);
		}
	}

	return $save;
}

/**
 * Determines if it is time to start a scheduled task based on the provided schedule.
 *
 * @param array  $schedule The schedule details
 * @param string $table    The name of the table to update the next start time in. Default is 'automation_networks'.
 *
 * @return bool Returns true if it is time to start the scheduled task, false otherwise.
 */
function api_scheduler_is_time_to_start(array $schedule, string $table = 'automation_networks') : bool {
	$now   = time();

	if (empty($schedule['next_start'])) {
		$schedule['next_start'] = date('Y-m-d H:i:s', strtotime($schedule['start_at']) + 86400);
	}

	if (is_null($schedule['start_at'])) {
		$schedule['start_at'] = date('Y-m-d H:i:s');
	}

	switch($schedule['sched_type']) {
		case SCHEDULE_MANUAL:
			return false;
		case SCHEDULE_HOURLY:
		case SCHEDULE_DAILY:
			if ($schedule['sched_type'] == SCHEDULE_HOURLY) {
				$recur = $schedule['recur_every'] * 3600; // days
			} else {
				$recur = $schedule['recur_every'] * 86400; // days
			}

			$start = strtotime($schedule['start_at']);
			$next  = strtotime($schedule['next_start']);

			if ($schedule['next_start'] == '0000-00-00 00:00:00') {
				$target = $start;
			} else {
				$target = $next;
			}

			if ($now > $target) {
				while ($now > $target) {
					$target += $recur;
				}

				db_execute_prepared("UPDATE $table
					SET next_start = ?
					WHERE id = ?",
					[date('Y-m-d H:i', $target), $schedule['id']]);

				return true;
			}

			return false;
		case SCHEDULE_WEEKLY:
			$recur = $schedule['recur_every'] * 86400 * 7; // weeks
			$start = strtotime($schedule['start_at']);
			$next  = strtotime($schedule['next_start']);
			$days  = explode(',', $schedule['day_of_week']);
			$day   = 86400;
			$week  = 86400 * 7;

			if ($schedule['next_start'] == '0000-00-00 00:00:00') {
				$target = $start;
			} else {
				$target = $next;
			}

			if ($now > $target) {
				while (true) {
					$target += $day;
					$cur_day = date('w', $target) + 1;

					$key = array_search($cur_day, $days, false);

					if ($key !== false) {
						if ($key == 0) {
							$target += $recur - $week;
						}

						break;
					}
				}

				db_execute_prepared("UPDATE $table
					SET next_start = ?
					WHERE id = ?",
					[date('Y-m-d H:i', $target), $schedule['id']]);

				return true;
			}

			return false;
		case SCHEDULE_MONTHLY:
		case SCHEDULE_MONTHLY_ON_DAY:
			$next = api_scheduler_calculate_next_start($schedule);

			db_execute_prepared("UPDATE $table
				SET next_start = ?
				WHERE id = ?",
				[date('Y-m-d H:i', $next), $schedule['id']]);

			if ($schedule['next_start'] == '0000-00-00 00:00:00') {
				if ($now > strtotime($schedule['start_at'])) {
					return true;
				} else {
					return false;
				}
			} elseif ($now > strtotime($schedule['next_start'])) {
				return true;
			}

			return false;
	}

	return false;
}

/**
 * Calculate the next start time for a given schedule.
 *
 * @param array $schedule The schedule configuration array.
 *
 * @return mixed - The timestamp of the next start time, or false if no valid next start time is found.
 */
function api_scheduler_calculate_next_start(array $schedule) : mixed {
	$now    = time();
	$dates  = [];

	// Some defaults
	$smonth = 'January';
	$sweek  = 'first';
	$sday   = 'Sunday';

	switch($schedule['sched_type']) {
		case SCHEDULE_MANUAL:
			break;
		case SCHEDULE_MONTHLY:
			$months = explode(',', $schedule['month']);
			$days   = explode(',', $schedule['day_of_month']);

			foreach ($months as $month) {
				foreach ($days as $day) {
					switch($month) {
						case '1':
							$smonth = 'January';

							break;
						case '2':
							$smonth = 'February';

							break;
						case '3':
							$smonth = 'March';

							break;
						case '4':
							$smonth = 'April';

							break;
						case '5':
							$smonth = 'May';

							break;
						case '6':
							$smonth = 'June';

							break;
						case '7':
							$smonth = 'July';

							break;
						case '8':
							$smonth = 'August';

							break;
						case '9':
							$smonth = 'September';

							break;
						case '10':
							$smonth = 'October';

							break;
						case '11':
							$smonth = 'November';

							break;
						case '12':
							$smonth = 'December';

							break;
					}

					if ($day == '32') {
						$dates[] = strtotime('last day of ' . $smonth);
					} else {
						$dates[] = strtotime("$smonth $day");
					}
				}
			}

			break;
		case SCHEDULE_MONTHLY_ON_DAY:
			$months = explode(',', $schedule['month']);
			$weeks  = explode(',', $schedule['monthly_week']);
			$days   = explode(',', $schedule['monthly_day']);
			$now    = time();
			$dates  = [];

			foreach ($months as $month) {
				foreach ($weeks as $week) {
					foreach ($days as $day) {
						switch($month) {
							case '1':
								$smonth = 'January';

								break;
							case '2':
								$smonth = 'February';

								break;
							case '3':
								$smonth = 'March';

								break;
							case '4':
								$smonth = 'April';

								break;
							case '5':
								$smonth = 'May';

								break;
							case '6':
								$smonth = 'June';

								break;
							case '7':
								$smonth = 'July';

								break;
							case '8':
								$smonth = 'August';

								break;
							case '9':
								$smonth = 'September';

								break;
							case '10':
								$smonth = 'October';

								break;
							case '11':
								$smonth = 'November';

								break;
							case '12':
								$smonth = 'December';

								break;
							default:
								$Smonth = 'January';

								break;
						}

						switch($week) {
							case '1':
								$sweek = 'first';

								break;
							case '2':
								$sweek = 'second';

								break;
							case '3':
								$sweek = 'third';

								break;
							case '4':
								$sweek = 'forth';

								break;
							case '32':
								$sweek = 'last';

								break;
							default:
								$sweek = 'first';

								break;
						}

						switch($day) {
							case '1':
								$sday = 'Sunday';

								break;
							case '2':
								$sday = 'Monday';

								break;
							case '3':
								$sday = 'Tuesday';

								break;
							case '4':
								$sday = 'Wednesday';

								break;
							case '5':
								$sday = 'Thursday';

								break;
							case '6':
								$sday = 'Friday';

								break;
							case '7':
								$sday = 'Saturday';

								break;
							default:
								$sday = 'Sunday';

								break;
						}

						$dates[] = strtotime("$sweek $sday of $smonth", strtotime($schedule['start_at']));
					}
				}
			}

			break;
	}

	if ($schedule['sched_type'] !== SCHEDULE_MANUAL) {
		asort($dates);

		$newdates = [];

		foreach ($dates as $date) {
			$ndate = date('Y-m-d', $date) . ' ' . date('H:i:s', strtotime($schedule['start_at']));
			$ntime = strtotime($ndate);

			cacti_log('Start At: ' . $schedule['start_at'] . ', Possible Next Start: ' . $ndate . ' with Timestamp: ' . $ntime, false, 'SCHEDULER', POLLER_VERBOSITY_DEBUG);

			if ($ntime > $now) {
				return $ntime;
			}
		}
	}

	return false;
}
