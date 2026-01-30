<?php

include(__DIR__ . '/../include/cli_check.php');
include(__DIR__ . '/../lib/snmp.php');

$xml_delimiter   =  '!';

// all required input parms
$hostname        = $_SERVER['argv'][1]; // hostname/IP@
$snmp_community  = $_SERVER['argv'][2];
$snmp_version    = $_SERVER['argv'][3];
$snmp_port       = $_SERVER['argv'][4];
$snmp_timeout    = $_SERVER['argv'][5];
$max_oids        = $_SERVER['argv'][6];

// required for SNMP V3
$snmp_auth_username    = $_SERVER['argv'][7];
$snmp_auth_password    = $_SERVER['argv'][8];
$snmp_auth_protocol    = $_SERVER['argv'][9];
$snmp_priv_passphrase  = $_SERVER['argv'][10];
$snmp_priv_protocol    = $_SERVER['argv'][11];
$snmp_context          = $_SERVER['argv'][12];
$cmd                   = $_SERVER['argv'][13];

if (isset($_SERVER['argv'][14])) {
	$query_field = $_SERVER['argv'][14];
}

if (isset($_SERVER['argv'][15])) {
	$query_index = $_SERVER['argv'][15];
}

// get number of snmp retries from global settings
$snmp_retries   = read_config_option('snmp_retries');

// Determine model of AKCP device
$model = cacti_snmp_get($hostname, $snmp_community,
	'.1.3.6.1.4.1.3854.1.1.8.0', $snmp_version, $snmp_auth_username,
	$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
	$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);

if (preg_match('/^securityProbe/',$model)) {
	$securityProbe = 1;

	$oids = [
		'index'                       => '.1.3.6.1.4.1.3854.2.3.2.1.1',  // SPAGENT-MIB::sensorTemperatureIndex
		'sensorProbeTempDescription'  => '.1.3.6.1.4.1.3854.2.3.2.1.2',  // SPAGENT-MIB::sensorTemperatureDescription
		'sensorProbeSensorType'       => '.1.3.6.1.4.1.3854.2.3.2.1.3',  // SPAGENT-MIB::sensorTemperatureType
		'sensorTemperatureUnit'       => '.1.3.6.1.4.1.3854.2.3.2.1.5',  // SPAGENT-MIB::sensorTemperatureUnit
		'sensorProbeTempStatus'       => '.1.3.6.1.4.1.3854.2.3.2.1.6',  // SPAGENT-MIB::sensorTemperatureStatus
		'sensorProbeTempOnline'       => '.1.3.6.1.4.1.3854.2.3.2.1.8',  // SPAGENT-MIB::sensorTemperatureGoOffline
		'sensorProbeTempHighWarning'  => '.1.3.6.1.4.1.3854.2.3.2.1.11', // SPAGENT-MIB::sensorTemperatureHighWarning
		'sensorProbeTempHighCritical' => '.1.3.6.1.4.1.3854.2.3.2.1.12', // SPAGENT-MIB::sensorTemperatureHighCritical
		'sensorProbeTempLowWarning'   => '.1.3.6.1.4.1.3854.2.3.2.1.10', // SPAGENT-MIB::sensorTemperatureLowWarning
		'sensorProbeTempLowCritical'  => '.1.3.6.1.4.1.3854.2.3.2.1.9',  // SPAGENT-MIB::sensorTemperatureLowCritical
		'sensorProbeTempDegreeRaw'    => '.1.3.6.1.4.1.3854.2.3.2.1.20'  // SPAGENT-MIB::sensorTemperatureRaw
	];
} else {
	$securityProbe = 0;

	$oids = [
		'index'                       => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.22',
		'sensorProbeTempDescription'  => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.1',
		'sensorProbeSensorType'       => '.1.3.6.1.4.1.3854.1.2.2.1.18.1.9',
		'sensorProbeTempStatus'       => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.4',
		'sensorProbeTempOnline'       => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.5',
		'sensorProbeTempHighWarning'  => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.7',
		'sensorProbeTempHighCritical' => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.8',
		'sensorProbeTempLowWarning'   => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.9',
		'sensorProbeTempLowCritical'  => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.10',
		'sensorProbeTempDegreeRaw'    => '.1.3.6.1.4.1.3854.1.2.2.1.16.1.14'
	];
}

