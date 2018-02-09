<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* api_device_crc_update - update hash stored in settings table to inform
   remote pollers to update their caches
   @arg $poller_id - the id of the poller impacted by hash update
   @arg $variable  - the hash variable prefix for the replication setting. */
function api_device_cache_crc_update($poller_id, $variable = 'poller_replicate_device_cache_crc') {
	$hash = hash('ripemd160', date('Y-m-d H:i:s') . rand() . $poller_id);

	db_execute_prepared("REPLACE INTO settings SET value = ?, name='$variable" . "_" . "$poller_id'", array($hash));
}

/* api_device_remove - removes a device
   @arg $device_id - the id of the device to remove */
function api_device_remove($device_id) {
	$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

	db_execute_prepared('DELETE FROM host             WHERE      id = ?', array($device_id));
	db_execute_prepared('DELETE FROM host_graph       WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM host_snmp_query  WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM host_snmp_cache  WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM poller_item      WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM poller_reindex   WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM graph_tree_items WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM reports_items    WHERE host_id = ?', array($device_id . ':%'));
	db_execute_prepared('DELETE FROM poller_command   WHERE command LIKE ?', array($device_id . ':%'));

	db_execute_prepared('UPDATE data_local  SET host_id = 0 WHERE host_id = ?', array($device_id));
	db_execute_prepared('UPDATE graph_local SET host_id = 0 WHERE host_id = ?', array($device_id));

	api_device_cache_crc_update($poller_id);
}

/* api_device_remove_multi - removes multiple devices in one call
   @arg $device_ids - an array of device id's to remove */
