<?php

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);
	print call_user_func_array(ss_nimble_alletra_volumes(...), $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}

function ss_nimble_alletra_volumes(int $host_id = 0, string $cmd = 'index', string $what = '', string $index = '') : mixed {
	$oids = [
		'index' => '.1.3.6.1.4.1.37447.1.2.1.2',
		'name'  => '.1.3.6.1.4.1.37447.1.2.1.3',
		'size'  => [
			'low'  => '.1.3.6.1.4.1.37447.1.2.1.4',
			'high' => '.1.3.6.1.4.1.37447.1.2.1.5'
		],
		'used' => [
			'low'  => '.1.3.6.1.4.1.37447.1.2.1.6',
			'high' => '.1.3.6.1.4.1.37447.1.2.1.7'
		],
		'data' => [
			'low'  => '.1.3.6.1.4.1.37447.1.2.1.52',
			'high' => '.1.3.6.1.4.1.37447.1.2.1.53'
		],
		'snapshot' => [
			'low'  => '.1.3.6.1.4.1.37447.1.2.1.54',
			'high' => '.1.3.6.1.4.1.37447.1.2.1.55'
		]
	];

	if ($host_id <= 0) {
		return 'U';
	}

	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$host_id]);

	if ($cmd == 'index') {
		$return_arr  = cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			$oids['index'],
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['snmp_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		foreach ($return_arr as $value) {
			$parts = explode('.', (string) $value['oid']);
			$count = cacti_sizeof($parts);

			$index = $parts[$count - 1];
			print $index . PHP_EOL;
		}
	} elseif ($cmd == 'num_index') {
		print cacti_sizeof(cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			$oids['index'],
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['snmp_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']));
	} elseif ($cmd == 'query') {
		if ($what == 'index' || $what == 'name') {
			$return_arr  = cacti_snmp_walk($host['hostname'],
				$host['snmp_community'],
				$oids[$what],
				$host['snmp_version'],
				$host['snmp_username'],
				$host['snmp_password'],
				$host['snmp_auth_protocol'],
				$host['snmp_priv_passphrase'],
				$host['snmp_priv_protocol'],
				$host['snmp_context'],
				$host['snmp_port'],
				$host['snmp_timeout'],
				$host['snmp_retries'],
				SNMP_POLLER,
				$host['snmp_engine_id']);

			foreach ($return_arr as $value) {
				$parts = explode('.', (string) $value['oid']);
				$count = cacti_sizeof($parts);

				$index = $parts[$count - 1];

				print $index . ':' . $value['value'] . PHP_EOL;
			}
		} else {
			$return_arr_low  = cacti_snmp_walk($host['hostname'],
				$host['snmp_community'],
				$oids[$what]['low'],
				$host['snmp_version'],
				$host['snmp_username'],
				$host['snmp_password'],
				$host['snmp_auth_protocol'],
				$host['snmp_priv_passphrase'],
				$host['snmp_priv_protocol'],
				$host['snmp_context'],
				$host['snmp_port'],
				$host['snmp_timeout'],
				$host['snmp_retries'],
				SNMP_POLLER,
				$host['snmp_engine_id']);

			$return_arr_high  = cacti_snmp_walk($host['hostname'],
				$host['snmp_community'],
				$oids[$what]['high'],
				$host['snmp_version'],
				$host['snmp_username'],
				$host['snmp_password'],
				$host['snmp_auth_protocol'],
				$host['snmp_priv_passphrase'],
				$host['snmp_priv_protocol'],
				$host['snmp_context'],
				$host['snmp_port'],
				$host['snmp_timeout'],
				$host['snmp_retries'],
				SNMP_POLLER,
				$host['snmp_engine_id']);

			$low  = [];
			$high = [];

			foreach ($return_arr_low as $key=>$value) {
				$parts       = explode('.', (string) $value['oid']);
				$count       = cacti_sizeof($parts);
				$index       = $parts[$count - 1];
				$low[$index] = str_pad(decbin($value['value']), 32, '0', STR_PAD_LEFT);
			}

			foreach ($return_arr_high as $key=>$value) {
				$parts        = explode('.', (string) $value['oid']);
				$count        = cacti_sizeof($parts);
				$index        = $parts[$count - 1];
				$high[$index] = str_pad(decbin($value['value']), 32, '0', STR_PAD_LEFT);
			}

			foreach ($low as $key => $value) {
				$long = $high[$key] ?? '' . $value;

				if ($what == 'data' || $what == 'snapshot') {
					print $key . ':' . bindec($long) . PHP_EOL;
				} else {
					print $key . ':' . bindec($long) * 1000000 . PHP_EOL;
				}
			}
		}
	} elseif ($cmd == 'get') {
		if ($what == 'index' || $what == 'name') {
			$return_val = cacti_snmp_get($host['hostname'],
				$host['snmp_community'],
				$oids[$what] . '.' . $index,
				$host['snmp_version'],
				$host['snmp_username'],
				$host['snmp_password'],
				$host['snmp_auth_protocol'],
				$host['snmp_priv_passphrase'],
				$host['snmp_priv_protocol'],
				$host['snmp_context'],
				$host['snmp_port'],
				$host['snmp_timeout'],
				$host['snmp_retries'],
				SNMP_POLLER,
				$host['snmp_engine_id']);

			return $return_val;
		} else {
			$return_val_low = cacti_snmp_get($host['hostname'],
				$host['snmp_community'],
				$oids[$what]['low'] . '.' . $index,
				$host['snmp_version'],
				$host['snmp_username'],
				$host['snmp_password'],
				$host['snmp_auth_protocol'],
				$host['snmp_priv_passphrase'],
				$host['snmp_priv_protocol'],
				$host['snmp_context'],
				$host['snmp_port'],
				$host['snmp_timeout'],
				$host['snmp_retries'],
				SNMP_POLLER,
				$host['snmp_engine_id']);

			$return_val_high = cacti_snmp_get($host['hostname'],
				$host['snmp_community'],
				$oids[$what]['high'] . '.' . $index,
				$host['snmp_version'],
				$host['snmp_username'],
				$host['snmp_password'],
				$host['snmp_auth_protocol'],
				$host['snmp_priv_passphrase'],
				$host['snmp_priv_protocol'],
				$host['snmp_context'],
				$host['snmp_port'],
				$host['snmp_timeout'],
				$host['snmp_retries'],
				SNMP_POLLER,
				$host['snmp_engine_id']);

			$low  = str_pad(decbin(intval($return_val_low)), 32, '0', STR_PAD_LEFT);
			$high = str_pad(decbin(intval($return_val_high)), 32, '0', STR_PAD_LEFT);

			if ($what == 'data' || $what == 'snapshot') {
				return bindec($high . $low);
			} else {
				return bindec($high . $low) * 1000000;
			}
		}
	}

	return null;
}