// -------------------------------------------------------------------------
// main code starts here
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// script MUST respond to index queries
//       the command for this is defined within the XML file as
//       <arg_index>index</arg_index>
//       you may replace the string "index" both in the XML and here
// -------------------------------------------------------------------------
//       php -q <script> <parms> index
// will list all indices of the target values
// e.g. in case of interfaces
//      it has to respond with the list of interface indices
// -------------------------------------------------------------------------
if ($cmd == 'index') {
	// retrieve all indices from target
	$return_arr = reindex(cacti_snmp_walk($hostname, $snmp_community,
		$oids['index'], $snmp_version, $snmp_auth_username,
		$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
		$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));

	// and print each index as a separate line
	for ($i = 0; ($i < sizeof($return_arr)); $i++) {
		// Index is returned as -1 for all sensors that are something other than temperature sensors:
		// SPAGENT-MIB::sensorProbeTempIndex.1 = INTEGER: -1
		// It's not worth doing anything if this is clearly not a valid sensor for this query.
		if ($return_arr[$i] >= 0) {
			$index_type = cacti_snmp_get($hostname, $snmp_community,
				$oids['sensorProbeSensorType'] . ".$return_arr[$i]", $snmp_version, $snmp_auth_username,
				$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
				$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);

			// The >= 0 should have weeded out all the non-temperature sensors already, but just
			// just for additional clarity's sake, we check the sensorProbeSensorType table too.
			if ($index_type == '3' || $index_type == 'humidity(3)' || $index_type == '1' || $index_type == 'temperature(1)' || $index_type == '23' || $index_type == 'arraytemp(23)') {
				print $return_arr[$i] . "\n";
			}
		}
	}
}

//
// -------------------------------------------------------------------------
// script MUST respond to query requests
//       the command for this is defined within the XML file as
//       <arg_query>query</arg_query>
//       you may replace the string "query" both in the XML and here
// -------------------------------------------------------------------------
//       php -q <script> <parms> query <function>
// where <function> is a parameter that tells this script,
// which target value should be retrieved
// e.g. in case of interfaces, <function> = ifdescription
//      it has to respond with the list of
//      interface indices along with the description of the interface
// -------------------------------------------------------------------------
elseif ($cmd == 'query' && isset($query_field)) {
	$arr_index = reindex(cacti_snmp_walk($hostname, $snmp_community,
		$oids['index'], $snmp_version, $snmp_auth_username,
		$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
		$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));

	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community,
		$oids[$query_field], $snmp_version, $snmp_auth_username,
		$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
		$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));

	for ($i = 0; ($i < sizeof($arr_index)); $i++) {
		// Index is returned as -1 for all sensors that are something other than temperature sensors:
		// SPAGENT-MIB::sensorProbeTempIndex.1 = INTEGER: -1
		// It's not worth doing anything if this is clearly not a valid sensor for this query.
		if ($arr_index[$i] >= 0) {
			$index_type = cacti_snmp_get($hostname, $snmp_community,
				$oids['sensorProbeSensorType'] . ".$arr_index[$i]", $snmp_version, $snmp_auth_username,
				$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
				$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);

			if ($index_type == '3' || $index_type == 'humidity(3)' || $index_type == '1' || $index_type == 'temperature(1)' || $index_type == '23' || $index_type == 'arraytemp(23)') {
				// The securityProbe still reports raw temperature values in celsius regardless of whether the
				// sensor is configured for fahrenheit or not. The sensorProbe switches the raw reading. It
				// wasn't logical to create different graph templates to handle each scenario, so I convert
				// the raw celsius reading here depending on the value of sensorTemperatureUnit
				if ($securityProbe && ($query_field == 'sensorProbeTempDegreeRaw')) {
					// Get the temperature unit for the specified sensor
					$temp_unit = cacti_snmp_get($hostname, $snmp_community,
						$oids['sensorTemperatureUnit'] . ".$arr_index[$i]", $snmp_version, $snmp_auth_username,
						$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
						$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);

					// If the sensor is configured for Fahrenheit, do the conversion
					if ($temp_unit == 'F') {
						// Temperature is always reported in tenths degrees. Divide by 10 first. Then multiply by 9/5.
						// Then add 32. Then multiply by 10 to re-convert to tenths degrees. Use substr() to strip off
						// any trailing digits from the final value, we should always return a 3 digit value.
						print $arr_index[$i] . $xml_delimiter . substr((string) (((($arr[$i] / 10) * (9 / 5)) + 32) * 10), 0, 3) . "\n";
					}

					// Otherwise if the sensor is not set for "F", just report the value.
					else {
						print $arr_index[$i] . $xml_delimiter . $arr[$i] . "\n";
					}
				}
				// This is a normal sensorProbe table and we just output the raw value as it's reported.
				else {
					print $arr_index[$i] . $xml_delimiter . $arr[$i] . "\n";
				}
			}
		}
	}
}

