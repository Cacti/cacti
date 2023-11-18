#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

require(__DIR__ . '/../include/cli_check.php');

ini_set('max_execution_time', '0');

if ($config['poller_id'] > 1) {
	print "FATAL: This utility is designed for the main Data Collector only" . PHP_EOL;
	exit(1);
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug            = false;
$force            = false;
$data_template_id = false;
$prev_heartbeat   = false;
$new_heartbeat    = false;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--new-heartbeat':
				$new_heartbeat = $value;
				break;
			case '--prev-heartbeat':
				$prev_heartbeat = $value;
				break;
			case '--data-template-id':
				$data_template_id = $value;
				break;
			case '--list-data-templates':
				$data_templates = db_fetch_assoc('SELECT id, name
					FROM data_template
					ORDER BY id');

				if (cacti_sizeof($data_templates)) {
					print 'ID       Data Template Name' . PHP_EOL;
					print '------   ----------------------' . PHP_EOL;

					foreach($data_templates as $dt) {
						printf('%-6s   %-25s' . PHP_EOL, $dt['id'], $dt['name']);
					}

					print PHP_EOL;
				} else {
					print 'WARNING: No Data Templates found!' . PHP_EOL;
				}

				exit(0);

				break;
			case '--list-heartbeats':
				if (cacti_sizeof($heartbeats)) {
					print 'Heartbeat   Heartbeat Name' . PHP_EOL;
					print '---------   --------------' . PHP_EOL;

					foreach($heartbeats as $index => $hb) {
						printf('%-12s%-17s' . PHP_EOL, $index, $hb);
					}

					print PHP_EOL;
				} else {
					print 'WARNING: No Heartbeats found!' . PHP_EOL;
				}

				exit(0);

				break;
			case '--list-profiles':
				$dspheartbeats = db_fetch_assoc('SELECT name, heartbeat
					FROM data_source_profiles
					ORDER BY heartbeat');

				if (cacti_sizeof($dspheartbeats)) {
					print 'Heartbeat   Data Source Profile' . PHP_EOL;
					print '---------   ----------------------' . PHP_EOL;

					foreach($dspheartbeats as $hb) {
						printf('%-12s%-25s' . PHP_EOL, $hb['heartbeat'], $hb['name']);
					}

					print PHP_EOL;
				} else {
					print 'WARNING: No Data Source Profiles found!' . PHP_EOL;
				}

				exit(0);

				break;
			case '--debug':
				$debug = true;
				break;
			case '--force':
				$force = true;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit(0);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
} else {
	print 'ERROR: You must supply input parameters' . PHP_EOL . PHP_EOL;
	display_help();
	exit(1);
}

$poller_interval = read_config_option('poller_interval');

if ($poller_interval * 2.0 > $new_heartbeat) {
	printf('ERROR: Your new heartbeat must be greater or equal than %.0f' . PHP_EOL, $poller_interval * 2);
	exit(1);
}

$sql_where  = '';
$sql_params = array();

if ($data_template_id !== false && $data_template_id > 0) {
	$sql_params[] = $data_template_id;
	$sql_where = ' AND dtd.data_template_id = ?';
} elseif ($data_template_id !== false && ($data_template_id <= 0 || !is_numeric($data_template_id))) {
	printf('ERROR: Your Data Template ID \'%s\' is invalid' . PHP_EOL, $data_template_id);
	exit(1);
}

if ($new_heartbeat !== false && (!is_numeric($new_heartbeat) || $new_heartbeat < -1)) {
	printf('ERROR: Your New Heartbeat \'%s\' must be greater or equal to zero.' . PHP_EOL, $new_heartbeat);
	exit(1);
}

if (!array_key_exists($new_heartbeat, $heartbeats)) {
	printf('ERROR: Your New Heartbeat \'%s\' is not a supported Heartbeat.  Use --list-heartbeats to see the list.' . PHP_EOL, $new_heartbeat);
	exit(1);
}

if ($prev_heartbeat !== false && (!is_numeric($prev_heartbeat) || $prev_heartbeat < -1)) {
	printf('ERROR: Your Previous Heartbeat \'%s\' must be greater or equal to zero' . PHP_EOL, $prev_heartbeat);
} elseif ($prev_heartbeat !== false) {
	$sql_where .= ' AND dtr.rrd_heartbeat = ?';
	$sql_params[] = $prev_heartbeat;
}

$sql_params1 = array_merge(array($config['rra_path']), $sql_params);

$rrdfiles = db_fetch_assoc_prepared("SELECT dtr.local_data_id, dtd.name_cache, dt.name,
	REPLACE(dtd.data_source_path, '<path_rra>', ?) AS rrd,
	dtr.rrd_heartbeat, GROUP_CONCAT(DISTINCT dtr.data_source_name) AS data_sources
	FROM data_template_data AS dtd
	INNER JOIN data_template AS dt
	ON dtd.data_template_id = dt.id
	INNER JOIN data_template_rrd AS dtr
	ON dtd.local_data_id = dtr.local_data_id
	WHERE dtd.local_data_id > 0
	$sql_where
	GROUP BY dtd.local_data_id",
	$sql_params1);

$total_heartbeats = array_rekey($rrdfiles, 'rrd_heartbeat', 'rrd_heartbeat');

$profile_ids = db_fetch_assoc_prepared("SELECT DISTINCT dsp.id, dsp.heartbeat, dsp.name
	FROM data_template_data AS dtd
	INNER JOIN data_template_rrd AS dtr
	ON dtd.local_data_id = dtr.local_data_id
	INNER JOIN data_source_profiles AS dsp
	ON dsp.id = dtd.data_source_profile_id
	WHERE dtd.local_data_id > 0
	$sql_where",
	$sql_params);

if (cacti_sizeof($profile_ids) > 1) {
	printf('There are %s Data Source Proiles Impacted by this change.' . PHP_EOL, cacti_sizeof($profile_ids));
} elseif (cacti_sizeof($profile_ids) == 1) {
	printf('There is %s Data Source Proile Impacted by this change.' . PHP_EOL, cacti_sizeof($profile_ids));
} else {
	printf('ERROR: Not RRDfiles found that match your --prev-heartbeat or --data-template-id setting' . PHP_EOL);
	exit(1);
}

printf('Current Data Source Profile Heartbeats found are: %s' . PHP_EOL, implode(', ', $total_heartbeats));
printf('There will be %s RRDfiles updated as a result of this command.' . PHP_EOL, cacti_sizeof($rrdfiles));

if (!$force) {
	$exit = false;

	foreach($profile_ids as $pid) {
		if ($pid['heartbeat'] != $new_heartbeat) {
			printf('ERROR: Data Source Profile \'%s\' has a heartbeat of %s which does' . PHP_EOL, $pid['name'], $pid['heartbeat']);
			printf('       not match the new heartbeat of %s.  Use the --force option to update' . PHP_EOL, $new_heartbeat);
			printf('       the Data Source Profile\'s heartbeat as well as the Data Sources.' . PHP_EOL . PHP_EOL);
			$exit = true;
		}
	}

	if ($exit) {
		exit(1);
	}
} else {
	printf('This is a forced run, impacted Data Source Profiles will have their Heartbeats updated as well' . PHP_EOL);
}

$i = 0;
if (cacti_sizeof($rrdfiles)) {
	foreach($rrdfiles as $f) {
		if (file_exists($f['rrd'])) {
			$command = sprintf("rrdtool tune %s ", $f['rrd']);

			$data_sources = explode(',', $f['data_sources']);

			foreach($data_sources as $ds) {
				$command .= " --heartbeat $ds:$new_heartbeat";
			}

			$output      = array();
			$return_code = 0;

			if (1 == 2) {
				printf("Updating Heartbeat for Data Source:%s, Data Template:%s, RRD:%s from 600 to 900" . PHP_EOL, $f['name_cache'], $f['name'], $f['rrd']);
				printf("The RRDtool command is '$command'" . PHP_EOL);
			}

			$result = exec($command, $output, $return_code);

			if ($return_code != 0) {
				printf("Warning Error Occurred: " . implode(', ', $output) . PHP_EOL);
			} else {
				db_execute_prepared('UPDATE data_template_rrd
					SET rrd_heartbeat = ?
					WHERE local_data_id = ?',
					array($new_heartbeat, $f['local_data_id']));
			}
		} else {
			printf('WARNING: RRDfile \'%s\' does not exist!' . PHP_EOL);
		}

		$i++;

		if ($i % 100 == 0) {
			printf("Processed %s RRDfiles" . PHP_EOL, $i);
		}
	}

	printf("Processed a Total of %s RRDfiles" . PHP_EOL, $i);

	if ($data_template_id > 0) {
		db_execute_prepared('UPDATE data_template_rrd
			SET rrd_heartbeat = ?
			WHERE local_data_id = 0
			AND data_template_id = ?',
			array($new_heartbeat, $data_template_id));
	}

	if ($force) {
		foreach($profile_ids as $pid) {
			db_execute_prepared('UPDATE data_source_profiles
				SET heartbeat = ?
				WHERE id = ?',
				array($new_heartbeat, $pid['id']));
		}

		if ($data_template_id > 0) {
			db_execute_prepared('UPDATE data_template_rrd
				SET rrd_heartbeat = ?
				WHERE local_data_id = 0
				AND data_template_id = ?',
				array($new_heartbeat, $data_template_id));
		} else {
			db_execute_prepared('UPDATE data_template_rrd
				SET rrd_heartbeat = ?
				WHERE local_data_id = 0',
				array($new_heartbeat));
		}
	}
}

/**
 * display_version - displays version information
 *
 * @return (void)
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Update RRDfile Heartbeat Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - displays the usage of the function
 *
 * @return (void)
 */
function display_help () {
	display_version();

	print "\nusage: update_heartbeat.php --new-heartbeat=N [--data-template-id=id] [--prev-heartbeat=N] [--force] [--debug|-d]\n\n";
	print "A utility to update RRDfile heartbeats and the Cacti database to match.\n\n";
	print "Required:\n";
	print "    --new-heartbeat=N     - A Heartbeat in seconds.  It must align with available Heartbeats in Cacti\n";
	print "                            Currently Heartbeats include from 20-172800 seconds.  The value must also\n";
	print "                            be at least two times the current Cacti poller interval.\n";
	print "Optional:\n";
	print "    --data-template-id=N  - Only update Cacti Data Source Heartbeats that are associated with a Data Template id.\n";
	print "    --prev-heartbeat=N    - Only update Cacti Data Sources that currently have the Heartbeat specified.\n";
	print "    --force               - If the hearbeat selected does not match the Data Source Profile, update the\n";
	print "                            Data Source Profile to match the command.  Otherwise, the script will exit.\n";
	print "    --debug               - Display verbose output during execution\n\n";
	print "List Options:\n";
	print "    --list-data-templates - List all Data Templates\n";
	print "    --list-heartbeats     - List all supported Heartbeats\n";
	print "    --list-profiles       - List all Data Source Profiles and their Heartbeats\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print "DEBUG: " . trim($message) . "\n";
	}
}

