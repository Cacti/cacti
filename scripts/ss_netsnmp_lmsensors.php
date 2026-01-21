<?php
# ------------------------------------------------------------------------------------
# ss_netsnmp_lmsensors.php
# version 0.9a
# November 11, 2010
#
# Copyright (C) 2006-2010, Eric A. Hall
# http://www.eric-a-hall.com/
#
# This software is licensed under the same terms as Cacti itself
# ------------------------------------------------------------------------------------

# ------------------------------------------------------------------------------------
# load the Cacti configuration settings if they aren't already present
# ------------------------------------------------------------------------------------
if (!isset($called_by_script_server)) {
	include(__DIR__ . '/../include/cli_check.php');
}

# ------------------------------------------------------------------------------------
# Include the snmp api if it is not already
# ------------------------------------------------------------------------------------
if (!function_exists('cacti_snmp_walk')) {
	include($config['base_path'] . '/lib/snmp.php');
}

# ------------------------------------------------------------------------------------
# call the main function manually if executed outside the Cacti script server
# ------------------------------------------------------------------------------------
if (!isset($called_by_script_server)) {
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_netsnmp_lmsensors', $_SERVER['argv']);
}

# ------------------------------------------------------------------------------------
# main function
# ------------------------------------------------------------------------------------
function ss_netsnmp_lmsensors($host_id = '', $sensor_type = '', $cacti_request = '', $data_request = '', $data_request_key = '') {
	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		array($host_id));

	if (!cacti_sizeof($host)) {
		cacti_log(sprintf('ERROR: Device with ID %s not found!', $host_id), false, 'LMSENSORS');
		return 'U';
	}

	$sensor_type = strtolower(trim($sensor_type));

	if (($sensor_type != 'fan') && ($sensor_type != 'temperature') && ($sensor_type != 'voltage')) {
		cacti_log(sprintf('ERROR: Device with ID %s has an invalid Sensor Type of %s', $host_id, $sensor_type), false, 'LMSENSORS');
		return 'U';
	}

	$cacti_request = strtolower(trim($cacti_request));

	if ($cacti_request == '') {
		cacti_log(sprintf('ERROR: Device with ID %s has an empty request type', $host_id), false, 'LMSENSORS');
		return 'U';
	}

	if (($cacti_request != 'index') && ($cacti_request != 'query') && ($cacti_request != 'get')) {
		cacti_log(sprintf('ERROR: Device with ID %s has an invalid request of %s', $host_id, $cacti_request), false, 'LMSENSORS');
		return 'U';
	}

	# ------------------------------------------------------------------------------------
	# remaining function arguments are $data_request and $data_request_key
	# ------------------------------------------------------------------------------------
	if ($cacti_request == 'query' || $cacti_request == 'get') {
		$data_request = strtolower(trim($data_request));

		if ($data_request == '') {
			cacti_log(sprintf('ERROR: Device with ID %s has an empty get or query data request.', $host_id), false, 'LMSENSORS');
			return 'U';
		}

		if (($data_request != 'sensordevice') && ($data_request != 'sensorname') && ($data_request != 'sensorreading')) {
			cacti_log(sprintf('ERROR: Device with ID %s has an invalid get or query data request of %s', $host_id, $data_request), false, 'LMSENSORS');
			return 'U';
		}

		# ------------------------------------------------------------------------------------
		# get the index variable
		# ------------------------------------------------------------------------------------
		if ($cacti_request == 'get') {
			$data_request_key = strtolower(trim($data_request_key));

			if ($data_request_key == '') {
				cacti_log(sprintf('ERROR: Device with ID %s has an empty get or query data request.', $host_id), false, 'LMSENSORS');
				return 'U';
			}
		}  else {
			$data_request_key = '';
		}
	}

	# ------------------------------------------------------------------------------------
	# build a nested array of data elements for future use
	# ------------------------------------------------------------------------------------
	switch ($sensor_type) {
		case 'temperature':
			$oid_array = array (
				'sensorIndex'   => '.1.3.6.1.4.1.2021.13.16.2.1.1',
				'sensorName'    => '.1.3.6.1.4.1.2021.13.16.2.1.2',
				'sensorReading' => '.1.3.6.1.4.1.2021.13.16.2.1.3'
			);

			break;
		case 'fan':
			$oid_array = array (
				'sensorIndex'   => '.1.3.6.1.4.1.2021.13.16.3.1.1',
				'sensorName'    => '.1.3.6.1.4.1.2021.13.16.3.1.2',
				'sensorReading' => '.1.3.6.1.4.1.2021.13.16.3.1.3'
			);

			break;
		case 'voltage':
			$oid_array = array (
				'sensorIndex'   => '.1.3.6.1.4.1.2021.13.16.4.1.1',
				'sensorName'    => '.1.3.6.1.4.1.2021.13.16.4.1.2',
				'sensorReading' => '.1.3.6.1.4.1.2021.13.16.4.1.3'
			);

			break;
	}

	# ------------------------------------------------------------------------------------
	# build the snmp_get_arguments and snmp_walk_arguments array for future use
	#
	# note that the array structure varies according to the version of Cacti in use
	# ------------------------------------------------------------------------------------
	$snmp_get_arguments = array(
		$host['hostname'],
		$host['snmp_community'],
		'',
		$host['snmp_version'],
		$host['snmp_username'],
		$host['snmp_password'],
		$host['snmp_auth_protocol'],
		$host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'],
		$host['snmp_context'],
		$host['snmp_port'],
		$host['snmp_timeout'],
		$host['ping_retries'],
		'SNMP',
		$host['snmp_engine_id']
	);

	$snmp_walk_arguments = array(
		$host['hostname'],
		$host['snmp_community'],
		'',
		$host['snmp_version'],
		$host['snmp_username'],
		$host['snmp_password'],
		$host['snmp_auth_protocol'],
		$host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'],
		$host['snmp_context'],
		$host['snmp_port'],
		$host['snmp_timeout'],
		$host['ping_retries'],
		$host['max_oids'],
		'SNMP',
		$host['snmp_engine_id']
	);

	# ------------------------------------------------------------------------------------
	# if they want data for just one sensor, use the input data to seed the array
	# ------------------------------------------------------------------------------------
	if ($cacti_request == 'get') {
		# ------------------------------------------------------------------------------------
		# set snmp_get_arguments to sensorIndex plus the requested index value and query
		# ------------------------------------------------------------------------------------
		$snmp_get_arguments[2] = $oid_array['sensorReading'] . '.' . $data_request_key;
		$snmp_test = trim(call_user_func_array('cacti_snmp_get', $snmp_get_arguments));

		# ------------------------------------------------------------------------------------
		# the snmp response should contain a numeric counter (NOT the device index)
		# ------------------------------------------------------------------------------------
		if ((isset($snmp_test) == false) ||
			(substr($snmp_test, 0, 16) == 'No Such Instance') ||
			(is_numeric($snmp_test) == false) ||
			($snmp_test == '')) {
			cacti_log(sprintf('WARNING: Device with ID %s Does not appear to have lmsensors installed!', $host_id), false, 'LMSENSORS');

			return 'U';
		} else {
			return $snmp_test;
		}
	} else {
		# ------------------------------------------------------------------------------------
		# set the snmp_walk arguments array to the sensor Index OID
		# ------------------------------------------------------------------------------------
		$snmp_walk_arguments[2] = $oid_array['sensorIndex'];

		# ------------------------------------------------------------------------------------
		# walk the tree and capture the resulting array of sensors
		# ------------------------------------------------------------------------------------
		$snmp_array = call_user_func_array('cacti_snmp_walk', $snmp_walk_arguments);

		# ------------------------------------------------------------------------------------
		# verify that the response contains expected data structures
		# ------------------------------------------------------------------------------------
		if ((isset($snmp_array) == false) ||
			(count($snmp_array) == 0) ||
			(array_key_exists('oid', $snmp_array[0]) == false) ||
			(array_key_exists('value', $snmp_array[0]) == false) ||
			(substr($snmp_array[0]['value'],0,16) == 'No Such Instance') ||
			(is_numeric($snmp_array[0]['value']) == false) ||
			(trim($snmp_array[0]['value']) == '')) {
			cacti_log(sprintf('WARNING: Device with ID %s Does not appear to have lmsensors installed!', $host_id), false, 'LMSENSORS');
			return;
		}

		# ------------------------------------------------------------------------------------
		# create the array entries
		# ------------------------------------------------------------------------------------
		$sensor_count = 0;

		foreach ($snmp_array as $snmp_response) {
			# ------------------------------------------------------------------------------------
			# the trailing block of digits in each response OID identifies the sensor index
			# remove whitespace from around the OIDs in $snmp_array so we can match the digits
			# ------------------------------------------------------------------------------------
			$snmp_response['oid'] = trim($snmp_response['oid']);

			# ------------------------------------------------------------------------------------
			# use regex to locate the relative OIDs
			# exit if no match found
			# ------------------------------------------------------------------------------------
			if (preg_match('/(\d+)$/', $snmp_response['oid'], $scratch) == 0) {
				cacti_log(sprintf('WARNING: Device with ID %s appears to have invalid snmpwalk data returned!', $host_id), false, 'LMSENSORS');
				return;
			} else {
				$sensor_array[$sensor_count]['index'] = $scratch[1];
			}

			$sensor_count++;
		}

		#
		# requests for data other than index values require additional processing
		#
		if ($data_request != 'sensordevice') {
			$sensor_count = 0;

			foreach ($sensor_array as $sensor) {
				switch ($data_request) {
					case 'sensordevice':
						#
						# no additional data is needed for index requests
						#

						break;
					case 'sensorname':
						#
						# set the snmp_get_arguments array to the sensorname value and query
						#
						$snmp_get_arguments[2] = ($oid_array['sensorName'] . '.' . $sensor['index']);
						$scratch = trim(call_user_func_array('cacti_snmp_get', $snmp_get_arguments));

						#
						# snmp response should contain the sensor name
						#
						if ((isset($scratch) == false) || (substr($scratch, 0, 16) == 'No Such Instance') || ($scratch == '')) {
							#
							# sensor name unknown, so call it 'sensor N'
							#
							$scratch = $sensor_type . ' ' . $sensor['index'];
						}

						#
						# if the name is long and has dashes, trim it down
						#
						while ((strlen($scratch) > 18) && (strrpos($scratch, '-') > 12)) {
							$scratch = (substr($scratch,0, (strrpos($scratch, '-'))));
						}

						#
						# if the name is long and has spaces, trim it down
						#
						while ((strlen($scratch) > 18) && (strrpos($scratch, ' ') > 12)) {
							$scratch = (substr($scratch,0, (strrpos($scratch, ' '))));
						}

						#
						# if the name is still long, chop it manually
						#
						if (strlen($scratch) > 18) {
							$scratch = (substr($scratch,0,18));
						}

						#
						# store the sensor name
						#
						$sensor_array[$sensor_count]['name'] = $scratch;

						break;
					case 'sensorreading':
						#
						# get the sensor reading for each entry
						#
						$snmp_get_arguments[2] = ($oid_array['sensorReading'] . '.' . $sensor['index']);
						$scratch = trim(call_user_func_array('cacti_snmp_get', $snmp_get_arguments));

						#
						# if no useful data was returned, null the results
						#
						if ((isset($scratch) == false) || (substr($scratch, 0, 16) == 'No Such Instance') || (is_numeric($scratch) == false) || ($scratch == '')) {
							$scratch = '';
						}

						#
						# negative voltage readings must be converted to negative numbers
						#
						if (($sensor_type == 'voltage') && ($scratch > 2147483647)) {
							$scratch = ($scratch - 4294967294);
						}

						#
						# move the voltage and thermal decimal place left by three places
						#
						if (($sensor_type == 'voltage') || ($sensor_type == 'temperature')) {
							$scratch = ($scratch / 1000);
						}

						#
						# remove impossibly-high temperature and voltage readings
						#
						if ((($sensor_type == 'voltage') || ($sensor_type == 'temperature')) && ($scratch >= '255')) {
							$scratch = '';
						}

						#
						# store the sensor reading
						#
						$sensor_array[$sensor_count]['reading'] = $scratch;

						break;
				}

				#
				# increment the sensor counter
				#
				$sensor_count++;
			}
		}

		#
		# generate output
		#
		foreach ($sensor_array as $sensor) {
			switch ($cacti_request) {
				case 'index':
					if (trim($sensor['index']) != '') {
						print $sensor['index'] . PHP_EOL;
					}

					break;
				case 'query':
					switch ($data_request) {
						case 'sensordevice':
							print $sensor['index'] . ':' . $sensor['index'] . PHP_EOL;

							break;
						case 'sensorname':
							print $sensor['index'] . ':' . $sensor['name'] . PHP_EOL;

							break;
						case 'sensorreading':
							print $sensor['index'] . ':' . $sensor['reading'] . PHP_EOL;

							break;
					}

					break;
			}
		}
	}
}

