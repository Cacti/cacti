<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-2fhv-c794-v433: data_sources.php's single-item ds_enable()/ds_disable()
 * and the bulk drp_action enable/disable loops in form_actions() passed a
 * user-controlled local_data_id straight into api_data_source_enable()/
 * api_data_source_disable() with no object-level authorization check, letting
 * a Sites/Devices/Data (realm 3) user toggle the state of a data source
 * belonging to a device outside their assigned Device Permissions. The fix
 * adds a data_source_authorized() helper (allow when the data source's
 * data_local.host_id is 0/host-independent, otherwise require
 * is_device_allowed($host_id)) and calls it before every enable/disable path.
 */

$source = file_get_contents(__DIR__ . '/../../../../data_sources.php');

test('GHSA-2fhv: data_source_authorized() exists and defers to is_device_allowed() for device-bound data sources', function () use ($source) {
	$start = strpos($source, 'function data_source_authorized(');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\n}\n", $start);
	$body = substr($source, $start, $end - $start);

	expect($body)->toContain('is_device_allowed($host_id)');
	expect($body)->toContain('if (empty($host_id)) {');
});

test('GHSA-2fhv: ds_disable() checks authorization before calling api_data_source_disable()', function () use ($source) {
	$start = strpos($source, 'function ds_disable(');
	$end   = strpos($source, "\n}\n", $start);
	$body  = substr($source, $start, $end - $start);

	$checkPos = strpos($body, "data_source_authorized(get_request_var('id'))");
	$callPos  = strpos($body, 'api_data_source_disable(');

	expect($checkPos)->not->toBeFalse();
	expect($callPos)->not->toBeFalse();
	expect($checkPos)->toBeLessThan($callPos);
});

test('GHSA-2fhv: ds_enable() checks authorization before calling api_data_source_enable()', function () use ($source) {
	$start = strpos($source, 'function ds_enable(');
	$end   = strpos($source, "\n}\n", $start);
	$body  = substr($source, $start, $end - $start);

	$checkPos = strpos($body, "data_source_authorized(get_request_var('id'))");
	$callPos  = strpos($body, 'api_data_source_enable(');

	expect($checkPos)->not->toBeFalse();
	expect($callPos)->not->toBeFalse();
	expect($checkPos)->toBeLessThan($callPos);
});

test('GHSA-2fhv: the bulk enable/disable actions filter each selected id through data_source_authorized()', function () use ($source) {
	expect(substr_count($source, 'if (data_source_authorized($selected_items[$i])) {'))->toBeGreaterThanOrEqual(2);
});
