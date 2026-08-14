<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Tests for import_validate_data_source_item() in lib/import.php.
 *
 * Template XML never passes through the data_sources.php form, so the three
 * free text data_template_rrd columns reach RRDtool unchecked. The rules here
 * have to accept everything the shipped install/templates packages contain.
 * lib/import.php only defines functions at load time, so it can be included
 * without a database or a full Cacti bootstrap.
 *
 * xml_to_data_template() is driven below in preview mode, which is the one way
 * to reach the data_template_rrd item loop without a live database. The loop
 * itself is shared with the write path, so the guard runs identically; the
 * ordering against sql_save() is pinned by a source assertion instead.
 */

if (!defined('POLLER_VERBOSITY_LOW')) {
	require_once dirname(__DIR__, 2) . '/include/global_constants.php';
}

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', '1.3.0');
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($a) {
		return is_array($a) ? count($a) : 0;
	}
}

if (!function_exists('cacti_count')) {
	function cacti_count($a) {
		return is_array($a) ? count($a) : 0;
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $environ = 'CMDPHP', $level = 0) {
		return true;
	}
}

if (!function_exists('cacti_version_compare')) {
	function cacti_version_compare($version1, $version2, $operator = '>') {
		return version_compare($version1, $version2, $operator);
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = [], $log = true) {
		return '';
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql, $col = '', $log = true) {
		return '';
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = [], $log = true) {
		return [];
	}
}

require_once dirname(__DIR__, 2) . '/lib/import.php';

/* Pull every data_source_name, rrd_minimum and rrd_maximum out of the packages
   Cacti ships, so the rules are measured against real template content. */
function shipped_data_source_item_values() : array {
	static $values = null;

	if ($values !== null) {
		return $values;
	}

	$values = ['data_source_name' => [], 'rrd_minimum' => [], 'rrd_maximum' => []];

	foreach (glob(dirname(__DIR__, 2) . '/install/templates/*.xml.gz') as $package) {
		$raw = gzdecode(file_get_contents($package));

		if ($raw === false || !preg_match_all('#<data>([A-Za-z0-9+/=\s]*)</data>#s', $raw, $blocks)) {
			continue;
		}

		foreach ($blocks[1] as $encoded) {
			$xml = base64_decode($encoded, true);

			if ($xml === false || strpos($xml, '<data_source_name>') === false) {
				continue;
			}

			foreach (array_keys($values) as $field) {
				if (preg_match_all('#<' . $field . '>(.*?)</' . $field . '>#s', $xml, $found)) {
					foreach ($found[1] as $value) {
						$values[$field][$value] = basename($package);
					}
				}
			}
		}
	}

	return $values;
}

test('every data source name in the shipped packages is accepted', function () {
	$names = shipped_data_source_item_values();

	expect(count($names['data_source_name']))->toBeGreaterThan(500);

	foreach ($names['data_source_name'] as $name => $package) {
		expect(import_validate_data_source_item('data_source_name', (string) $name))
			->toBeTrue('rejected ' . $name . ' from ' . $package);
	}
});

test('every minimum and maximum in the shipped packages is accepted', function () {
	$values = shipped_data_source_item_values();

	/* |query_ifSpeed| is a real rrd_maximum in ArubaOS_switch, so the rule
	   cannot be a plain numeric check. */
	expect($values['rrd_maximum'])->toHaveKey('|query_ifSpeed|');
	expect($values['rrd_maximum'])->toHaveKey('U');

	foreach (['rrd_minimum', 'rrd_maximum'] as $field) {
		foreach ($values[$field] as $value => $package) {
			expect(import_validate_data_source_item($field, (string) $value))
				->toBeTrue('rejected ' . $field . ' ' . $value . ' from ' . $package);
		}
	}
});

test('the remaining item fields are left to their column types', function () {
	expect(import_validate_data_source_item('data_source_type_id', '1'))->toBeTrue();
	expect(import_validate_data_source_item('rrd_heartbeat', '600'))->toBeTrue();
	expect(import_validate_data_source_item('data_input_field_id', '17'))->toBeTrue();
});

test('shell metacharacters in a data source name are refused', function () {
	expect(import_validate_data_source_item('data_source_name', 'ds;id'))->toBeFalse();
	expect(import_validate_data_source_item('data_source_name', 'ds`id`'))->toBeFalse();
	expect(import_validate_data_source_item('data_source_name', 'ds$(id)'))->toBeFalse();
	expect(import_validate_data_source_item('data_source_name', 'ds|id'))->toBeFalse();
	expect(import_validate_data_source_item('data_source_name', 'ds id'))->toBeFalse();
	expect(import_validate_data_source_item('data_source_name', ''))->toBeFalse();

	/* varchar(19) truncation would otherwise silently keep the leading bytes */
	expect(import_validate_data_source_item('data_source_name', str_repeat('a', 20)))->toBeFalse();
});

