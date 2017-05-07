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

	print call_user_func_array('ss_net_snmp_disk_bytes', $_SERVER['argv']);
}

function ss_net_snmp_disk_bytes($host_id_or_hostname) {
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
		$tmpfile = $host_id . '_bytes';
	} else {
		$tmpdir = $tmpdir . '/cacti/net-snmp-devio';
		$tmpfile = $host_id . '_' . $poller_id . '_bytes_rt';
	}

	if (!is_dir($tmpdir)) {
		mkdir($tmpdir, 0777, true);
	}

	$found    = false;
	$previous = array();

	if (is_file("$tmpdir/$tmpfile")) {
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

	$bytesread = $byteswritten = 0;

	if (sizeof($indexes)) {
		$bytes = cacti_snmp_walk($host['hostname'], 
			$host['snmp_community'], 
			'.1.3.6.1.4.1.2021.13.15.1.1.12',
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

		foreach($bytes as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[sizeof($parts)-1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$bytesread = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
					$bytesread = 'U';
				} elseif (!isset($previous["br$index"])) {
					$bytesread = 'U';
				} elseif ($previous["br$index"] > $measure['value']) {
					$bytesread += $measure['value'] + 18446744073709551615 - $previous["br$index"] - $previous["br$index"];
				} else {
					$bytesread += $measure['value'] - $previous["br$index"];
				}

				$current["br$index"] = $measure['value'];
			}
		}

		$bytes = cacti_snmp_walk($host['hostname'], 
			$host['snmp_community'], 
			'.1.3.6.1.4.1.2021.13.15.1.1.13',
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

		foreach($bytes as $measure) {
			$parts = explode('.', $measure['oid']);
			$index = $parts[sizeof($parts)-1];

			if (array_key_exists($index, $indexes)) {
				if (!isset($previous['uptime'])) {
					$byteswritten = 'U';
				} elseif ($current['uptime'] < $previous['uptime']) {
					$byteswritten = 'U';
				} elseif (!isset($previous["bw$index"])) {
					$byteswritten = 'U';
				} elseif ($previous["bw$index"] > $measure['value']) {
					$byteswritten += $measure['value'] + 18446744073709551615 - $previous["bw$index"] - $previous["bw$index"];
				} else {
					$byteswritten += $measure['value'] - $previous["bw$index"];
				}

				$current["bw$index"] = $measure['value'];
			}
		}

		$data = "'" . json_encode($current) . "'";
		shell_exec("echo $data > $tmpdir/$tmpfile");
	}

	if ($found) {
		return "bytesread:$bytesread byteswritten:$byteswritten";
	} else {
		return 'bytesread:0 byteswritten:0';
	}
}
