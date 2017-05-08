<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

if (isset($config)) {
	include_once(dirname(__FILE__) . '/../lib/snmp.php');
}

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/global.php');
	include_once(dirname(__FILE__) . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_net_snmp_disk_io', $_SERVER['argv']);
}

function ss_net_snmp_disk_io($host_id_or_hostname) {
	global $environ, $poller_id, $config;

	if (!is_numeric($host_id_or_hostname)) {
		$host_id = db_fetch_cell_prepared('SELECT id FROM host WHERE hostname = ?', array($host_id_or_hostname));
	} else {
		$host_id = $host_id_or_hostname;
	}

	if ($config['cacti_server_os'] == 'win32') {
		$tmpdir = getenv('TEMP');
	} else {
		$tmpdir = '/tmp';
	}

	if ($environ != 'realtime') {
		$tmpdir = $tmpdir . '/cacti/net-snmp-devio';
		$tmpfile = $host_id . '_io';
	} else {
		$tmpdir = $tmpdir . '/cacti/net-snmp-devio';
		$tmpfile = $host_id . '_' . $poller_id . '_io_rt';
	}

	if (!is_dir($tmpdir)) {
		mkdir($tmpdir, 0777, true);
	}

	$previous = array();
	$found    = false;

	if (file_exists("$tmpdir/$tmpfile")) {
		$previous = json_decode(file_get_contents("$tmpdir/$tmpfile"), true);
		$found = true;
	}

	$indexes = array();
	$host    = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($host_id));

	$uptime  = cacti_snmp_get($host['hostname'], 
		$host['snmp_community'], 
		'.1.3.6.1.2.1.1.3.0',
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
		SNMP_POLLER,
		$host['snmp_engine_id']);

	$current['uptime'] = $uptime;

	$names  = cacti_snmp_walk($host['hostname'], 
		$host['snmp_community'], 
		'.1.3.6.1.4.1.2021.13.15.1.1.2',
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
		SNMP_POLLER, 
		$host['snmp_engine_id']);

	foreach($names as $measure) {
		if (substr($measure['value'],0,2) == 'sd') {
			if (is_numeric(substr(strrev($measure['value']),0,1))) {
				continue;
			}

			$parts = explode('.', $measure['oid']);
			$indexes[$parts[sizeof($parts)-1]] = $parts[sizeof($parts)-1];
		}
	}

	$reads = $writes = 0;

	if (sizeof($indexes)) {
		$iops = cacti_snmp_walk($host['hostname'], 
			$host['snmp_community'], 
			'.1.3.6.1.4.1.2021.13.15.1.1.5',
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
			SNMP_POLLER, 
			$host['snmp_engine_id']);

		foreach($iops as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[sizeof($parts)-1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$reads = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
					$reads = 'U';
				} elseif (!isset($previous["dr$index"])) {
					$reads = 'U';
				} elseif ($previous["dr$index"] > $measure['value']) {
					$reads += $measure['value'] + 4294967295 - $previous["dr$index"] - $previous["dr$index"];
				} else {
					$reads += $measure['value'] - $previous["dr$index"];
				}

				$current["dr$index"] = $measure['value'];
			}
		}

		$iops = cacti_snmp_walk($host['hostname'], 
			$host['snmp_community'], 
			'.1.3.6.1.4.1.2021.13.15.1.1.6',
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
			SNMP_POLLER, 
			$host['snmp_engine_id']);

		foreach($iops as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[sizeof($parts)-1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$writes = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
					$writes = 'U';
				} elseif (!isset($previous["dw$index"])) {
					$writes = 'U';
				} elseif ($previous["dw$index"] > $measure['value']) {
					$writes += $measure['value'] + 4294967295 - $previous["dw$index"] - $previous["dw$index"];
				} else {
					$writes += $measure['value'] - $previous["dw$index"];
				}

				$current["dw$index"] = $measure['value'];
			}
		}

		$data = "'" . json_encode($current) . "'";
		shell_exec("echo $data > $tmpdir/$tmpfile");
	}

	if ($found) {
		return "reads:$reads writes:$writes";
	} else {
		return 'reads:0 writes:0';
	}
}
