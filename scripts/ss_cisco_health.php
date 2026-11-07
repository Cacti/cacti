<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
if (!isset($called_by_script_server)) {
	require_once __DIR__ . '/../include/cli_check.php';
}
require_once __DIR__ . '/../lib/snmp.php';

function cisco_health_number(mixed $value) : int|float|null {
	return is_numeric($value) ? 0 + $value : null;
}
function cisco_health_temperature(mixed $value, mixed $scale, mixed $precision, mixed $status, bool $cisco = false) : string {
	if ($status != 1 || !is_numeric($value) || !is_numeric($scale) || !is_numeric($precision)
		|| abs($value + 0) >= 1000000000 || $scale < 1 || $scale > 17 || $precision < -8 || $precision > 9) {
		return 'U';
	}
	// ENTITY-SENSOR-MIB: negative precision denotes accuracy, not decimal places.
	$exponent = $cisco && $scale == 14 ? 18 : ($cisco && $scale == 15 ? 15 : ($scale - 9) * 3);
	$result   = $value * pow(10, $exponent - max(0, $precision));

	return is_finite($result) ? (string)$result : 'U';
}
function cisco_health_qfp_factor(int|float|null $unit) : ?int {
	// Unit 4 is bytes on the verified IOS XE C1100 implementations.
	// Unknown unit codes are not guessed. Legacy MIBs without this column use kB.
	if ($unit === null) {
		return 1024;
	}

	return $unit == 4 ? 1 : null;
}
function ss_cisco_health(int|string $host_id = 0, string $kind = '', string $cmd = 'index', string $field = '', string $index = '') : string {
	if (!ctype_digit((string)$host_id) || !in_array($kind, ['memory', 'qfp_memory', 'temperature'], true)) {
		return 'U';
	}
	$h = db_fetch_row_prepared('SELECT hostname, snmp_community, snmp_version,
		snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase,
		snmp_priv_protocol, snmp_context, snmp_port, snmp_timeout, ping_retries,
		snmp_engine_id, bulk_walk_size
		FROM host WHERE id = ?', [(int)$host_id]);

	if (!is_array($h) || !$h) {
		return 'U';
	}
	$args = [$h['hostname'], $h['snmp_community'], '', $h['snmp_version'], $h['snmp_username'],
		$h['snmp_password'], $h['snmp_auth_protocol'], $h['snmp_priv_passphrase'], $h['snmp_priv_protocol'],
		$h['snmp_context'], $h['snmp_port'], $h['snmp_timeout'], $h['ping_retries']];
	$engine   = $h['snmp_engine_id'] ?? '';
	$walkSize = max(1, (int)($h['bulk_walk_size'] ?? 10));
	$get      = function ($oid) use ($args, $engine) {
		static $values = [];

		if (!array_key_exists($oid, $values)) {
			$a            = $args;
			$a[2]         = $oid;
			$a[]          = SNMP_POLLER;
			$a[]          = $engine;
			$values[$oid] = cacti_snmp_get(...$a);
		}

		return $values[$oid];
	};
	$walk = function ($oid) use ($args, $engine, $walkSize) {
		$a    = $args;
		$a[2] = $oid;
		$a[]  = $walkSize;
		$a[]  = SNMP_POLLER;
		$a[]  = $engine;
		$rows = cacti_snmp_walk(...$a);
		$out  = [];

		foreach ((array)$rows as $row) {
			$key    = ltrim($row['oid'] ?? '', '.');
			$prefix = ltrim($oid, '.') . '.';

			if (strpos($key, $prefix) === 0) {
				$out[substr($key, strlen($prefix))] = $row['value'];
			}
		}

		return $out;
	};

	return cisco_health_query($kind, $cmd, $field, $index, $get, $walk);
}

/** Execute one query using injectable SNMP readers; no database state is required here. */
function cisco_health_query(string $kind, string $cmd, string $field, string $index, callable $get, callable $walk) : string {
	if (!in_array($kind, ['memory', 'qfp_memory', 'temperature'], true)) {
		return 'U';
	}
	$mem       = '.1.3.6.1.4.1.9.9.221.1.1.1.1.';
	$qfp       = '.1.3.6.1.4.1.9.9.715.1.1.7.1.';
	$temp      = '.1.3.6.1.2.1.99.1.1.1.';
	$legacyMem = '.1.3.6.1.4.1.9.9.48.1.1.1.';
	$ciscoTemp = '.1.3.6.1.4.1.9.9.91.1.1.1.1.';
	$names     = '.1.3.6.1.2.1.47.1.1.1.1.7.';

	if ($cmd === 'get') {
		if (!preg_match('/^[0-9]+(?:\.[0-9]+)*$/D', $index)) {
			return 'U';
		}

		if ($kind === 'temperature') {
			if ($field !== 'temperature') {
				return 'U';
			}
			$type = $get($temp . '1.' . $index);

			// Try the Cisco table when no standard sensor type is readable.
			if (cisco_health_number($type) === null) {
				$temp = $ciscoTemp;
				$type = $get($temp . '1.' . $index);
			}

			if (cisco_health_number($type)!=8) {
				return 'U';
			}

			return cisco_health_temperature(cisco_health_number($get($temp . '4.' . $index)),
				cisco_health_number($get($temp . '2.' . $index)), cisco_health_number($get($temp . '3.' . $index)),
				cisco_health_number($get($temp . '5.' . $index)), $temp === $ciscoTemp);
		}

		if (!in_array($field, ['used', 'free'], true)) {
			return 'U';
		}

		if ($kind === 'memory') {
			// Legacy pool indexes have one component; enhanced indexes have two.
			if (ctype_digit((string)$index)) {
				if (cisco_health_number($get($legacyMem . '4.' . $index))!=1) {
					return 'U';
				}
				$v = cisco_health_number($get($legacyMem . ($field === 'used' ? '5.' : '6.') . $index));

				return $v !== null && $v >= 0 ? (string)$v : 'U';
			}

			if (cisco_health_number($get($mem . '6.' . $index))!=1) {
				return 'U';
			}
			$v = cisco_health_number($get($mem . ($field === 'used' ? '18.' : '20.') . $index));

			return $v !== null && $v >= 0 ? (string)$v : 'U';
		}
		$rawUnit = $get($qfp . '16.' . $index);
		$unit    = cisco_health_number($rawUnit);

		// A failed request must not silently change units.
		if ($unit === null && stripos((string)$rawUnit,'No Such') === false) {
			return 'U';
		}
		$factor = cisco_health_qfp_factor($unit);
		$v      = cisco_health_number($get($qfp . ($field === 'used' ? '11.' : '13.') . $index));

		return $factor !== null && $v !== null && $v >= 0 ? (string)($v * $factor) : 'U';
	}
	$labels = [];

	if ($kind === 'memory') {
		$enhanced = $walk($mem . '3');

		foreach ($enhanced as $idx=>$name) {
			$entity       = explode('.', $idx)[0];
			$entityName   = $get($names . $entity);
			$labels[$idx] = trim($entityName . ' / ' . $name, ' /');
		}

		// Do not merge two unrelated index spaces: they may describe the same pools.
		if (!$enhanced) {
			foreach ($walk($legacyMem . '2') as $idx=>$name) {
				if (ctype_digit((string)$idx)) {
					$labels[$idx] = $name;
				}
			}
		}
	} elseif ($kind === 'qfp_memory') {
		foreach ($walk($qfp . '9') as $idx=>$total) {
			if (!is_numeric($total) || $total <= 0) {
				continue;
			}
			$parts        = explode('.', $idx);
			$type         = end($parts);
			$resource     = $type == 1 ? 'DRAM' : 'Resource ' . $type;
			$labels[$idx] = $get($names . $parts[0]) . ' / ' . $resource;
		}
	} else {
		// Both sensor tables use entPhysicalIndex, so union by index avoids duplicates.
		$types = $walk($temp . '1') + $walk($ciscoTemp . '1');

		foreach ($types as $idx=>$type) {
			if ((int)$type === 8) {
				$name         = $get($names . $idx);
				$labels[$idx] = $name ?: 'Sensor ' . $idx;
			}
		}
	}

	if ($cmd === 'num_indexes') {
		return (string)count($labels);
	}
	$out = [];

	foreach ($labels as $idx=>$name) {
		if ($cmd === 'index') {
			$out[] = $idx;
		} elseif ($cmd === 'query' && in_array($field,['index', 'name'],true)) {
			$out[] = $idx . '!' . ($field === 'index' ? $idx : str_replace(["\r", "\n", '!'], ' ', $name));
		}
	}

	return implode("\n", $out) . (count($out) ? "\n" : '');
}

if (!isset($called_by_script_server)) {
	$args = $_SERVER['argv'];
	array_shift($args);
	print ss_cisco_health(...$args);
}
