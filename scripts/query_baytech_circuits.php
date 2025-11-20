<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

# deactivate http headers
$no_http_headers = true;
# include some cacti files for ease of use
include(__DIR__ . '/../include/global.php');
include(__DIR__ . '/../lib/snmp.php');

# define all OIDs we need for further processing
$oids = [
	'index'                => '.1.3.6.1.4.1.4779.1.3.5.2.1.1',
	'rpcName'              => '.1.3.6.1.4.1.4779.1.3.5.2.1.2',
	'cktCurrent1'          => '.1.3.6.1.4.1.4779.1.3.5.9.1.4.X.1',
	'cktCurrent2'          => '.1.3.6.1.4.1.4779.1.3.5.9.1.4.X.2',
	'cktCurrent3'          => '.1.3.6.1.4.1.4779.1.3.5.9.1.4.X.3',
	'cktMaxCurrent1'       => '.1.3.6.1.4.1.4779.1.3.5.9.1.5.X.1',
	'cktMaxCurrent2'       => '.1.3.6.1.4.1.4779.1.3.5.9.1.5.X.2',
	'cktMaxCurrent3'       => '.1.3.6.1.4.1.4779.1.3.5.9.1.5.X.3',
	'cktVoltage1'          => '.1.3.6.1.4.1.4779.1.3.5.9.1.6.X.1',
	'cktVoltage2'          => '.1.3.6.1.4.1.4779.1.3.5.9.1.6.X.2',
	'cktVoltage3'          => '.1.3.6.1.4.1.4779.1.3.5.9.1.6.X.3',
	'cktPower1'            => '.1.3.6.1.4.1.4779.1.3.5.9.1.7.X.1',
	'cktPower2'            => '.1.3.6.1.4.1.4779.1.3.5.9.1.7.X.2',
	'cktPower3'            => '.1.3.6.1.4.1.4779.1.3.5.9.1.7.X.3',
	'cktVA1'               => '.1.3.6.1.4.1.4779.1.3.5.9.1.8.X.1',
	'cktVA2'               => '.1.3.6.1.4.1.4779.1.3.5.9.1.8.X.2',
	'cktVA3'               => '.1.3.6.1.4.1.4779.1.3.5.9.1.8.X.3',
	'cktCurrentAlmThresh1' => '.1.3.6.1.4.1.4779.1.3.5.9.1.9.X.1',
	'cktCurrentAlmThresh2' => '.1.3.6.1.4.1.4779.1.3.5.9.1.9.X.2',
	'cktCurrentAlmThresh3' => '.1.3.6.1.4.1.4779.1.3.5.9.1.9.X.3',
	'cktPowerFactor1'      => '.1.3.6.1.4.1.4779.1.3.5.9.1.10.X.1',
	'cktPowerFactor2'      => '.1.3.6.1.4.1.4779.1.3.5.9.1.10.X.2',
	'cktPowerFactor3'      => '.1.3.6.1.4.1.4779.1.3.5.9.1.10.X.3',
	'cktPFAlmThresh1'      => '.1.3.6.1.4.1.4779.1.3.5.9.1.11.X.1',
	'cktPFAlmThresh2'      => '.1.3.6.1.4.1.4779.1.3.5.9.1.11.X.2',
	'cktPFAlmThresh3'      => '.1.3.6.1.4.1.4779.1.3.5.9.1.11.X.3',
	'cktPFPowerThresh1'    => '.1.3.6.1.4.1.4779.1.3.5.9.1.12.X.1',
	'cktPFPowerThresh2'    => '.1.3.6.1.4.1.4779.1.3.5.9.1.12.X.2',
	'cktPFPowerThresh3'    => '.1.3.6.1.4.1.4779.1.3.5.9.1.12.X.3',
];

$xml_delimiter =  '!';

# all required input parms
$hostname        = $_SERVER['argv'][1]; # hostname/IP@
#$cmd            = $_SERVER["argv"][2];
$snmp_community  = $_SERVER['argv'][2];
$snmp_version    = $_SERVER['argv'][3];
$snmp_port       = $_SERVER['argv'][4];
$snmp_timeout    = $_SERVER['argv'][5];
$max_oids        = $_SERVER['argv'][6];
# required for SNMP V3
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

# get number of snmp retries from global settings
$snmp_retries   = read_config_option('snmp_retries');

# -------------------------------------------------------------------------
# main code starts here
# -------------------------------------------------------------------------

