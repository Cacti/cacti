<?php

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_aruba_oscx_6x00', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}

function ss_aruba_oscx_6x00(int $host_id = 0, string $object = 'fan', string $cmd = 'index', string $what = '', string $index = '') : mixed {
	$state_table = [
		'notPresent'           => 1,
		'booting'              => 2,
		'ready'                => 3,
		'versionMismatch'      => 4,
		'communicationFailure' => 5,
		'inOtherFragment'      => 6
		];

	// object = fan, temp, ...
	$oids['fan'] = [
		'index' => '.1.3.6.1.4.1.47196.4.1.1.3.11.5.1.1.4',
		'name'  => '.1.3.6.1.4.1.47196.4.1.1.3.11.5.1.1.4',
		'state' => '.1.3.6.1.4.1.47196.4.1.1.3.11.5.1.1.5',
		'rpm'   => '.1.3.6.1.4.1.47196.4.1.1.3.11.5.1.1.8'
	];

	$oids['temp'] = [
		'index'  => '.1.3.6.1.4.1.47196.4.1.1.3.11.3.1.1.5',
		'name'   => '.1.3.6.1.4.1.47196.4.1.1.3.11.3.1.1.5',
		'state'  => '.1.3.6.1.4.1.47196.4.1.1.3.11.3.1.1.6',
		'actual' => '.1.3.6.1.4.1.47196.4.1.1.3.11.3.1.1.7'
	];

	$oids['power'] = [
		'index'  => '.1.3.6.1.4.1.47196.4.1.1.3.11.2.1.1.3',
		'name'   => '.1.3.6.1.4.1.47196.4.1.1.3.11.2.1.1.3',
		'state'  => '.1.3.6.1.4.1.47196.4.1.1.3.11.2.1.1.4',
		'actual' => '.1.3.6.1.4.1.47196.4.1.1.3.11.2.1.1.7'
	];

	$oids['vsf_standalone'] = [
		'index'  => '.1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.3',
		'name'   => '.1.3.6.1.2.1.1.5',
		// fake state = snmp answer = state ok
		'cpu'    => '.1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.3',
		'memory' => '.1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.4',
		'uptime' => '.1.3.6.1.2.1.1.3'
	];

	$oids['vsf_member'] = [
		'index'  => '.1.3.6.1.4.1.47196.4.1.1.3.10.0.3.1.7',
		'name'   => '.1.3.6.1.4.1.47196.4.1.1.3.10.0.3.1.7',
		'state'  => '.1.3.6.1.4.1.47196.4.1.1.3.15.1.2.1.3',
		'cpu'    => '.1.3.6.1.4.1.47196.4.1.1.3.15.1.2.1.9',
		'memory' => '.1.3.6.1.4.1.47196.4.1.1.3.15.1.2.1.10',
		'uptime' => '.1.3.6.1.4.1.47196.4.1.1.3.15.1.2.1.11'
	];

	$vsf_standalone = true;

	if ($host_id <= 0) {
		return 'U';
	}

	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$host_id]);

	// is it standalone switch or VSF stack member?
	if ($object == 'vsf') {
		if (cacti_snmp_walk($host['hostname'],
			$host['snmp_community'],
			$oids['vsf_standalone']['cpu'],
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
			SNMP_POLLER,
			$host['snmp_engine_id'])) { // it is standalone
			$oids['vsf'] = $oids['vsf_standalone'];
		} else {
			$oids['vsf']    = $oids['vsf_member'];
			$vsf_standalone = false;
		}
	}

	if ($cmd == 'index') {
		if ($vsf_standalone && $object == 'vsf') {
			print '1' . PHP_EOL;
		} else {
			$return_arr  = cacti_snmp_walk($host['hostname'],
				$host['snmp_community'],
				current($oids[$object]),
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
				SNMP_POLLER,
				$host['snmp_engine_id']);

			foreach ($return_arr as $key=>$value) {
				$parts = explode('.', $value['oid']);
				$count = cacti_sizeof($parts);

				if ($object == 'vsf') {
					$index = $parts[$count - 1];
				} elseif ($object == 'power') {
					$index = $parts[$count - 2] . '.' . $parts[$count - 1];
				} elseif ($object == 'fan') {
					$index = $parts[$count - 3] . '.' . $parts[$count - 2] . '.' . $parts[$count - 1];
				} elseif ($object == 'temp') {
					$index = $parts[$count - 4] . '.' . $parts[$count - 3] . '.' . $parts[$count - 2] . '.' . $parts[$count - 1];
				}
				print $index . PHP_EOL;
			}
		}
	} elseif ($cmd == 'num_index') {
		if ($vsf_standalone && $object == 'vsf') {
			print '1' . PHP_EOL;
		} else {
			print cacti_sizeof(cacti_snmp_walk($host['hostname'],
				$host['snmp_community'],
				current($oids[$object]),
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
				SNMP_POLLER,
				$host['snmp_engine_id']));
		}
	} elseif ($cmd == 'query') {
		// fake state for standalone switch
		if ($vsf_standalone && $object == 'vsf' && $what == 'state') {
			print '1:3' . PHP_EOL;
		} else {
			$return_arr  = cacti_snmp_walk($host['hostname'],
				$host['snmp_community'],
				$oids[$object][$what],
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
				SNMP_POLLER,
				$host['snmp_engine_id']);

			foreach ($return_arr as $key=>$value) {
				$parts = explode('.', $value['oid']);
				$count = cacti_sizeof($parts);

				if ($object == 'vsf') {
					if ($vsf_standalone) {
						$index = 1;
					} else {
						$index = $parts[$count - 1];
					}
				} elseif ($object == 'power') {
					$index = $parts[$count - 2] . '.' . $parts[$count - 1];
				} elseif ($object == 'fan') {
					$index = $parts[$count - 3] . '.' . $parts[$count - 2] . '.' . $parts[$count - 1];
				} elseif ($object == 'temp') {
					$index = $parts[$count - 4] . '.' . $parts[$count - 3] . '.' . $parts[$count - 2] . '.' . $parts[$count - 1];
				}

				// ok = fan, normal = tempsensor
				if ($what == 'state') {
					if ($object == 'fan' || $object == 'power') {
						print $index . ':' . (cacti_strtolower($value['value']) == 'ok' ? '1' : '0') . PHP_EOL;
					} elseif ($object == 'temp') {
						print $index . ':' . (cacti_strtolower($value['value']) == 'normal' ? '1' : '0') . PHP_EOL;
					} elseif ($object == 'vsf') {
						print $index . ':' . $state_table[$value['value']] . PHP_EOL;
					}
				} elseif ($what == 'actual') {
					if ($object == 'temp') {
						print $index . ':' . ($value['value'] / 1000) . PHP_EOL;
					} else {
						print $index . ':' . $value['value'] . PHP_EOL;
					}
				} else {
					print $index . ':' . $value['value'] . PHP_EOL;
				}
			}
		}
	} elseif ($cmd == 'get') {
		// fake for standalone device
		if ($vsf_standalone && $object == 'vsf') {
			if ($what == 'state') {
				return 3;
			} else {
				$return_val = cacti_snmp_getnext($host['hostname'],
					$host['snmp_community'],
					$oids[$object][$what],
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
					SNMP_POLLER,
					$host['snmp_engine_id']);
			}
		} else {
			$return_val = cacti_snmp_get($host['hostname'],
				$host['snmp_community'],
				$oids[$object][$what] . '.' . $index,
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
				SNMP_POLLER,
				$host['snmp_engine_id']);
		}

		if ($what == 'state') {
			if ($object == 'fan' || $object == 'power') {
				return (cacti_strtolower($return_val) == 'ok' ? '1' : '0');
			}

			if ($object == 'temp') {
				return (cacti_strtolower($return_val) == 'normal' ? '1' : '0');
			}

			if ($object == 'vsf') {
				return $state_table[$return_val];
			}
		} elseif ($what == 'actual') {
			if ($object == 'temp') {
				return (intval($return_val) / 1000);
			} else {
				return $return_val;
			}
		} else {
			return $return_val;
		}
	}

	return null;
}
