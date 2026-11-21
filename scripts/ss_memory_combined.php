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

/** Combined memory query with stable resource membership. */
function memory_combined_binding(array $ids): string
{
	$ids = array_map('strval', $ids);
	if (!$ids || count($ids) > 8 || count(array_unique($ids)) !== count($ids)) {
		throw new InvalidArgumentException('Requires 1–8 unique pools');
	}
	foreach ($ids as $id) {
		if (!preg_match('/^[1-9][0-9]{0,9}(?:\.[0-9]{1,10}){0,3}$/D', $id)) {
			throw new InvalidArgumentException('Invalid pool index');
		}
	}
	usort($ids, 'strnatcmp');
	$binding = 'm'.implode('_', $ids);
	if (strlen($binding) > 255) {
		throw new InvalidArgumentException('Pool binding exceeds Cacti index length');
	}
	return $binding;
}
function memory_combined_ids(string $binding): array
{
	if (!str_starts_with($binding, 'm')) {
		throw new InvalidArgumentException('Invalid pool binding');
	}
	$ids = explode('_', substr($binding, 1));
	if (memory_combined_binding($ids) !== $binding) {
		throw new InvalidArgumentException('Noncanonical pool binding');
	}
	return $ids;
}
function memory_combined_existing($host_id, string $kind): array
{
	$hash = ['memory' => 'ae7f035d28f4c236beae4941e740fce9','qfp_memory' => 'e1d221dcd1ef0f7df5cc79bdb835959d'][$kind];
	return array_column(db_fetch_assoc_prepared('SELECT DISTINCT did.value AS binding FROM data_local AS dl
      JOIN snmp_query AS sq ON sq.id=dl.snmp_query_id
      JOIN data_template_data AS dtd ON dtd.local_data_id=dl.id
      JOIN data_input_data AS did ON did.data_template_data_id=dtd.id
      JOIN data_input_fields AS dif ON dif.id=did.data_input_field_id
      WHERE dl.host_id=? AND sq.hash=? AND dif.type_code=? AND did.value LIKE ?', [(int)$host_id,$hash,'index_value','m%']), 'binding');
}
function memory_combined_value(string $binding, int $slot, string $metric, callable $read): string
{
	if ($slot < 1 || $slot > 8 || !in_array($metric, ['used','free'], true)) {
		return 'U';
	}
	$ids = memory_combined_ids($binding);
	if (!isset($ids[$slot - 1])) {
		return 'U';
	}
	$v = $read($ids[$slot - 1], $metric);
	return is_numeric($v) && is_finite((float)$v) && $v >= 0 ? (string)$v : 'U';
}
function ss_memory_combined($host_id, $kind, $cmd = 'index', $field = '', $index = ''): string
{
	global $config,$called_by_script_server;
	if (!ctype_digit((string)$host_id) || !in_array($kind, ['memory','qfp_memory'], true)) {
		return $cmd === 'get' ? 'U' : '';
	}
	require_once __DIR__.'/ss_cisco_health.php';
	try {
		if ($cmd === 'get') {
			if (!preg_match('/^(used|free)([1-8])$/D', $field, $m)) {
				return 'U';
			}
			return memory_combined_value($index, (int)$m[2], $m[1], fn ($id, $metric) => ss_cisco_health($host_id, $kind, 'get', $metric, $id));
		}
		$raw = trim(ss_cisco_health($host_id, $kind, 'index'));
		$current = $raw === '' ? [] : preg_split('/\s+/', $raw);
		$bindings = memory_combined_existing($host_id, $kind);
		foreach ($bindings as $b) {
			memory_combined_ids($b);
		}
		if (!$bindings && $current) {
			$bindings = [memory_combined_binding($current)];
		}
		if ($cmd === 'index') {
			return $bindings ? implode("\n", $bindings)."\n" : '';
		}
		if ($cmd === 'num_indexes') {
			return (string)count($bindings);
		}
		if ($cmd !== 'query') {
			return '';
		}
		$names = [];
		if (preg_match('/^pool[1-8]Name$/D', $field)) {
			foreach (explode("\n", trim(ss_cisco_health($host_id, $kind, 'query', 'name'))) as $line) {
				$parts = explode('!', $line, 2);
				if (count($parts) === 2) {
					$names[$parts[0]] = $parts[1];
				}
			}
		}
		$out = '';
		foreach ($bindings as $b) {
			$ids = memory_combined_ids($b);
			if ($field === 'index') {
				$v = $b;
			} elseif ($field === 'name') {
				$v = 'All pools';
			} elseif ($field === 'poolCount') {
				$v = (string)count($ids);
			} elseif ($field === 'newPools') {
				$v = (string)count(array_diff($current, $ids));
			} elseif (preg_match('/^pool([1-8])Name$/D', $field, $m)) {
				$id = $ids[(int)$m[1] - 1] ?? null;
				$v = $id === null ? '' : ($names[$id] ?? 'Pool '.$id.' (unavailable)');
			} else {
				return '';
			}
			$out .= $b.'!'.str_replace(["!","\r","\n"], ' ', $v)."\n";
		}
		return $out;
	} catch (InvalidArgumentException $e) {
		return $cmd === 'get' ? 'U' : ($cmd === 'num_indexes' ? '0' : '');
	}
}
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	require_once __DIR__.'/../include/cli_check.php';
	$called_by_script_server = true;
	$a = $_SERVER['argv'];
	echo ss_memory_combined($a[1] ?? '', $a[2] ?? '', $a[3] ?? 'index',$a[4] ?? '',$a[5] ?? '');
}