function api_device_remove_multi($device_ids) {
	$devices_to_delete = '';
	$i = 0;

	if (sizeof($device_ids)) {
		/* build the list */
		foreach($device_ids as $device_id) {
			if ($i == 0) {
				$devices_to_delete .= $device_id;
			} else {
				$devices_to_delete .= ', ' . $device_id;
			}

			/* poller commands go one at a time due to trashy logic */
			db_execute_prepared('DELETE FROM poller_item    WHERE host_id = ?', array($device_id));
			db_execute_prepared('DELETE FROM poller_reindex WHERE host_id = ?', array($device_id));
			db_execute_prepared('DELETE FROM poller_command WHERE command LIKE ?', array($device_id . ':%'));
			$i++;
		}

		$poller_ids = array_rekey(db_fetch_assoc("SELECT DISTINCT poller_id
			FROM host
			WHERE id IN ($devices_to_delete)"), 'poller_id', 'poller_id');

		db_execute("DELETE FROM host             WHERE id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_graph       WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_snmp_query  WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_snmp_cache  WHERE host_id IN ($devices_to_delete)");

		db_execute("DELETE FROM graph_tree_items WHERE host_id IN ($devices_to_delete)");

		db_execute("DELETE FROM reports_items    WHERE host_id IN ($devices_to_delete)");

		/* for people who choose to leave data sources around */
		db_execute("UPDATE data_local  SET host_id=0 WHERE host_id IN ($devices_to_delete)");
		db_execute("UPDATE graph_local SET host_id=0 WHERE host_id IN ($devices_to_delete)");
	}

	if (sizeof($poller_ids)) {
		foreach($poller_ids as $poller_id) {
			api_device_cache_crc_update($poller_id);
		}
	}
}

/* api_device_dq_add - adds a device->data query mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $data_query_id - the id of the data query to remove the mapping for
   @arg $reindex_method - the reindex method to user when adding the data query */
function api_device_dq_add($device_id, $data_query_id, $reindex_method) {
    db_execute_prepared('REPLACE INTO host_snmp_query
        (host_id, snmp_query_id, reindex_method)
        VALUES (?, ?, ?)',
        array($device_id, $data_query_id, $reindex_method));

    /* recache snmp data */
	run_data_query($device_id, $data_query_id);
}

/* api_device_dq_remove - removes a device->data query mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $data_query_id - the id of the data query to remove the mapping for */
function api_device_dq_remove($device_id, $data_query_id) {
	db_execute_prepared('DELETE FROM host_snmp_cache
		WHERE snmp_query_id = ?
		AND host_id = ?',
		array($data_query_id, $device_id));

	db_execute_prepared('DELETE FROM host_snmp_query
		WHERE snmp_query_id = ?
		AND host_id = ?',
		array($data_query_id, $device_id));

	db_execute_prepared('DELETE FROM poller_reindex
		WHERE data_query_id = ?
		AND host_id = ?',
		array($data_query_id, $device_id));
}

/* api_device_dq_change - changes a device->data query mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $data_query_id - the id of the data query to remove the mapping for
   @arg $reindex_method - the reindex method to use when changing the data query */
function api_device_dq_change($device_id, $data_query_id, $reindex_method) {
	db_execute_prepared('INSERT INTO host_snmp_query
		(host_id, snmp_query_id, reindex_method)
		VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE reindex_method=VALUES(reindex_method)',
		array($device_id, $data_query_id, $reindex_method));

	db_execute_prepared('DELETE FROM poller_reindex
		WHERE data_query_id = ?
		AND host_id = ?', array($data_query_id, $device_id));

	/* finally rerun the data query */
	run_data_query($device_id, $data_query_id);
}

/* api_device_gt_remove - removes a device->graph template mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $graph_template_id - the id of the graph template to remove the mapping for */
function api_device_gt_remove($device_id, $graph_template_id) {
	db_execute_prepared('DELETE FROM host_graph
		WHERE graph_template_id = ?
		AND host_id = ?',
		array($graph_template_id, $device_id));
}

function api_device_save($id, $host_template_id, $description, $hostname, $snmp_community, $snmp_version,
	$snmp_username, $snmp_password, $snmp_port, $snmp_timeout, $disabled,
	$availability_method, $ping_method, $ping_port, $ping_timeout, $ping_retries,
	$notes, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_engine_id, $max_oids, $device_threads, $poller_id = 1, $site_id = 1, $external_id = '') {
	global $config;

	include_once($config['base_path'] . '/lib/utility.php');
	include_once($config['base_path'] . '/lib/variables.php');
	include_once($config['base_path'] . '/lib/data_query.php');

	/* fetch some cache variables */
	if (empty($id)) {
		$_host_template_id = 0;
	} else {
		$_host_template_id = db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?', array($id));
	}

	$save['id']                   = form_input_validate($id, 'id', '^[0-9]+$', false, 3);
	$save['host_template_id']     = form_input_validate($host_template_id, 'host_template_id', '^[0-9]+$', true, 3);

	$save['poller_id']            = form_input_validate($poller_id, 'poller_id', '^[0-9]+$', true, 3);
	$save['site_id']              = form_input_validate($site_id, 'site_id', '^[0-9]+$', true, 3);
	$save['external_id']          = form_input_validate($external_id, 'external_id', '', true, 3);

	$save['description']          = form_input_validate($description, 'description', '', false, 3);
	$save['hostname']             = form_input_validate(trim($hostname), 'hostname', '', false, 3);
	$save['notes']                = form_input_validate($notes, 'notes', '', true, 3);

	$save['snmp_version']         = form_input_validate($snmp_version, 'snmp_version', '', true, 3);
	$save['snmp_community']       = form_input_validate($snmp_community, 'snmp_community', '', true, 3);

	if ($save['snmp_version'] == 3) {
		$save['snmp_username']        = form_input_validate($snmp_username, 'snmp_username', '', true, 3);
		$save['snmp_password']        = form_input_validate($snmp_password, 'snmp_password', '', true, 3);
		$save['snmp_auth_protocol']   = form_input_validate($snmp_auth_protocol, 'snmp_auth_protocol', "^\[None\]|MD5|SHA$", true, 3);
		$save['snmp_priv_passphrase'] = form_input_validate($snmp_priv_passphrase, 'snmp_priv_passphrase', '', true, 3);
		$save['snmp_priv_protocol']   = form_input_validate($snmp_priv_protocol, 'snmp_priv_protocol', "^\[None\]|DES|AES128$", true, 3);
		$save['snmp_context']         = form_input_validate($snmp_context, 'snmp_context', '', true, 3);
		$save['snmp_engine_id']       = form_input_validate($snmp_engine_id, 'snmp_engine_id', '', true, 3);

		if (strlen($save['snmp_password']) < 8 && $snmp_auth_protocol != '[None]') {
			raise_message(32);
			$_SESSION['sess_error_fields']['snmp_password'] = 'snmp_password';
		}
	} else {
		$save['snmp_username']        = '';
		$save['snmp_password']        = '';
		$save['snmp_auth_protocol']   = '';
		$save['snmp_priv_passphrase'] = '';
		$save['snmp_priv_protocol']   = '';
		$save['snmp_context']         = '';
		$save['snmp_engine_id']       = '';
	}

	$save['snmp_port']            = form_input_validate($snmp_port, 'snmp_port', '^[0-9]+$', false, 3);
	$save['snmp_timeout']         = form_input_validate($snmp_timeout, 'snmp_timeout', '^[0-9]+$', false, 3);

	/* disabled = 'on'   => regexp '^on$'
	 * not disabled = '' => no regexp, but allow nulls */
	$save['disabled']             = form_input_validate($disabled, 'disabled', '^on$', true, 3);

	$save['availability_method']  = form_input_validate($availability_method, 'availability_method', '^[0-9]+$', false, 3);
	$save['ping_method']          = form_input_validate($ping_method, 'ping_method', '^[0-9]+$', false, 3);
	$save['ping_port']            = form_input_validate($ping_port, 'ping_port', '^[0-9]+$', true, 3);
	$save['ping_timeout']         = form_input_validate($ping_timeout, 'ping_timeout', '^[0-9]+$', true, 3);
	$save['ping_retries']         = form_input_validate($ping_retries, 'ping_retries', '^[0-9]+$', true, 3);
	$save['max_oids']             = form_input_validate($max_oids, 'max_oids', '^[0-9]+$', true, 3);
	$save['device_threads']       = form_input_validate($device_threads, 'device_threads', '^[0-9]+$', true, 3);

	$save = api_plugin_hook_function('api_device_save', $save);

	$host_id = 0;

	if (!is_error_message()) {
		$host_id = sql_save($save, 'host');

		if ($host_id) {
			raise_message(1);

			/* clear the host session data for poller cache repopulation */
			if (isset($_SESSION['sess_host_cache_array'][$host_id])) {
				unset($_SESSION['sess_host_cache_array'][$host_id]);
			}

			/* change reindex method for 'None' for non-snmp devices */
			if ($save['snmp_version'] == 0) {
				db_execute_prepared('UPDATE host_snmp_query SET reindex_method = 0 WHERE host_id = ?', array($host_id));
				db_execute_prepared('DELETE FROM poller_reindex WHERE host_id = ?', array($host_id));
			}

			api_device_cache_crc_update($save['poller_id']);

			/* push out relavant fields to data sources using this host */
			push_out_host($host_id, 0);

			/* the host substitution cache is now stale; purge it */
			kill_session_var('sess_host_cache_array');

			/* update title cache for graph and data source */
			update_data_source_title_cache_from_host($host_id);
			update_graph_title_cache_from_host($host_id);
		} else {
			raise_message(2);
		}

		/* if the user changes the host template, add each snmp query associated with it */
		if (($host_template_id != $_host_template_id) && (!empty($host_template_id))) {
			api_device_update_host_template($host_id, $host_template_id);
		}
	}

	if ($host_id > 0) {
		if (read_config_option('extended_paths') == 'on'){
			$host_dir = $config['rra_path'] . '/' . $host_id;
			if (!is_dir($host_dir)){
				if (is_writable($config['rra_path'])) {
					if (mkdir($host_dir, 0775)) {
						if ($config['cacti_server_os'] != 'win32') {
							$owner_id      = fileowner($config['rra_path']);
							$group_id      = filegroup($config['rra_path']);

							if ((chown($host_dir, $owner_id)) &&
								(chgrp($host_dir, $group_id))) {
								/* permissions set ok */
							} else {
								cacti_log("ERROR: Unable to set directory permissions for '" . $host_dir . "'", false);
							}
						}
					} else {
						cacti_log("ERROR: Unable to create directory '" . $host_dir . "'", false);
					}
				} else {
					cacti_log("ERROR: Unable to create directory due to missing write permissions '" . $host_dir . "'", false);
				}
			}
		}

		# now that we have the id of the new host, we may plugin postprocessing code
		$save['id'] = $host_id;

		snmpagent_api_device_new($save);

		automation_execute_device_create_tree($host_id);

		api_plugin_hook_function('api_device_new', $save);
	}

	return $host_id;
}

/* api_device_update_host_template - changes the host template of a host
   @arg $host_id - the id of the device which contains the mapping
   @arg $host_template_id - the id of the host template alter the device to */
function api_device_update_host_template($host_id, $host_template_id) {
	db_execute_prepared('UPDATE host SET host_template_id = ? WHERE id = ?', array($host_template_id, $host_id));

	$snmp_queries = db_fetch_assoc_prepared('SELECT snmp_query_id
		FROM host_template_snmp_query
		WHERE host_template_id = ?', array($host_template_id));

	if (sizeof($snmp_queries)) {
		foreach ($snmp_queries as $snmp_query) {
			db_execute_prepared('REPLACE INTO host_snmp_query
				(host_id, snmp_query_id, reindex_method)
				VALUES (?, ?, ?)',
				array($host_id, $snmp_query['snmp_query_id'], read_config_option('reindex_method')));

			/* recache snmp data */
			run_data_query($host_id, $snmp_query['snmp_query_id']);
		}
	}

	$graph_templates = db_fetch_assoc_prepared('SELECT graph_template_id
		FROM host_template_graph
		WHERE host_template_id = ?', array($host_template_id));

	if (sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			db_execute_prepared('REPLACE INTO host_graph
				(host_id, graph_template_id)
				VALUES (?, ?)',
				array($host_id, $graph_template['graph_template_id']));

			automation_hook_graph_template($host_id, $graph_template['graph_template_id']);

			api_plugin_hook_function('add_graph_template_to_host',
				array('host_id' => $host_id, 'graph_template_id' => $graph_template['graph_template_id']));
		}
	}
}

/* api_device_template_sync_template - updates the device template mapping for all devices mapped to a template
   @arg $device_template - the device template to syncronize
   @arg $down_devices - also update mapping of down devices */
function api_device_template_sync_template($device_template, $down_devices = false) {
	if ($down_devices == true) {
		$status_where = '';
	} else {
		$status_where = ' AND status IN(3,2)';
	}

	$devices = array_rekey(
		db_fetch_assoc_prepared('SELECT id
			FROM host
			WHERE host_template_id = ?' .
			$status_where,
			array($device_template)),
		'id', 'id'
	);

	if (sizeof($devices)) {
		foreach($devices as $device) {
			api_device_update_host_template($device, $device_template);
		}
	}
}

function api_device_ping_device($device_id, $from_remote = false) {
	global $config, $snmp_error;

	if (empty($device_id)) {
		return "";
	}

	$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($device_id));
	$am   = $host['availability_method'];
	$anym = false;

	if ($config['poller_id'] != $host['poller_id'] && $from_remote == false) {
		$hostname = db_fetch_cell_prepared('SELECT hostname
			FROM poller
			WHERE id = ?',
			array($host['poller_id']));

		$fgc_contextoption = get_default_contextoption(5);
		$fgc_context       = stream_context_create($fgc_contextoption);
		$results           = @file_get_contents(get_url_type() .'://' . $hostname . $config['url_path'] . 'remote_agent.php?action=ping&host_id=' . $host['id'], false, $fgc_context);

		if ($results != '') {
			print $results;
		} else {
			print __('ERROR: Failed to connect to remote collector.');
		}

		return;
	}

	if ($host['disabled'] == 'on') {
		print __('Device is Disabled') . '<br>';
		print __('Device Availability Check Bypassed') . '<br>';
	} elseif ($am == AVAIL_SNMP || $am == AVAIL_SNMP_GET_NEXT ||
		$am == AVAIL_SNMP_GET_SYSDESC || $am == AVAIL_SNMP_AND_PING ||
		$am == AVAIL_SNMP_OR_PING) {

		$anym = true;

		print __('SNMP Information') . "<br>\n";
		print "<span class='monoSpace'>\n";

		if (($host['snmp_community'] == '' && $host['snmp_username'] == '') || $host['snmp_version'] == 0) {
			print "<span style='color: #ab3f1e; font-weight: bold;'>" . __('SNMP not in use') . "</span>\n";
		} else {
			$snmp_error = '';
			$session = cacti_snmp_session($host['hostname'], $host['snmp_community'], $host['snmp_version'],
 				$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
 				$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['snmp_port'],
				$host['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

			if ($session === false || $snmp_error > '') {
				print "<span class='hostDown'>" . __('Session') . ' ' . __('SNMP error');
				if ($snmp_error > '') {
					print " - $snmp_error";
				}
				print "</span>\n";
			} else {
				$snmp_system = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.1.0');
				if ($snmp_system === false || $snmp_system == 'U' || $snmp_error > '') {
					print "<span class='hostDown'>" . __('System') . ' ' . __('SNMP error');
					if ($snmp_error > '') {
						print " - $snmp_error";
					}
					print "</span>\n";
				} else {

					/* modify for some system descriptions */
					/* 0000937: System output in host.php poor for Alcatel */
					if (substr_count($snmp_system, '00:')) {
						$snmp_system = str_replace('00:', '', $snmp_system);
						$snmp_system = str_replace(':', ' ', $snmp_system);
					}

					if ($snmp_system == '') {
						print "<span class='hostDown'>" . __('Host') . ' ' .  __('SNMP error');
						if ($snmp_error > '') {
							print " - $snmp_error";
						}
						"</span>\n";
					} else {
						$snmp_uptime     = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.3.0');
						$snmp_hostname   = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.5.0');
						$snmp_location   = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.6.0');
						$snmp_contact    = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.4.0');

						print '<strong>' . __('System:') . '</strong> ' . html_split_string($snmp_system) . "<br>\n";
						$days      = intval($snmp_uptime / (60*60*24*100));
						$remainder = $snmp_uptime % (60*60*24*100);
						$hours     = intval($remainder / (60*60*100));
						$remainder = $remainder % (60*60*100);
						$minutes   = intval($remainder / (60*100));
						print '<strong>' . __('Uptime:') . "</strong> $snmp_uptime";
						print "&nbsp;(" . $days . __('days') . ', ' . $hours . __('hours') . ', ' . $minutes . __('minutes') . ")<br>\n";
						print "<strong>" . __('Hostname:') . "</strong> $snmp_hostname<br>\n";
						print "<strong>" . __('Location:') . "</strong> $snmp_location<br>\n";
						print "<strong>" . __('Contact:') . "</strong> $snmp_contact<br>\n";
					}

				}

				$session->close();
			}
		}
		print "</span><br>\n";
	}

	if ($am == AVAIL_PING || $am == AVAIL_SNMP_AND_PING || $am == AVAIL_SNMP_OR_PING) {
		$anym = true;

		/* create new ping socket for host pinging */
		$ping = new Net_Ping;

		$ping->host = $host;
		$ping->port = $host['ping_port'];

		/* perform the appropriate ping check of the host */
		$ping_results = $ping->ping(AVAIL_PING, $host['ping_method'], $host['ping_timeout'], $host['ping_retries']);

		if ($ping_results == true) {
			$host_down = false;
			$class     = 'hostUp';
		} else {
			$host_down = true;
			$class     = 'hostDown';
		}

		print __('Ping Results') . "<br>\n";
		print "<span class='" . $class . "'>" . $ping->ping_response . "</span>\n";
	}

	if ($anym == false && $host['disabled'] != 'on') {
		print __('No Ping or SNMP Availability Check in Use') . "<br><br>\n";
	}
}

