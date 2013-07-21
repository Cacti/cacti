<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log("WARNING: Cacti Poller process terminated by user", TRUE);

			/* record the process as having completed */
			record_cmdphp_done();

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

/* let the poller server know about cmd.php being finished */
function record_cmdphp_done($pid = "") {
	if ($pid == "") $pid = getmypid();

	db_execute("UPDATE poller_time SET end_time=NOW() WHERE pid=" . $pid);
}

/* let cacti processes know that a poller has started */
function record_cmdphp_started() {
	db_execute("INSERT INTO poller_time (poller_id, pid, start_time, end_time) VALUES (0, " . getmypid() . ", NOW(), '0000-00-00 00:00:00')");
}

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$start = date("Y-n-d H:i:s"); // for runtime measurement

ini_set("max_execution_time", "0");
ini_set("memory_limit", "512M");

$no_http_headers = true;

include(dirname(__FILE__) . "/include/global.php");
include_once($config["base_path"] . "/lib/snmp.php");
include_once($config["base_path"] . "/lib/poller.php");
include_once($config["base_path"] . "/lib/rrd.php");
include_once($config["base_path"] . "/lib/ping.php");
include_once($config["library_path"] . "/variables.php");

/* notify cacti processes that a poller is running */
record_cmdphp_started();

/* correct for a windows PHP bug. fixed in 5.2.0 */
if ($config["cacti_server_os"] == "win32") {
	/* check PHP versions first, we know 5.2.0 and above is fixed */
	if (version_compare("5.2.0", PHP_VERSION, ">=")) {
		$guess = substr(__FILE__,0,2);
		if ($guess == strtoupper($guess)) {
			$response = "ERROR: The PHP Script: CMD.PHP Must be started using the full path to the file and in lower case.  This is a PHP Bug!!!";
			print "\n";
			cacti_log($response,true);

			record_cmdphp_done();
			exit("-1");
		}
	}
}

/* install signal handlers for UNIX only */
if (function_exists("pcntl_signal")) {
	pcntl_signal(SIGTERM, "sig_handler");
	pcntl_signal(SIGINT, "sig_handler");
}

/* record the start time */
list($micro,$seconds) = explode(" ", microtime());
$start = $seconds + $micro;

/* initialize the polling items */
$polling_items = array();

/* determine how often the poller runs from settings */
$polling_interval = read_config_option("poller_interval");

