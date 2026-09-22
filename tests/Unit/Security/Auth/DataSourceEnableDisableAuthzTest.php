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

	expect($body)->toContain('is_device_allowed((int) $host_id)');
	expect($body)->toContain('if (empty($host_id)) {');
});

test('GHSA-2fhv: ds_disable() checks authorization before calling api_data_source_disable()', function () use ($source) {
	$start = strpos($source, 'function ds_disable(');
	$end   = strpos($source, "\n}\n", $start);
	$body  = substr($source, $start, $end - $start);

	$checkPos  = strpos($body, 'data_source_authorized((int) grv(\'id\'))');
	$callPos   = strpos($body, 'api_data_source_disable(');

	expect($checkPos)->not->toBeFalse();
	expect($callPos)->not->toBeFalse();
	expect($checkPos)->toBeLessThan($callPos);
});

test('GHSA-2fhv: ds_enable() checks authorization before calling api_data_source_enable()', function () use ($source) {
	$start = strpos($source, 'function ds_enable(');
	$end   = strpos($source, "\n}\n", $start);
	$body  = substr($source, $start, $end - $start);

	$checkPos = strpos($body, 'data_source_authorized((int) grv(\'id\'))');
	$callPos  = strpos($body, 'api_data_source_enable(');

	expect($checkPos)->not->toBeFalse();
	expect($callPos)->not->toBeFalse();
	expect($checkPos)->toBeLessThan($callPos);
});

test('GHSA-2fhv: the bulk disable/enable actions filter each selected id through data_source_authorized()', function () use ($source) {
	expect(substr_count($source, 'if (data_source_authorized((int) $local_data_id)) {'))->toBeGreaterThanOrEqual(2);
});

/*
 * GHSA-fc82-w6xq-4jg7: the bulk "Change Data Source Profile" action
 * (drp_action == '6') iterated $selected_items and updated
 * data_template_data/data_template_rrd/poller_item for each local_data_id with
 * no object-level authorization check -- the same class of defect as
 * GHSA-2fhv, just a different bulk action in the same file. Guarded with the
 * same data_source_authorized() helper.
 */
test('GHSA-fc82: the bulk change-data-source-profile action skips unauthorized data sources', function () use ($source) {
	$start = strpos($source, "drp_action') == '6') { // change data source profile");
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\n\t\t\t} elseif", $start);
	$body = substr($source, $start, $end - $start);

	$checkPos  = strpos($body, 'if (!data_source_authorized((int) $local_data_id)) {');
	$updatePos = strpos($body, "UPDATE data_template_data");

	expect($checkPos)->not->toBeFalse();
	expect($updatePos)->not->toBeFalse();
	expect($checkPos)->toBeLessThan($updatePos);
});