//
// -------------------------------------------------------------------------
// script MUST respond to get requests
//       the command for this is defined within the XML file as
//       <arg_get>get</arg_get>
//       you may replace the string "get" both in the XML and here
// -------------------------------------------------------------------------
//       php -q <script> <parms> get <function> <index>
// where <function> is a parameter that tells this script,
// which target value should be retrieved
// and   <index>    is the index that should be queried
// e.g. in case of interfaces, <function> = ifdescription
//                             <index>    = 1
//      it has to respond with
//      the description of the interface for interface #1
// -------------------------------------------------------------------------
elseif ($cmd == 'get' && isset($query_field) && isset($query_index)) {
	$value = (cacti_snmp_get($hostname, $snmp_community,
		$oids[$query_field] . ".$query_index", $snmp_version, $snmp_auth_username,
		$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
		$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));

	// The securityProbe still reports raw temperature values in celsius regardless of whether the
	// sensor is configured for fahrenheit or not. The sensorProbe switches the raw reading. It
	// wasn't logical to create different graph templates to handle each scenario, so I convert
	// the raw celsius reading here based upon the value of sensorTemperatureUnit
	if ($securityProbe && ($query_field == 'sensorProbeTempDegreeRaw')) {
		// Get the temperature unit for the specified sensor
		$temp_unit = cacti_snmp_get($hostname, $snmp_community,
			$oids['sensorTemperatureUnit'] . ".$query_index", $snmp_version, $snmp_auth_username,
			$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
			$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);

		// If the sensor is configured for Fahrenheit, do the conversion
		if ($temp_unit == 'F') {
			// Temperature is always reported in tenths degrees. Divide by 10 first. Then multiply by 9/5.
			// Then add 32. Then multiply by 10 to re-convert to tenths degrees. Use substr() to strip off
			// any trailing digits from the final value, we should always return a 3 digit value.
			print substr((string) ((((floatval($value) / 10) * (9 / 5)) + 32) * 10), 0, 3);
		}
		// Otherwise if the sensor is not set for "F", just report the value.
		else {
			print $value;
		}
	}
	// This is a normal sensorProbe table and we just output the raw value as it's reported.
	else {
		print $value;
	}
}

// -------------------------------------------------------------------------
// -------------------------------------------------------------------------
else {
	print "Invalid use of script query, required parameters:\n\n";
	print "    <hostname> <cmd>\n";
}

function reindex(array $arr) : array {
	$return_arr = [];

	for ($i = 0; ($i < sizeof($arr)); $i++) {
		$return_arr[$i] = $arr[$i]['value'];
	}

	return $return_arr;
}

?>
