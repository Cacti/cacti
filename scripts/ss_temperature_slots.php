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

/** Combined temperature query with stable sensor membership. */
function temperature_slot_binding(array $ids): string
{
	$ids = array_map('strval', $ids);
	foreach ($ids as $id) {
		if (!preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
			throw new InvalidArgumentException('Invalid sensor index');
		}
	}
	if (count(array_unique($ids)) !== count($ids) || count($ids) > 8 || !$ids) {
		throw new InvalidArgumentException('Requires 1–8 unique sensors; never truncate');
	}
	usort($ids, fn ($a, $b) => (int)$a <=> (int)$b);
	return 't' . implode('_', array_pad($ids, 8, '0'));
}
function temperature_slot_ids(string $binding): array
{
	if (!preg_match('/^t([0-9]{1,10}_){7}[0-9]{1,10}$/D', $binding)) {
		throw new InvalidArgumentException('Invalid binding');
	}
	$ids = explode('_', substr($binding, 1));
	if (temperature_slot_binding(array_values(array_filter($ids, fn ($v) => $v !== '0'))) !== $binding) {
		throw new InvalidArgumentException('Noncanonical binding');
	}
	return $ids;
}
function temperature_slot_value(string $binding, int $slot, callable $read): string
{
	if ($slot < 1 || $slot > 8) {
		return 'U';
	}
	$ids = temperature_slot_ids($binding);
	if ($ids[$slot - 1] === '0') {
		return 'U';
	}
	$value = $read($ids[$slot - 1]);
	return is_numeric($value) && is_finite((float)$value) && $value >= -273.15 ? (string)$value : 'U';
}
function temperature_slot_discover(array $current, array $saved): array
{
	// Saved graph identities stay discoverable, including during SNMP outages.
	if ($saved) {
		foreach ($saved as $binding) {
			temperature_slot_ids($binding);
		}
		return array_values(array_unique($saved));
	}
	return $current ? [temperature_slot_binding($current)] : [];
}
function temperature_slot_existing($host_id): array
{
	$rows = db_fetch_assoc_prepared(
		'SELECT DISTINCT did.value AS binding
        FROM data_local AS dl
        JOIN snmp_query AS sq ON sq.id = dl.snmp_query_id
        JOIN data_template_data AS dtd ON dtd.local_data_id = dl.id
        JOIN data_input_data AS did ON did.data_template_data_id = dtd.id
        JOIN data_input_fields AS dif ON dif.id = did.data_input_field_id
        WHERE dl.host_id = ? AND sq.hash = ? AND dif.type_code = ? AND did.value LIKE ?',
		[(int)$host_id, '59bb7c19c1f17052ccb33a1937e40155', 'index_value', 't%']
	);
	return array_column($rows, 'binding');
}
function ss_temperature_slots($host_id, $cmd = 'index', $field = '', $index = ''): string
{
	global $called_by_script_server, $config;
	if (!ctype_digit((string)$host_id)) {
		return $cmd === 'get' ? 'U' : '';
	}
	require_once __DIR__ . '/ss_cisco_health.php';
	try {
		if ($cmd === 'get' && preg_match('/^temperature([1-8])$/D', $field, $m)) {
			return temperature_slot_value($index, (int)$m[1], fn ($id) => ss_cisco_health($host_id, 'temperature', 'get', 'temperature', $id));
		}
		$saved = temperature_slot_existing($host_id);
		// Validate stored membership even when no live discovery is needed.
		$bindings = temperature_slot_discover([], $saved);
		$current = [];
		if (!$saved || ($cmd === 'query' && $field === 'newSensors')) {
			$raw = trim(ss_cisco_health($host_id, 'temperature', 'index'));
			$current = $raw === '' ? [] : preg_split('/\s+/', $raw);
			$bindings = temperature_slot_discover($current, $saved);
		}
		if ($cmd === 'index') {
			return $bindings ? implode("\n", $bindings) . "\n" : '';
		}
		if ($cmd === 'num_indexes') {
			return (string)count($bindings);
		}
		if ($cmd !== 'query') {
			return '';
		}
		$out = '';
		foreach ($bindings as $binding) {
			$ids = temperature_slot_ids($binding);
			$occupied = array_values(array_filter($ids, fn ($id) => $id !== '0'));
			if ($field === 'index') {
				$value = $binding;
			} elseif ($field === 'name') {
				$value = 'All sensors';
			} elseif ($field === 'sensorCount') {
				$value = (string)count($occupied);
			} elseif ($field === 'newSensors') {
				$value = (string)count(array_diff($current, $occupied));
			} elseif (preg_match('/^sensor([1-8])Name$/D', $field, $m)) {
				$id = $ids[(int)$m[1] - 1];
				$value = $id === '0' ? '' : ss_cisco_health($host_id, 'temperature', 'get', 'name', $id);
				if ($value === 'U') {
					$value = 'Sensor ' . $id . ' (unavailable)';
				}
			} else {
				return '';
			}
			$out .= $binding . '!' . str_replace(["!", "\r", "\n"], ' ', $value) . "\n";
		}
		return $out;
	} catch (InvalidArgumentException $e) {
		return $cmd === 'get' ? 'U' : ($cmd === 'num_indexes' ? '0' : '');
	}
}

// Cacti runs discovery as a CLI script even for Script Server queries.
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	require_once __DIR__ . '/../include/cli_check.php';
	$called_by_script_server = true;
	$args = $_SERVER['argv'];
	echo ss_temperature_slots((int)($args[1] ?? 0), $args[2] ?? 'index', $args[3] ?? '', $args[4] ?? '');
}