test('shell metacharacters in a minimum or maximum are refused', function () {
	expect(import_validate_data_source_item('rrd_maximum', '0;id;'))->toBeFalse();
	expect(import_validate_data_source_item('rrd_maximum', '100 -x'))->toBeFalse();
	expect(import_validate_data_source_item('rrd_maximum', '$(id)'))->toBeFalse();
	expect(import_validate_data_source_item('rrd_maximum', '|query_evil|'))->toBeFalse();
	expect(import_validate_data_source_item('rrd_minimum', '0`id`'))->toBeFalse();
	expect(import_validate_data_source_item('rrd_minimum', 'U;id'))->toBeFalse();
});

test('a trailing newline does not slip past the anchors', function () {
	expect(import_validate_data_source_item('data_source_name', "traffic_in\n"))->toBeFalse();
	expect(import_validate_data_source_item('data_source_name', "traffic_in\nid"))->toBeFalse();
	expect(import_validate_data_source_item('rrd_maximum', "U\n"))->toBeFalse();
	expect(import_validate_data_source_item('rrd_maximum', "100\nid"))->toBeFalse();
	expect(import_validate_data_source_item('rrd_minimum', "0\n"))->toBeFalse();
});

test('the data template import calls the validator before it saves', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/import.php');

	$decode = strpos($src, '$save[$field_name] = xml_character_decode($item_array[$field_name]);');
	$guard  = strpos($src, 'if (!import_validate_data_source_item($field_name, $save[$field_name])) {');
	$write  = strpos($src, "sql_save(\$save, 'data_template_rrd')");

	expect($decode)->not->toBeFalse();
	expect($guard)->not->toBeFalse();
	expect($write)->not->toBeFalse();
	expect($guard)->toBeGreaterThan($decode);
	expect($guard)->toBeLessThan($write);
});

test('a rejected data template aborts the import instead of adding false to the hash cache', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/import.php');

	expect($src)->toContain('$cache_add = xml_to_data_template(');
	expect($src)->not->toContain('$hash_cache += xml_to_data_template(');
});

/* Drive the real xml_to_data_template() item loop with the database stubbed out. */
function import_one_data_template(array $item) : mixed {
	global $struct_data_source, $struct_data_source_item, $preview_only, $legacy_template;
	global $hash_type_codes, $cacti_version_codes, $import_messages, $import_debug_info, $config;

	/* keeps the rejection's cacti_log() call off the database */
	$config['is_web'] = false;
	$config['config_options_array'] = [
		'log_verbosity'          => POLLER_VERBOSITY_NONE,
		'log_destination'        => 1,
		'selective_debug'        => '',
		'selective_plugin_debug' => ''
	];

	$preview_only        = true;
	$legacy_template     = false;
	$import_messages     = [];
	$import_debug_info   = [];
	$hash_type_codes     = ['data_template_item' => '08'];
	$cacti_version_codes = [CACTI_VERSION => '0400'];
	$struct_data_source  = [];

	$struct_data_source_item = [
		'data_source_name'    => [],
		'rrd_minimum'         => [],
		'rrd_maximum'         => [],
		'data_source_type_id' => [],
		'rrd_heartbeat'       => [],
		'data_input_field_id' => []
	];

	$item_hash  = 'hash_080400' . str_repeat('a', 32);
	$xml_array  = ['name' => 'Test Template', 'ds' => [], 'items' => [$item_hash => $item]];
	$hash_cache = [];

	return xml_to_data_template(str_repeat('b', 32), $xml_array, $hash_cache, true, 1);
}

function import_item(array $overrides) : array {
	return $overrides + [
		'data_source_name'    => 'traffic_in',
		'rrd_minimum'         => '0',
		'rrd_maximum'         => 'U',
		'data_source_type_id' => '2',
		'rrd_heartbeat'       => '600'
	];
}

test('the driven field list still matches global_form.php', function () {
	$form = file_get_contents(dirname(__DIR__, 2) . '/include/global_form.php');
	$item = substr($form, strpos($form, '$struct_data_source_item = ['));
	$item = substr($item, 0, strpos($item, "\n];"));

	foreach (['data_source_name', 'rrd_minimum', 'rrd_maximum', 'data_source_type_id', 'rrd_heartbeat', 'data_input_field_id'] as $field) {
		expect($item)->toContain("'" . $field . "' => [");
	}
});

test('a clean data source item is accepted by the item loop', function () {
	$result = import_one_data_template(import_item(['rrd_maximum' => '|query_ifSpeed|']));

	expect($result)->toBeArray();
	expect($result)->toHaveKey('data_template_item');
	expect($GLOBALS['import_messages'])->toBe([]);
});

test('an injected data source name aborts the item loop', function () {
	$result = import_one_data_template(import_item(['data_source_name' => 'ds;id;']));

	expect($result)->toBeFalse();
	expect($GLOBALS['import_messages'])->toContain(7);
});

test('an injected maximum aborts the item loop', function () {
	$result = import_one_data_template(import_item(['rrd_maximum' => "U\n; touch /tmp/pwned"]));

	expect($result)->toBeFalse();
	expect($GLOBALS['import_messages'])->toContain(7);
});

test('an injected minimum aborts the item loop', function () {
	$result = import_one_data_template(import_item(['rrd_minimum' => '0`id`']));

	expect($result)->toBeFalse();
	expect($GLOBALS['import_messages'])->toContain(7);
});
