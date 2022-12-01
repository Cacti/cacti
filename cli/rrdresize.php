#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* take your time */
ini_set('max_execution_time', 0);

/* change working directory */
chdir(__DIR__ . '/../');

/* get access to the Cacti database */
require_once('./include/cli_check.php');

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

if (sizeof($parms)) {
	$data_template_id     = false;
	$displayDataTemplates = false;
	$debug_mode           = false;
	$dry_run_mode         = false;

	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode("=", $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case "-d":
				$debug_mode = true;
				break;
			case "--debug":
				$debug_mode = true;
				break;
			case "--data-template-id":
				$data_template_id = trim($value);
				if (!is_numeric($data_template_id)) {
					echo "ERROR: You must supply a valid data template id to run this script!" . PHP_EOL;
					exit(1);
				} else {
					$data_template_id__valid = db_fetch_cell('SELECT id FROM data_template WHERE id =' . $data_template_id);
					if ($data_template_id__valid === null) {
						echo "ERROR: You must supply a valid data template id to run this script!" . PHP_EOL;
						exit(1);
					}
				}
				break;
			case "--backup":
				$backup_folder = trim($value);
				if (!$backup_folder) {
					echo "ERROR: You must supply a valid backup folder to run this script!" . PHP_EOL;
					exit(1);
				}
				if (!file_exists($backup_folder)) {
					if (mkdir($backup_folder, 0777, true) === false) {
						echo "ERROR: Cannot create backup folder: \"$backup_folder\"" . PHP_EOL;
						exit(1);
					}
				} else {
					if (is_dir($backup_folder) === false) {
						echo "ERROR: Given backup folder: \"$backup_folder\" is not a directory" . PHP_EOL;
						exit(1);
					} else {
						if (!is_writable($backup_folder)) {
							echo "ERROR: No write permission to backup folder: \"$backup_folder\" given." . PHP_EOL;
							exit(1);
						}
					}
				}
				$tmp_backup_folder = $backup_folder . '/' . date('d-m-Y_G-i-s') . '/';
				break;
			case "--dry-run":
				$dry_run_mode = true;
				break;
			case "--list-data-templates":
				$displayDataTemplates = true;
				break;
			case "--version":
			case "-v":
			case "-V":
				display_version();
				exit(0);
			case "-h":
			case "-H":
			case "--help":
				display_help();
				exit(0);
			default:
				echo "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
} else {
	display_help();
	exit(0);
}

display_version();
echo "THIS SCRIPT IS CURRENTLY RELYING ON TABLES THAT DO NOT EXIST" . PHP_EOL . PHP_EOL;

/* presets */
$logging             = 1;
$total_files         = 0;
$total_files_skipped = 0;
$total_errors        = 0;
$total_outdated      = 0;
$total_mismatches    = 0;
$total_skipped       = 0;

if ($displayDataTemplates) {
	$data_templates = db_fetch_assoc("SELECT id, name
		FROM data_template
		ORDER BY id, name");

	echo "Known Data Templates: (id, name)" . PHP_EOL;
	if (sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			echo $data_template["id"] . "\t" . $data_template["name"] . PHP_EOL;
		}
	} else {
		echo "ERROR: No data templates found." . PHP_EOL;
	}
	echo PHP_EOL;
	exit(0);
}

if (!$data_template_id) {
	echo "ERROR: You must supply a valid data template id to run this script!" . PHP_EOL;
	exit(0);
}

if (!$backup_folder) {
	echo "ERROR: You must define a backup folder!" . PHP_EOL;
	exit(0);
} else {
	$tmp_xml_file = $tmp_backup_folder . 'tmp_rrdresize.xml';
	$tmp_rrd_file = $tmp_backup_folder . 'tmp_rrdfile.rrd';
}

/* check status of Boost */
$boost_enabled = (function_exists("boost_process_poller_output") &&
	db_fetch_cell("SELECT 1 FROM `settings`
		WHERE name = 'boost_rrd_update_enable'
		AND value = 'on'")) ? true : false;

$boost_server_enabled = (db_fetch_cell("SELECT 1 FROM `settings`
	WHERE name = 'boost_server_enable'
	AND value = 'on'")) ? true : false;


/* setup paths */
$path_rrdtool = read_config_option('path_rrdtool');
$path_rra = getcwd() . DIRECTORY_SEPARATOR . 'rra';

/* setup RRDtool Pipes */
$rrdtool_process_pipes = rrdtool_pipe_init($path_rrdtool);
$rrdtool_process       = $rrdtool_process_pipes[0];
$rrdtool_pipes         = $rrdtool_process_pipes[1];

/* scan the system to get an idea of how files and folders we have */
$scanned_directory = dirToArray($path_rra);
/*
$scanned_directory = array(
    'files' => 0,
    'folders' => 1,
    'content' => Array
        (
            2446 => Array
                (
                    'files' => 1,
                    'folders' => 0,
                    'content' => Array
                        (
                            0 => '42194.rrd',
                        )

                )
		)
	);
*/


$known_cfs = array(1 => 'AVERAGE', 2 => 'MIN', 3 => 'MAX', 4 => 'LAST');
$known_ds_types = array(1 => 'GAUGE', 2 => 'COUNTER', 3 => 'DERIVE', 4 => 'ABSOLUTE');

if ($scanned_directory['folders']) {
	/* create backup folder */
	if (!mkdir($tmp_backup_folder)) {
		echo "ERROR: Unable to create a backup folder!" . PHP_EOL;
		exit(0);
	}

	/* create log file */
	$log_handle = fopen($tmp_backup_folder . 'tmp_rrdresize.log', 'w');

	f_notify("Boost Plugin Status", ($boost_enabled ? "enabled" : "disabled"));
	f_log("Boost Plugin Status " . ($boost_enabled ? "enabled" : "disabled"));
	f_notify("Boost Server Status", ($boost_server_enabled ? "enabled" : "disabled"));
	f_log("Boost Server Status " . ($boost_server_enabled ? "enabled" : "disabled"));
	f_log("Total number of RRD files found: " . $total_files);

	/* generate a list of hosts using this data template id */
	$db_hosts = db_fetch_assoc_prepared(
		'SELECT DISTINCT host_id
		FROM data_local
		WHERE data_template_id = ?',
		array($data_template_id)
	);

	$dt_hosts = array();
	if (sizeof($db_hosts) > 0) {
		foreach ($db_hosts as $key => $value) {
			$dt_hosts[] = $value["host_id"];
		}
	} else {
		echo "No hosts found." . PHP_EOL;
		exit(1);
	}

	$db_data_sources = db_fetch_assoc(
		'SELECT DISTINCT local_data_id
		FROM data_template_data
		WHERE data_template_id = ?
		AND data_source_path IS NOT NULL',
		array($data_template_id)
	);

	$dt_data_sources = array();
	if (sizeof($db_data_sources) > 0) {
		foreach ($db_data_sources as $key => $value) {
			$dt_data_sources[] = $value["local_data_id"];
		}
	} else {
		echo "No data sources found." . PHP_EOL;
		exit(1);
	}

	$subfolders = $scanned_directory['content'];

	$counter = 0;
	foreach ($subfolders as $host_id => $host_data) {

		if (!in_array($host_id, $dt_hosts)) continue;

		if ($host_data['files']) {
			$host_files = $host_data['content'];

			foreach ($host_files as $host_file) {

				$local_data_id = intval(substr($host_file, 0, -4));

				if (!in_array($local_data_id, $dt_data_sources))	continue;

				/************** Data Template Data *****************/
				$file = $path_rra . DIRECTORY_SEPARATOR . $host_id . DIRECTORY_SEPARATOR . $host_file;

				if (!$local_data_id) {
					f_log('[ERROR] Unable to detect local data id: ' . $file);
					$total_errors++;
					continue;
				}

				if (!is_readable($file)) {
					f_log('[ERROR] : Read permissions required for file: ' . $file);
					$total_errors++;
					continue;
				}

				if (!is_resource_writable($file)) {
					f_log('[ERROR] : Write permissions required for file: ' . $file);
					$total_errors++;
					continue;
				}

				$search_pattern = '<path_rra>/' . $host_id . '/' . $host_file;
				$local_data = db_fetch_row(
					"SELECT *
					FROM data_template_data
					WHERE data_source_path = ?",
					array(db_qstr($search_pattern))
				);

				if ($local_data) {
					if ($local_data['data_template_id'] != $data_template_id) {
						/* this file has not to be update due to template mismatches */
						f_log('[SKIPPED] Template mismatch: ' . $file);
						continue;
					}

					f_notify('File', $file);
					f_notify('Local Data ID', $local_data_id);
					f_notify('Cacti Settings ');

					/* grep all data template settings */
					$data_template_ds            = db_fetch_assoc_prepared(
						"SELECT
						id, rrd_maximum, rrd_minimum, rrd_heartbeat,
						data_source_type_id, data_source_name
						FROM data_template_rrd
						WHERE hash != ''
						AND data_template_id = ? ORDER BY id",
						array($local_data['data_template_id'])
					);
					$data_template_ds_counter    = sizeof($data_template_ds);
					$data_template_data_settings = db_fetch_assoc_prepared(
						'SELECT *
						FROM data_template_data_rra
						LEFT JOIN rra
						ON rra.id = data_template_data_rra.rra_id
						RIGHT JOIN rra_cf
						ON rra_cf.rra_id = rra.id
						WHERE data_template_data_rra.data_template_data_id = ?',
						array($local_data['id'])
					);
					f_notify(false, "\033[0;32m[COMPLETED]\033[0m");
				} else {
					f_log('[OUTDATED] Unable to detect referenced local data id: ' . $file);
					continue;
				}

				/************** BOOST update process *****************/
				f_notify('Boost update');
				if ($boost_enabled && !$dry_run_mode) {
					$boost_update = true; //placeholder
					//----- run on demand update if Boost is enabled and cached data is part of the report period -----
					$output = boost_process_poller_output($boost_server_enabled, $local_data_id);
					f_notify(false, "\033[0;32m[COMPLETED]\033[0m");
				} else {
					f_notify(false, "\033[0;36m[SKIPPED]\033[0m");
				}

				f_notify('RRDtool info');
				$output = rrdtool_pipe_execute(' info ' . $file . "\r\n", $rrdtool_pipes);
				if (strpos($output, 'ERROR')) {
					f_notify(false, "\033[0;31m[FAILED]\033[0m");
					f_log('[ERROR] Unable to fetch RRDtool Info: ' . $file);
					continue;
				} else {
					$rrd_info = rrdtool_parse_info($output);
					if ($rrd_info) {
						f_notify(false, "\033[0;32m[COMPLETED]\033[0m");
					} else {
						f_notify(false, "\033[0;31m[FAILED]\033[0m");
						f_log('[ERROR] Unable to parse RRDtool Info: ' . $file);
						continue;
					}

					/********************* Analyze Data Structure ************************/
					f_notify('DS Structure');
					$ds_mismatch = false;
					$tmp_ds = $rrd_info['ds'];

					foreach ($data_template_ds as $data_template__ds_settings) {
						$found = false;
						$defined_ds_name        = $data_template__ds_settings['data_source_name'];
						$defined_ds_type        = $known_ds_types[$data_template__ds_settings['data_source_type_id']];
						$defined_minimum        = is_numeric($data_template__ds_settings['rrd_minimum']) ? $data_template__ds_settings['rrd_minimum'] : 'NaN';
						$defined_maximum        = is_numeric($data_template__ds_settings['rrd_maximum']) ? $data_template__ds_settings['rrd_maximum'] : 'NaN';
						$defined_min_heartbeat  = $data_template__ds_settings['rrd_heartbeat'];

						if (isset($tmp_ds[$defined_ds_name])) {
							$tmp_ds_rrd = $tmp_ds[$defined_ds_name];
							if ($tmp_ds_rrd['type'] == $defined_ds_type && $tmp_ds_rrd['minimal_heartbeat'] == $defined_min_heartbeat && $tmp_ds_rrd['min'] == $defined_minimum && $tmp_ds_rrd['max'] == $defined_maximum) {
								$found = true;
							} else {
								$ds_mismatch = true;
								$ds_error = 'definition';
								$ds_mismatch_fixable = ($tmp_ds_rrd['type'] == $defined_ds_type) ? true : false;

								if ($tmp_ds_rrd['minimal_heartbeat'] != $defined_min_heartbeat) {
									$rrd_info['ds'][$defined_ds_name]['minimal_heartbeat'] = $defined_min_heartbeat;
									f_log('[NOTICE] Data Source \'' . $defined_ds_name . '\' heartbeat mismatch: ' . $file);
								}
								if ($tmp_ds_rrd['min'] != $defined_minimum) {
									$rrd_info['ds'][$defined_ds_name]['min'] = $defined_minimum;
									f_log('[NOTICE] Data Source \'' . $defined_ds_name . '\' minimum mismatch: ' . $file);
								}
								if ($tmp_ds_rrd['max'] != $defined_maximum) {
									$rrd_info['ds'][$defined_ds_name]['max'] = $defined_maximum;
									f_log('[NOTICE] Data Source \'' . $defined_ds_name . '\' maximum mismatch: ' . $file);
								}
							}
							unset($tmp_ds[$defined_ds_name]);
						} else {
							$ds_mismatch = true;
							$ds_error = 'missing';
							break;
						}
					}

					$ds_superfluos_ids = array();

					if ($ds_mismatch == true) {
						if ($ds_error == 'missing') {
							f_notify(false, "\033[0;31m[COUNT - MISSING DS]\033[0m");
							f_log('[ERROR] Data source missing: ' . $file);
							continue;
						} elseif ($ds_error == 'definition') {
							if (!$ds_mismatch_fixable) {
								f_notify(false, "\033[0;31m[UNFIXABLE]\033[0m");
								f_log('[ERROR] Unable to fix misconfigured data source: ' . $file);
								continue;
							} else {
								f_notify(false, "\033[0;35m[FIXABLE]\033[0m");
							}
						}
					} else {
						f_notify(false, "\033[0;32m[MATCH]\033[0m");
					}

					if (!empty($tmp_ds)) {
						/* Additional ds found ! :| */
						$ds_names = array_keys($rrd_info['ds']);
						foreach ($tmp_ds as $tmp_ds_name => $tmp_ds_settings) {
							$ds_superfluos_ids += array_keys($ds_names, $tmp_ds_name);
						}

						f_notify('DS COUNT');
						f_notify(false, "\033[0;35m[MISMATCH]\033[0m");
						f_log('[NOTICE] Superfluous data sources \'' . implode(',', $ds_superfluos_ids) . '\' found: ' . $file);
					} else {
						f_notify('DS COUNT');
						f_notify(false, "\033[0;32m[MATCH]\033[0m");
					}

					f_notify('RRA Structure');
					$rra_mismatch = false;
					$tmp_rras = $rrd_info['rra'];

					foreach ($data_template_data_settings as $data_template__rra_settings) {

						/* rra exists - let us compare the details */
						$found        = false;
						$defined_cf   = $known_cfs[$data_template__rra_settings['consolidation_function_id']];
						$defined_rows = $data_template__rra_settings['rows'];
						$defined_xff  = $data_template__rra_settings['x_files_factor'];
						$defined_ppr  = $data_template__rra_settings['steps'];

						foreach ($tmp_rras as $tmp_rra) {
							if ($tmp_rra['cf'] == $defined_cf && $tmp_rra['rows'] == $defined_rows && $tmp_rra['xff'] == $defined_xff &&	$tmp_rra['pdp_per_row'] == $defined_ppr) {
								$found = true;
								break;
							}
						}

						if (!$found) {
							$rra_mismatch = true;
							break;
						}
					}

					if ($rra_mismatch == false) {
						f_notify(false, "\033[0;32m[MATCH]\033[0m");
					} else {
						f_notify(false, "\033[0;35m[MISMATCH]\033[0m");
					}
				}

				if ($rra_mismatch == false & $ds_mismatch == false) {
					f_notify("Update", "\033[0;35m[NOT NECESSARY]\033[0m");
					continue;
				}

				f_notify('RRDtool dump');
				$file_dump = rrdtool_pipe_execute(' dump ' . $file . "\r\n", $rrdtool_pipes);

				if (strpos($file_dump, 'ERROR')) {
					f_notify(false, "\033[0;31m[FAILED]\033[0m");
					### log_missing
					continue;
				} else {
					$rrd_data = json_decode(json_encode(simplexml_load_string($file_dump)), true);
					if ($rrd_data === false) {
						f_notify(false, "\033[0;31m[FAILED]\033[0m");
						### log_missing
						continue;
					} else {
						f_notify(false, "\033[0;32m[COMPLETED]\033[0m");
					}
				}

				/***************** Re-configuration process starts *****************************/
				f_notify("Rebuilding RRAs");

				$rra_timespans = array();
				$tmp_step = $rrd_info['step'];
				foreach ($tmp_rras as $rra_index => $rra_settings) {
					$rra_cf = $rra_settings['cf'];
					$step = $rra_settings['pdp_per_row'] * $tmp_step;

					$end = $rrd_info['last_update'] - $rrd_info['last_update'] % ($tmp_step * $rra_settings['pdp_per_row']);
					$start = $end - ($rra_settings['rows'] - 1) * $step;

					$rra_timespans[$rra_settings['cf']][$rra_index] = array(
						'step' => $step,
						'start' => $start,
						'end' => $end
					);
				}

				$rrd_new_header = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL .
					'<!DOCTYPE rrd SYSTEM "http://oss.oetiker.ch/rrdtool/rrdtool.dtd">' . PHP_EOL .
					'<rrd>' . PHP_EOL .
					"\t<version>0003</version>" . PHP_EOL .
					"\t<step>300</step>" . PHP_EOL .
					"\t<lastupdate>" . $rrd_info['last_update'] . "</lastupdate>" . PHP_EOL;

				$rrd_new_ds_definition = '';
				$i = 0;
				foreach ($rrd_info['ds'] as $rrd_info_ds_name => $rrd_info_ds_settings) {
					if (!in_array($i, $ds_superfluos_ids)) {
						$rrd_new_ds_definition__ds_definition_template = "\t<ds>" . PHP_EOL .
							"\t\t<name> " . $rrd_info_ds_name . " </name>" . PHP_EOL .
							"\t\t<type> " . $rrd_info_ds_settings['type'] . " </type>" . PHP_EOL .
							"\t\t<minimal_heartbeat>" . $rrd_info_ds_settings['minimal_heartbeat'] . "</minimal_heartbeat>"  . PHP_EOL .
							"\t\t<min>" . $rrd_info_ds_settings['min'] . "</min>" . PHP_EOL .
							"\t\t<max>" . $rrd_info_ds_settings['max'] . "</max>" . PHP_EOL .
							"\t\t<last_ds>" . $rrd_info_ds_settings['last_ds'] . "</last_ds>" . PHP_EOL .
							"\t\t<value>" . $rrd_info_ds_settings['value'] . "</value>" . PHP_EOL .
							"\t\t<unknown_sec> " . $rrd_info_ds_settings['unknown_sec'] . " </unknown_sec>" . PHP_EOL .
							"\t</ds>" . PHP_EOL;
						$rrd_new_ds_definition .= $rrd_new_ds_definition__ds_definition_template;
					}
					$i++;
				}

				$rrd_new_header .= $rrd_new_ds_definition;
				$rrd_new_body    = "\t<!-- Round Robin Archives -->" . PHP_EOL;
				$rrd_new_body__ds_definition = '';

				/* create one fake entry to fill up gaps we do not have data for */
				if ($data_template_ds_counter == 1) {
					$row_copy_fake = 'NaN';
				} else {
					$row_copy_fake = array();
					for ($i = 0; $i < $data_template_ds_counter; $i++) {
						$row_copy_fake[] = 'NaN';
					}
				}

				/* create new archives uses the data template definitions provided by Cacti and fill them up with existing data */

				foreach ($data_template_data_settings as $data_template__rra_settings) {
					$defined_cf   = $known_cfs[$data_template__rra_settings['consolidation_function_id']];
					$defined_rows = $data_template__rra_settings['rows'];
					$defined_xff  = $data_template__rra_settings['x_files_factor'];
					$defined_ppr  = $data_template__rra_settings['steps'];

					$defined_step      = $data_template__rra_settings['steps'] * $rrd_info['step'];
					#$defined_timespan = $data_template__rra_settings['timespan'];
					$defined_timespan  = $defined_rows * $defined_step;

					$last_data_point   = $rrd_info['last_update'] - $rrd_info['last_update'] % $defined_step;
					$first_data_point  = $last_data_point - $defined_timespan + $defined_step;
					$timestamps        = array_flip(range($first_data_point, $last_data_point, $defined_step));

					$rrd_new_body .= "\t<rra>" . PHP_EOL .
						"\t\t<cf>" . $defined_cf . "</cf>" . PHP_EOL .
						"\t\t<pdp_per_row>" . $defined_ppr . "</pdp_per_row>" . PHP_EOL . PHP_EOL .
						"\t\t<params>" . PHP_EOL .
						"\t\t<xff>" . sprintf('%e', $defined_xff) . "</xff>" . PHP_EOL .
						"\t\t</params>" . PHP_EOL .
						"\t\t<cdp_prep>" . PHP_EOL;

					$rrd_new_body__ds_definition = '';
					for ($i = 0; $i < $data_template_ds_counter; $i++) {
						$rrd_new_body__ds_definition_template = "\t\t\t<ds>" . PHP_EOL .
							"\t\t\t<primary_value>__placeholder__$i</primary_value>" . PHP_EOL .
							"\t\t\t<secondary_value>NaN</secondary_value>"  . PHP_EOL .
							"\t\t\t<value>NaN</value>" . PHP_EOL .
							"\t\t\t<unknown_datapoints>0</unknown_datapoints>" . PHP_EOL .
							"\t\t\t</ds>" . PHP_EOL;
						$rrd_new_body__ds_definition .= $rrd_new_body__ds_definition_template;
					}

					$rrd_new_body .= $rrd_new_body__ds_definition .
						"\t\t</cdp_prep>" . PHP_EOL .
						"\t\t<database>" . PHP_EOL;

					foreach ($timestamps as $timestamp => $dummy) {
						$step = 9999999999;

						$selected_archive_index = false;

						foreach ($rra_timespans[$defined_cf] as $g_rra_index => $g_rra_settings) {

							if ($g_rra_settings['step'] > $defined_step) {
								if (($timestamp > $g_rra_settings['start']) && ($timestamp <= $g_rra_settings['end']) && ($g_rra_settings['step'] <= $step)) {
									$consolidation_required = false;
									$step                   = $g_rra_settings['step'];
									$selected_archive_index = $g_rra_index;
								}
							} else if ($g_rra_settings['step'] < $defined_step) {
								if ((($timestamp - $defined_step) >= $g_rra_settings['start']) && ($timestamp <= $g_rra_settings['end']) && ($g_rra_settings['step'] <= $step)) {
									$consolidation_required = true;
									$step                   = $g_rra_settings['step'];
									$selected_archive_index = $g_rra_index;
								}
							} else {
								if (($timestamp > $g_rra_settings['start']) && ($timestamp <= $g_rra_settings['end'])) {
									$consolidation_required = false;
									$step                   = $g_rra_settings['step'];
									$selected_archive_index = $g_rra_index;
									break;
								}
							}
						}

						/* There's no data available we could reuse. Fill these entries with NaNs and jump to the next one */
						if ($selected_archive_index === false) {
							$rrd_new_body .= "\t\t\t<!-- " . date("Y-m-d H:i:s ", ($timestamp)) . "GMT / " . ($timestamp) . " --> <row><v>" . (is_array($row_copy_fake) ? implode('</v><v>', $row_copy_fake) : $row_copy_fake)  . "</v></row>" . PHP_EOL;
							continue;
						}
						$g_rra_settings = $rra_timespans[$defined_cf][$selected_archive_index];

						if (!$consolidation_required) {
							/* calculate the correct index number of the selected archive */
							$calculated_index = ($timestamp - $timestamp % $g_rra_settings['step'] - $g_rra_settings['start'] - $defined_step) / $g_rra_settings['step'];
							if ($g_rra_settings['step'] > $defined_step && ($timestamp % $g_rra_settings['step']) == 0) {
								$calculated_index--;
							}
							if (isset($rrd_data['rra'][$selected_archive_index]['database']['row'][$calculated_index]['v'])) {
								$row_copy = $rrd_data['rra'][$selected_archive_index]['database']['row'][$calculated_index]['v'];
								if (!empty($ds_superfluos_ids)) {
									foreach ($ds_superfluos_ids as $ds_superfluos_id) {
										unset($row_copy[$ds_superfluos_id]);
									}
								}

								$rrd_new_body .= "\t\t\t<!-- " . date("Y-m-d H:i:s ", ($timestamp)) . "GMT / " . ($timestamp) . " --> <row><v>" . (is_array($row_copy) ? implode('</v><v>', $row_copy) : $row_copy)  . "</v></row>" . PHP_EOL;
							} else {
								f_notify(false, "\033[0;31m[FAILED]\033[0m");
								print_r($rra_timespans);
								print $timestamp . '::' . $defined_cf;
								//print $rrd_new_body;
								/// log_missing
								continue 4;
							}
						} else {
							/* data consolidation required, because we will start grepping data from an archive with a higher granularity */
							/* calculate the correct index number of the selected archive */
							$calculated_index = ($timestamp - $timestamp % $g_rra_settings['step'] - $g_rra_settings['start'] - $defined_step) / $g_rra_settings['step'];
							$high_granulary_rows_required = round($defined_step / $g_rra_settings['step'], 0);
							$consolidated_ds_values = array();

							if (is_array($rrd_data['rra'][$selected_archive_index]['database']['row'][$calculated_index]['v'])) {
								foreach ($rrd_data['rra'][$selected_archive_index]['database']['row'][$calculated_index]['v'] as $ds_index => $ds_value) {
									$unconsolidated_ds_values = array();

									for ($i = 0; $i < $high_granulary_rows_required; $i++) {
										$unconsolidated_ds_values[] = $rrd_data['rra'][$selected_archive_index]['database']['row'][$calculated_index + $i]['v'][$ds_index];
									}

									foreach ($unconsolidated_ds_values as $key => $value) {
										if (!is_numeric($value) | is_null($value)) {
											unset($unconsolidated_ds_values[$key]);
										}
									}

									if (count($unconsolidated_ds_values) > 0) {
										/* drop all NaNs */

										switch ($defined_cf) {
											case 'AVERAGE':
												$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : array_sum($unconsolidated_ds_values) / $high_granulary_rows_required;
												break;
											case 'MAX':
												$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : max($unconsolidated_ds_values);
												break;
											case 'MIN':
												$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : min($unconsolidated_ds_values);
												break;
											case 'LAST':
												$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : end($unconsolidated_ds_values);
												break;
										}
										$consolidated_ds_values[$ds_index] = preg_replace('/(e[+-])(\d)$/', '${1}0$2', sprintf('%.10e', $consolidated_value));
									} else {
										$consolidated_ds_values[$ds_index] = 'NaN';
									}
								}
							} else {
								$unconsolidated_ds_values = array();

								for ($i = 0; $i < $high_granulary_rows_required; $i++) {
									$unconsolidated_ds_values[] = $rrd_data['rra'][$selected_archive_index]['database']['row'][$calculated_index + $i]['v'];
								}

								foreach ($unconsolidated_ds_values as $key => $value) {
									if (!is_numeric($value) | is_null($value)) {
										unset($unconsolidated_ds_values[$key]);
									}
								}

								if (count($unconsolidated_ds_values) > 0) {
									/* drop all NaNs */

									switch ($defined_cf) {
										case 'AVERAGE':
											$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : array_sum($unconsolidated_ds_values) / $high_granulary_rows_required;
											break;
										case 'MAX':
											$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : max($unconsolidated_ds_values);
											break;
										case 'MIN':
											$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : min($unconsolidated_ds_values);
											break;
										case 'LAST':
											$consolidated_value = empty($unconsolidated_ds_values) ? 'NaN' : end($unconsolidated_ds_values);
											break;
									}
									$consolidated_ds_values[0] = preg_replace('/(e[+-])(\d)$/', '${1}0$2', sprintf('%.10e', $consolidated_value));
								} else {
									$consolidated_ds_values[0] = 'NaN';
								}
							}

							if (!empty($ds_superfluos_ids)) {
								foreach ($ds_superfluos_ids as $ds_superfluos_id) {
									unset($consolidated_ds_values[$ds_superfluos_id]);
								}
							}
							$rrd_new_body .= "\t\t\t<!-- " . date("Y-m-d H:i:s ", ($timestamp)) . "GMT / " . ($timestamp) . " --> <row><v>" . implode('</v><v>', $consolidated_ds_values) . "</v></row>" . PHP_EOL;
						}
					}

					/* return the last identified value */
					$last_values = (!isset($calculated_index)) ? 'NaN' : $rrd_data['rra'][$selected_archive_index]['database']['row'][$calculated_index]['v'];
					if (is_array($last_values)) {
						foreach ($last_values as $index => $value) {
							$rrd_new_body = str_replace('>__placeholder__' . $index . '<', '>' . $value . '<', $rrd_new_body);
						}
					} else {
						$rrd_new_body = str_replace('>__placeholder__0<', '>' . $last_values . '<', $rrd_new_body);
					}

					$rrd_new_body .= "\t\t</database>" . PHP_EOL .
						"\t</rra>" . PHP_EOL;
				}
				f_notify(false, "\033[0;32m[COMPLETED]\033[0m");

				/* update temporary XML file */
				f_notify('Create Temp File');
				if (!file_put_contents($tmp_xml_file, $rrd_new_header . $rrd_new_body . "</rrd>")) {
					// Critical ERROR - This should never happen
					f_notify(false, "\033[0;31m[FAILED]\033[0m");
					f_log('[ERROR] Unable to create and update temporary XML file: ./tmp_rrdresize.xml');
					if (!$debug_mode) {
						f_notify(false, '[ERROR] Unable to create and update temporary XML file: ./tmp_rrdresize.xml', false);
					}
					exit;
				} else {
					f_notify(false, "\033[0;32m[COMPLETED]\033[0m");
				};

				/* Use RRDtool to verify if XML structure is valid */
				f_notify('RRDtool Restore');
				$file_restore = rrdtool_pipe_execute(' restore -r -f ' . $tmp_xml_file . ' ' . $tmp_rrd_file . "\r\n", $rrdtool_pipes);
				if (strpos($file_restore, 'ERROR')) {
					f_notify(false, "\033[0;31m[FAILED]\033[0m");
					/// log_missing
					continue;
				} else {
					f_notify(false, "\033[0;32m[COMPLETED]\033[0m");

					/* create host backup directory if it is still not existing */
					if (!is_dir($tmp_backup_folder . $host_id)) mkdir($tmp_backup_folder . $host_id);

					f_notify('RRDtool Backup');
					if (!copy($file, $tmp_backup_folder . $host_id . '/' . $host_file)) {
						f_notify(false, "\033[0;31m[FAILED]\033[0m");
					} else {
						f_notify(false, "\033[0;32m[COMPLETED]\033[0m");

						f_notify('RRDtool Replace');
						if (!$dry_run_mode) {
							if (!copy($tmp_rrd_file, $file) || !chmod($file, 0644)) {
								f_notify(false, "\033[0;31m[FAILED]\033[0m");
							} else {
								f_notify(false, "\033[0;32m[COMPLETED]\033[0m");
							}
						} else {
							f_notify(false, "\033[0;36m[SKIPPED]\033[0m");
						}
					}
				}

				if (!$debug_mode) fwrite(STDOUT, '.');
				$counter++;
				if ($counter % 1000 == 0) fwrite(STDOUT, $counter);
			}
		}
	}
}

if (!$debug_mode) fwrite(STDOUT, "\r\n");
exit;


function rrdtool_parse_info($lines) {
	$store = array();
	$lines = explode(PHP_EOL, trim($lines));

	foreach ($lines as $line) {
		list($raw_key, $raw_val) = explode(' = ', $line);

		$keys      = preg_split('/[\.\[\]]/', $raw_key, -1, PREG_SPLIT_NO_EMPTY);
		$key_count = count($keys);
		$pointer   = &$store;

		foreach ($keys as $key_num => $key) {
			if (!array_key_exists($key, $pointer)) {
				$pointer[$key] = array();
			}
			$pointer = &$pointer[$key];
			if ($key_num + 1 === $key_count) {
				$pointer = trim($raw_val, '"');
			}
		}
	}
	return $store;
}


function rrdtool_pipe_init($path_rrdtool) {

	$fds = array(
		0 => array('pipe', 'r'),		// stdin
		1 => array('pipe', 'w'),		// stdout
		2 => array('file', '/dev/null', 'a')	// stderr
	);
	$process = proc_open($path_rrdtool . " -", $fds, $pipes);

	/* make stdin/stdout/stderr non-blocking */
	stream_set_blocking($pipes[0], 0);
	stream_set_blocking($pipes[1], 0);
	return array($process, $pipes);
}

function rrdtool_pipe_close($process) {
	proc_close($process);
}

function rrdtool_pipe_execute($command, $pipes) {
	$stdout = '';
	$return_code = fwrite($pipes[0], $command);

	while (!feof($pipes[1])) {
		$line = fgets($pipes[1], 8092);
		/* return the complete output cause the logic behind a proxy should be as simple as possible */
		if (substr_count($line, "OK u")) {
			break;
		}
		$stdout .= $line;
	}
	if (strlen($stdout)) return $stdout;
}

function dirToArray($dir) {
	global $total_files;

	$files = 0;
	$folders = 0;

	$result = array();

	$cdir = scandir($dir);
	if ($cdir) {
		foreach ($cdir as $key => $value) {
			if (!in_array($value, array(".", ".."))) {
				if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
					$folders++;
					$result[$value] = dirToArray($dir . DIRECTORY_SEPARATOR . $value);
				} else {
					if (substr($value, -4) == '.rrd') {
						$result[] = $value;
						$files++;
						$total_files++;
					}
				}
			}
		}
	}

	return $summary = array('files' => $files, 'folders' => $folders, 'content' => $result);
}


function f_notify($category = false, $status = false, $debug_mode_only = true) {
	global $debug_mode;

	if (!$debug_mode && $debug_mode_only) {
		return true;
	} else {
		if ($category && $status) {
			fwrite(STDOUT, sprintf("  %-20s: %s\r\n", $category, $status));
		} else if (!$category && $status) {
			fwrite(STDOUT, sprintf("%s\r\n", $status));
		} else if ($category && !$status) {
			fwrite(STDOUT, sprintf("  %-20s: ", $category));
		} else {
			return false;
		}
	}
	return true;
}

function f_log($msg) {
	global $logging, $log_handle, $total_outdated, $total_errors, $total_skipped, $total_mismatches;

	fwrite($log_handle, date("Y-m-d H:i:s T", time()) . "   " . $msg . PHP_EOL);
	if (strpos($msg, '[ERROR]')) {
		$total_errors++;
		$total_skipped++;
	} elseif (strpos($msg, '[OUTDATED]')) {
		$total_outdated++;
		$total_skipped++;
	} elseif (strpos($msg, '[SKIPPED]')) {
		$total_skipped++;
	}
}

function display_version() {
	$version = get_cacti_cli_version();
	echo "RRDfile Reassign Data Template, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();
	echo "A simple command line utility to analyse and reassign data template settings\nto RRDfiles based upon." . PHP_EOL . PHP_EOL;
	echo "usage: rrdresize.php --data-template-id=[ID] --backup=[PATH] [--dry-run] [--debug, -d]" . PHP_EOL . PHP_EOL;
	echo "    --data-template-id          the numerical ID of the host" . PHP_EOL;
	echo "    --backup                    path to backup original RRDfiles" . PHP_EOL;
	echo "    --dry-run                   no boost cache update and no replacement of rrdfiles" . PHP_EOL;
	echo "    --debug, -d                 show detail processing data" . PHP_EOL;
	echo "    --list-data-templates       list all data templates" . PHP_EOL . PHP_EOL;
}

?>