# -------------------------------------------------------------------------
# script MUST respond to index queries
#       the command for this is defined within the XML file as
#       <arg_index>index</arg_index>
#       you may replace the string "index" both in the XML and here
# -------------------------------------------------------------------------
#       php -q <script> <parms> index
# will list all indices of the target values
# e.g. in case of interfaces
#      it has to respond with the list of interface indices
# -------------------------------------------------------------------------
if ($cmd == 'index') {
	# retrieve all indices from target
	$return_arr = reindex(cacti_snmp_walk($hostname, $snmp_community,
		$oids['index'], $snmp_version, $snmp_auth_username,
		$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
		$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));

	# and print each index as a separate line
	for ($i = 0; ($i < sizeof($return_arr)); $i++) {
		print $return_arr[$i] . "\n";
	}
}

#
# -------------------------------------------------------------------------
# script MUST respond to query requests
#       the command for this is defined within the XML file as
#       <arg_query>query</arg_query>
#       you may replace the string "query" both in the XML and here
# -------------------------------------------------------------------------
#       php -q <script> <parms> query <function>
# where <function> is a parameter that tells this script,
# which target value should be retrieved
# e.g. in case of interfaces, <function> = ifdescription
#      it has to respond with the list of
#      interface indices along with the description of the interface
# -------------------------------------------------------------------------
elseif ($cmd == 'query' && isset($query_field)) {
	$arr = [];

	$arr_index = reindex(cacti_snmp_walk($hostname, $snmp_community,
		$oids['index'], $snmp_version, $snmp_auth_username,
		$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
		$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));

	if ($query_field == 'rpcName') {
		$arr = reindex(cacti_snmp_walk($hostname, $snmp_community,
			$oids[$query_field], $snmp_version, $snmp_auth_username,
			$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
			$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));
	} elseif ($query_field == 'index') {
		$arr = reindex(cacti_snmp_walk($hostname, $snmp_community,
			$oids[$query_field], $snmp_version, $snmp_auth_username,
			$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
			$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));
	} else {
		for ($i = 0; ($i < sizeof($arr_index)); $i++) {
			$result = cacti_snmp_get($hostname, $snmp_community,
				str_replace('X', "$arr_index[$i]", $oids[$query_field]), $snmp_version, $snmp_auth_username,
				$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
				$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER);

			# Cannot perform CDEF operations on input type data query items, so we perform the necessary
			# calculation as we collect the data.
			if (preg_match('/^cktMaxCurrent/',$query_field) || preg_match('/^cktCurrentAlmThresh/',$query_field)) {
				$arr[$i] = ($result / 10);
			} else {
				$arr[$i] = $result;
			}
		}
	}

	for ($i = 0; ($i < sizeof($arr_index)); $i++) {
		print $arr_index[$i] . $xml_delimiter . $arr[$i] . "\n";
	}
}

#
# -------------------------------------------------------------------------
# script MUST respond to get requests
#       the command for this is defined within the XML file as
#       <arg_get>get</arg_get>
#       you may replace the string "get" both in the XML and here
# -------------------------------------------------------------------------
#       php -q <script> <parms> get <function> <index>
# where <function> is a parameter that tells this script,
# which target value should be retrieved
# and   <index>    is the index that should be queried
# e.g. in case of interfaces, <function> = ifdescription
#                             <index>    = 1
#      it has to respond with
#      the description of the interface for interface #1
# -------------------------------------------------------------------------
elseif ($cmd == 'get' && isset($query_field) && isset($query_index)) {
	if ($query_field == 'rpcName') {
		print(cacti_snmp_get($hostname, $snmp_community,
			$oids[$query_field] . ".$query_index", $snmp_version, $snmp_auth_username,
			$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
			$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));
	} elseif ($query_field == 'index') {
		print(cacti_snmp_get($hostname, $snmp_community,
			$oids[$query_field] . ".$query_index", $snmp_version, $snmp_auth_username,
			$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
			$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));
	} else {
		$result =  (cacti_snmp_get($hostname, $snmp_community,
			str_replace('X', "$query_index", $oids[$query_field]), $snmp_version, $snmp_auth_username,
			$snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol,
			$snmp_context, $snmp_port, $snmp_timeout, $snmp_retries, $max_oids, SNMP_POLLER));

		# Cannot perform CDEF operations on input type data query items, so we perform the necessary
		# calculation as we collect the data.
		if (preg_match('/^cktMaxCurrent/',$query_field) || preg_match('/^cktCurrentAlmThresh/',$query_field)) {
			print($result / 10);
		} else {
			print $result;
		}
	}
}

# -------------------------------------------------------------------------
# -------------------------------------------------------------------------
else {
	print "Invalid use of script query, required parameters:\n\n";
	print "    <hostname> <cmd>\n";
}

function reindex($arr) {
	$return_arr = [];

	for ($i = 0; ($i < sizeof($arr)); $i++) {
		$return_arr[$i] = $arr[$i]['value'];
	}

	return $return_arr;
}