/* check arguments */
if ( $_SERVER["argc"] == 1 ) {
	if (isset($polling_interval)) {
		$polling_items = db_fetch_assoc("SELECT * FROM poller_item WHERE rrd_next_step<=0 ORDER by host_id");
		$script_server_calls = db_fetch_cell("SELECT count(*) from poller_item WHERE (action=2 AND rrd_next_step<=0)");
	}else{
		$polling_items = db_fetch_assoc("SELECT * FROM poller_item ORDER by host_id");
		$script_server_calls = db_fetch_cell("SELECT count(*) from poller_item WHERE (action=2)");
	}

	$print_data_to_stdout = true;
	/* get the number of polling items from the database */
	$hosts = db_fetch_assoc("select * from host where disabled = '' order by id");

	/* rework the hosts array to be searchable */
	$hosts = array_rekey($hosts, "id", $host_struc);

	$host_count = sizeof($hosts);
	$script_server_calls = db_fetch_cell("SELECT count(*) from poller_item WHERE action IN (" . POLLER_ACTION_SCRIPT_PHP . "," . POLLER_ACTION_SCRIPT_PHP_COUNT . ")");

	/* setup next polling interval */
	if (isset($polling_interval)) {
		db_execute("UPDATE poller_item SET rrd_next_step=rrd_next_step-" . $polling_interval);
		db_execute("UPDATE poller_item SET rrd_next_step=rrd_step-" . $polling_interval . " WHERE rrd_next_step < 0");
	}
}else{
	$print_data_to_stdout = false;
	if ($_SERVER["argc"] == "3") {
		if ($_SERVER["argv"][1] <= $_SERVER["argv"][2]) {
			/* address potential exploits */
			input_validate_input_number($_SERVER["argv"][1]);
			input_validate_input_number($_SERVER["argv"][2]);

			$hosts = db_fetch_assoc("
					SELECT * FROM host
					WHERE (disabled = ''
					AND id >= " . $_SERVER["argv"][1] . "
					AND id <= " . $_SERVER["argv"][2] . ")
					ORDER by id");
			$hosts      = array_rekey($hosts,"id",$host_struc);
			$host_count = sizeof($hosts);

			if (isset($polling_interval)) {
				$polling_items = db_fetch_assoc("SELECT *
					FROM poller_item
					WHERE (host_id >= " . $_SERVER["argv"][1] . "
					AND host_id <= " .    $_SERVER["argv"][2] . "
					AND rrd_next_step <= 0)
					ORDER by host_id");

				$script_server_calls = db_fetch_cell("SELECT count(*)
					FROM poller_item
					WHERE (action=2
					AND host_id >= " . $_SERVER["argv"][1] . "
					AND host_id <= " . $_SERVER["argv"][2] . "
					AND rrd_next_step <= 0)");

				/* setup next polling interval */
				db_execute("UPDATE poller_item
					SET rrd_next_step = rrd_next_step - " . $polling_interval . "
					WHERE (host_id >= " . $_SERVER["argv"][1] . "
					AND host_id <= " . $_SERVER["argv"][2] . ")");

				db_execute("UPDATE poller_item
					SET rrd_next_step = rrd_step - " . $polling_interval . "
					WHERE (rrd_next_step < 0
					AND host_id >= " . $_SERVER["argv"][1] . "
					AND host_id <= " . $_SERVER["argv"][2] . ")");
			}else{
				$polling_items = db_fetch_assoc("SELECT * from poller_item" .
						" WHERE (host_id >= " .	$_SERVER["argv"][1] . " and host_id <= " .
						$_SERVER["argv"][2] . ") ORDER by host_id");

				$script_server_calls = db_fetch_cell("SELECT count(*) from poller_item " .
						"WHERE (action IN (" . POLLER_ACTION_SCRIPT_PHP . "," . POLLER_ACTION_SCRIPT_PHP_COUNT . ") AND (host_id >= " .
						$_SERVER["argv"][1] .
						" and host_id <= " .
						$_SERVER["argv"][2] . "))");
			}
		}else{
			print "ERROR: Invalid Arguments.  The first argument must be less than or equal to the first.\n";
			print "USAGE: CMD.PHP [[first_host] [second_host]]\n";
			cacti_log("ERROR: Invalid Arguments.  This rist argument must be less than or equal to the first.");

			/* record the process as having completed */
			record_cmdphp_done();
			exit("-1");
		}
	}else{
		cacti_log("ERROR: Invalid Number of Arguments.  You must specify 0 or 2 arguments.",$print_data_to_stdout);

		/* record the process as having completed */
		record_cmdphp_done();
		exit("-1");
	}
}

if ((sizeof($polling_items) > 0) && (read_config_option("poller_enabled") == "on")) {
	$failure_type = "";
	$host_down    = false;
	$new_host     = true;
	$last_host    = "";
	$current_host = "";

	/* create new ping socket for host pinging */
	$ping = new Net_Ping;

	/* startup Cacti php polling server and include the include file for script processing */
	if ($script_server_calls > 0) {
		$cactides = array(
			0 => array("pipe", "r"), // stdin is a pipe that the child will read from
			1 => array("pipe", "w"), // stdout is a pipe that the child will write to
			2 => array("pipe", "w")  // stderr is a pipe to write to
			);

		if (function_exists("proc_open")) {
			$cactiphp = proc_open(read_config_option("path_php_binary") . " -q " . $config["base_path"] . "/script_server.php cmd", $cactides, $pipes);
			$output = fgets($pipes[1], 1024);
			if (substr_count($output, "Started") != 0) {
				if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
					cacti_log("PHP Script Server Started Properly",$print_data_to_stdout);
				}
			}
			$using_proc_function = true;
		}else {
			$using_proc_function = false;
			if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
				cacti_log("WARNING: PHP version 4.3 or above is recommended for performance considerations.",$print_data_to_stdout);
			}
		}
	}else{
		$using_proc_function = FALSE;
	}

	foreach ($polling_items as $item) {
		$data_source  = $item["local_data_id"];
		$current_host = $item["host_id"];

		if ($current_host != $last_host) {
			$new_host = true;

			/* assume the host is up */
			$host_down = false;

			/* assume we don't have to spike prevent */
			$set_spike_kill = false;

			$host_update_time = date("Y-m-d H:i:s"); // for poller update time
		}

		$host_id = $item["host_id"];

		if (($new_host) && (!empty($host_id))) {
			$ping->host = $item;
			$ping->port = $hosts[$host_id]["ping_port"];

			/* perform the appropriate ping check of the host */
			if ($ping->ping($hosts[$host_id]["availability_method"], $hosts[$host_id]["ping_method"],
				$hosts[$host_id]["ping_timeout"], $hosts[$host_id]["ping_retries"])) {
				$host_down = false;
				update_host_status(HOST_UP, $host_id, $hosts, $ping, $hosts[$host_id]["availability_method"], $print_data_to_stdout);
			}else{
				$host_down = true;
				update_host_status(HOST_DOWN, $host_id, $hosts, $ping, $hosts[$host_id]["availability_method"], $print_data_to_stdout);
			}

			if (!$host_down) {
				/* do the reindex check for this host */
				$reindex = db_fetch_assoc("select
					poller_reindex.data_query_id,
					poller_reindex.action,
					poller_reindex.op,
					poller_reindex.assert_value,
					poller_reindex.arg1
					from poller_reindex
					where poller_reindex.host_id=" . $item["host_id"]);

				if ((sizeof($reindex) > 0) && (!$host_down)) {
					if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
						cacti_log("Host[$host_id] RECACHE: Processing " . sizeof($reindex) . " items in the auto reindex cache for '" . $item["hostname"] . "'.",$print_data_to_stdout);
					}

					foreach ($reindex as $index_item) {
						$assert_fail = false;

						/* do the check */
						switch ($index_item["action"]) {

						case POLLER_ACTION_SNMP: /* snmp */
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] OID: " . $index_item["arg1"], $print_data_to_stdout);
							}
							$output = cacti_snmp_get($item["hostname"], $item["snmp_community"], $index_item["arg1"],
								$item["snmp_version"], $item["snmp_username"], $item["snmp_password"],
								$item["snmp_auth_protocol"], $item["snmp_priv_passphrase"], $item["snmp_priv_protocol"],
								$item["snmp_context"], $item["snmp_port"], $item["snmp_timeout"], read_config_option("snmp_retries"), SNMP_CMDPHP);
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] OID: " . $index_item["arg1"] . ", output: " . $output, $print_data_to_stdout);
							}

							break;

						case POLLER_ACTION_SCRIPT: /* script (popen) */
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script: " . $index_item["arg1"], $print_data_to_stdout);
							}
							$output = trim(exec_poll($index_item["arg1"]));

							/* remove any quotes from string */
							$output = strip_quotes($output);

							if (!validate_result($output)) {
								if (strlen($output) > 20) {
									$strout = 20;
								} else {
									$strout = strlen($output);
								}

								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Warning: Result from Script not valid. Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout);
							}

							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script: " . $index_item["arg1"] . ", output: $output",$print_data_to_stdout);
							}
							break;

						case POLLER_ACTION_SCRIPT_PHP: /* script (php script server) */
							if ($using_proc_function == true) {
								if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
									cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script Server: " . $index_item["arg1"], $print_data_to_stdout);
								}

								$output = trim(str_replace("\n", "", exec_poll_php($index_item["arg1"], $using_proc_function, $pipes, $cactiphp)));

								/* remove any quotes from string */
								$output = strip_quotes($output);

								if (!validate_result($output)) {
									if (strlen($output) > 20) {
										$strout = 20;
									} else {
										$strout = strlen($output);
									}

									cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] WARNING: Result from Script Server not valid. Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout);
								}

								if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
									cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script Server: " . $index_item["arg1"] . ", output: $output", $print_data_to_stdout);
								}
							}else{
								if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
									cacti_log("Host[$host_id] DS[$data_source] *SKIPPING* Script Server: " . $item["arg1"] . " (PHP < 4.3)", $print_data_to_stdout);
								}

								$output = "U";
							}

							break;
						case POLLER_ACTION_SNMP_COUNT: /* snmp; count items */
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] OID Count: " . $index_item["arg1"], $print_data_to_stdout);
							}
							$output = sizeof(cacti_snmp_walk($item["hostname"], $item["snmp_community"], $index_item["arg1"],
								$item["snmp_version"], $item["snmp_username"], $item["snmp_password"],
								$item["snmp_auth_protocol"], $item["snmp_priv_passphrase"], $item["snmp_priv_protocol"],
								$item["snmp_context"], $item["snmp_port"], $item["snmp_timeout"], read_config_option("snmp_retries"), SNMP_CMDPHP));
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] OID Count: " . $index_item["arg1"] . ", output: " . $output, $print_data_to_stdout);
							}

							break;
						case POLLER_ACTION_SCRIPT_COUNT: /* script (popen); count items */
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script Count: " . $index_item["arg1"], $print_data_to_stdout);
							}
							/* count items found */
							$script_index_array = exec_into_array($index_item["arg1"]);
							$output = sizeof($script_index_array);

							if (!validate_result($output)) {
								if (strlen($output) > 20) {
									$strout = 20;
								} else {
									$strout = strlen($output);
								}

								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Warning: Result from Script not valid. Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout);
							}

							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
								cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script Count: " . $index_item["arg1"] . ", output: $output",$print_data_to_stdout);
							}
							break;

						case POLLER_ACTION_SCRIPT_PHP_COUNT: /* script (php script server); count items */
							if ($using_proc_function == true) {
								if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
									cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script Server Count: " . $index_item["arg1"], $print_data_to_stdout);
								}

								/* fetch specified index */
								$output = 'U'; # TODO compatibility until option is correctly implemented
								cacti_log("Host[$host_id] DS[$data_source] *SKIPPING* Script Server Count: " . $item["arg1"] . " (arg_num_indexes required)", $print_data_to_stdout);
								# TODO $output = sizeof(exec_poll_php($index_item["arg1"], $using_proc_function, $pipes, $cactiphp));
								/* remove any quotes from string */
								#$output = strip_quotes($output);

								#if (!validate_result($output)) {
								#	if (strlen($output) > 20) {
								#		$strout = 20;
								#	} else {
								#		$strout = strlen($output);
								#	}

								#	cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] WARNING: Result from Script Server not valid. Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout);
								#}

								#if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
								#	cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] Script Server Count: " . $index_item["arg1"] . ", output: $output", $print_data_to_stdout);
								#}
							}else{
								if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
									cacti_log("Host[$host_id] DS[$data_source] *SKIPPING* Script Server: " . $item["arg1"] . " (PHP < 4.3)", $print_data_to_stdout);
								}

								$output = "U";
							}

							break;
						default: /* invalid reindex option */
							cacti_log("Host[$host_id] RECACHE DQ[" . $index_item["data_query_id"] . "] ERROR: Invalid reindex option: " . $index_item["action"], $print_data_to_stdout);
						}


						/* assert the result with the expected value in the db; recache if the assert fails */
						/* TODO: remove magic ":" from poller_command["command"]; this may interfere with scripts */
						if (($index_item["op"] == "=") && ($index_item["assert_value"] != trim($output))) {
							cacti_log("ASSERT: '" . $index_item["assert_value"] . "=" . trim($output) . "' failed. Recaching host '" . $item["hostname"] . "', data query #" . $index_item["data_query_id"], $print_data_to_stdout);
							db_execute("replace into poller_command (poller_id, time, action, command) values (0, NOW(), " . POLLER_COMMAND_REINDEX . ", '" . $item["host_id"] . ":" . $index_item["data_query_id"] . "')");
							$assert_fail = true;
						}else if (($index_item["op"] == ">") && ($index_item["assert_value"] < trim($output))) {
							cacti_log("ASSERT: '" . $index_item["assert_value"] . ">" . trim($output) . "' failed. Recaching host '" . $item["hostname"] . "', data query #" . $index_item["data_query_id"], $print_data_to_stdout);
							db_execute("replace into poller_command (poller_id, time, action, command) values (0, NOW(), " . POLLER_COMMAND_REINDEX . ", '" . $item["host_id"] . ":" . $index_item["data_query_id"] . "')");
							$assert_fail = true;
						}else if (($index_item["op"] == "<") && ($index_item["assert_value"] > trim($output))) {
							cacti_log("ASSERT: '" . $index_item["assert_value"] . "<" . trim($output) . "' failed. Recaching host '" . $item["hostname"] . "', data query #" . $index_item["data_query_id"], $print_data_to_stdout);
							db_execute("replace into poller_command (poller_id, time, action, command) values (0, NOW(), " . POLLER_COMMAND_REINDEX . ", '" . $item["host_id"] . ":" . $index_item["data_query_id"] . "')");
							$assert_fail = true;
						}

						/* update 'poller_reindex' with the correct information if:
						 * 1) the assert fails
						 * 2) the OP code is > or < meaning the current value could have changed without causing
						 *     the assert to fail */
						if (($assert_fail == true) || ($index_item["op"] == ">") || ($index_item["op"] == "<")) {
							db_execute("update poller_reindex set assert_value='$output' where host_id='$host_id' and data_query_id='" . $index_item["data_query_id"] . "' and arg1='" . $index_item["arg1"] . "'");

							/* spike kill logic */
							if (($assert_fail) &&
								(($index_item["op"] == "<") || ($index_item["arg1"] == ".1.3.6.1.2.1.1.3.0"))) {
								/* don't spike kill unless we are certain */
								if (!empty($output)) {
									$set_spike_kill = true;

									if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
										cacti_log("Host[$host_id] NOTICE: Spike Kill in Effect for '" . $item["hostname"] . "'.", $print_data_to_stdout);
									}
								}
							}
						}
					}
				}
			}

			$new_host = false;
			$last_host = $current_host;
		}

		if (!$host_down) {
			switch ($item["action"]) {
			case POLLER_ACTION_SNMP: /* snmp */
				if (($item["snmp_version"] == 0) || (($item["snmp_community"] == "") && ($item["snmp_version"] != 3))) {
					cacti_log("Host[$host_id] DS[$data_source] ERROR: Invalid SNMP Data Source.  Please either delete it from the database, or correct it.", $print_data_to_stdout);
					$output = "U";
				}else {
					$output = cacti_snmp_get($item["hostname"], $item["snmp_community"], $item["arg1"],
						$item["snmp_version"], $item["snmp_username"], $item["snmp_password"],
						$item["snmp_auth_protocol"], $item["snmp_priv_passphrase"], $item["snmp_priv_protocol"],
						$item["snmp_context"], $item["snmp_port"], $item["snmp_timeout"], read_config_option("snmp_retries"), SNMP_CMDPHP);

					/* remove any quotes from string */
					$output = strip_quotes($output);

					if (!validate_result($output)) {
						if (strlen($output) > 20) {
							$strout = 20;
						} else {
							$strout = strlen($output);
						}

						cacti_log("Host[$host_id] DS[$data_source] WARNING: Result from SNMP not valid.  Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout);
						$output = "U";
					}
				}

				if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
					cacti_log("Host[$host_id] DS[$data_source] SNMP: v" . $item["snmp_version"] . ": " . $item["hostname"] . ", dsname: " . $item["rrd_name"] . ", oid: " . $item["arg1"] . ", output: $output",$print_data_to_stdout);
				}

				break;
			case POLLER_ACTION_SCRIPT: /* script (popen) */
				$output = trim(exec_poll($item["arg1"]));

				/* remove any quotes from string */
				$output = strip_quotes($output);

				if (!validate_result($output)) {
					if (strlen($output) > 20) {
						$strout = 20;
					} else {
						$strout = strlen($output);
					}

					cacti_log("Host[$host_id] DS[$data_source] WARNING: Result from CMD not valid.  Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout);
				}

				if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
					cacti_log("Host[$host_id] DS[$data_source] CMD: " . $item["arg1"] . ", output: $output",$print_data_to_stdout);
				}

				break;
			case POLLER_ACTION_SCRIPT_PHP: /* script (php script server) */
				if ($using_proc_function == true) {
					$output = trim(str_replace("\n", "", exec_poll_php($item["arg1"], $using_proc_function, $pipes, $cactiphp)));

					/* remove any quotes from string */
					$output = strip_quotes($output);

					if (!validate_result($output)) {
						if (strlen($output) > 20) {
							$strout = 20;
						} else {
							$strout = strlen($output);
						}

						cacti_log("Host[$host_id] DS[$data_source] WARNING: Result from SERVER not valid.  Partial Result: " . substr($output, 0, $strout), $print_data_to_stdout);
					}

					if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
						cacti_log("Host[$host_id] DS[$data_source] SERVER: " . $item["arg1"] . ", output: $output", $print_data_to_stdout);
					}
				}else{
					if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM) {
						cacti_log("Host[$host_id] DS[$data_source] *SKIPPING* SERVER: " . $item["arg1"] . " (PHP < 4.3)", $print_data_to_stdout);
					}

					$output = "U";
				}

				break;
			default: /* invalid polling option */
				cacti_log("Host[$host_id] DS[$data_source] ERROR: Invalid polling option: " . $item["action"], $stdout);
			} /* End Switch */

			if (isset($output)) {
				/* insert a U in place of the actual value if the snmp agent restarts */
				if (($set_spike_kill) && (!substr_count($output, ":"))) {
					db_execute("insert into poller_output (local_data_id,rrd_name,time,output) values (" . $item["local_data_id"] . ",'" . $item["rrd_name"] . "','$host_update_time','" . addslashes("U") . "')");
				/* otherwise, just insert the value received from the poller */
				}else{
					db_execute("insert into poller_output (local_data_id, rrd_name, time, output) values (" . $item["local_data_id"] . ", '" . $item["rrd_name"] . "', '$host_update_time', '" . addslashes($output) . "')");
				}
			}
		} /* Next Cache Item */
	} /* End foreach */

	if (($using_proc_function == true) && ($script_server_calls > 0)) {
		// close php server process
		fwrite($pipes[0], "quit\r\n");
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$return_value = proc_close($cactiphp);
	}

	if (($print_data_to_stdout) || (read_config_option("log_verbosity") >= POLLER_VERBOSITY_MEDIUM)) {
		/* take time and log performance data */
		list($micro,$seconds) = explode(" ", microtime());
		$end = $seconds + $micro;

		cacti_log(sprintf("Time: %01.4f s, " .
			"Theads: N/A, " .
			"Hosts: %s",
			round($end-$start,4),
			$host_count),$print_data_to_stdout);
	}
}else if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_MEDIUM) {
	cacti_log("NOTE: There are no items in your poller for this polling cycle!", TRUE, "POLLER");
}

/* record the process as having completed */
record_cmdphp_done();

?>
