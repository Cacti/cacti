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

$source = file_get_contents(__DIR__ . '/../../cli/change_device.php');

test('legacy id changes do not require interactive confirmation', function () use ($source) {
	expect($source)->toContain("if (\$file != '' && !\$force)");
	expect($source)->not->toContain('if (!$force) {');
});

test('validation and save failures both determine the final status', function () use ($source) {
	expect($source)->toContain('$validation_failed++');
	expect($source)->toContain('$save_failed++');
	expect($source)->toContain('$failed    = $validation_failed + $save_failed;');
	expect($source)->toContain('exit($failed > 0 ? 1 : 0);');
});

test('numeric CSV and CLI overrides share documented constraints', function () use ($source) {
	expect($source)->toContain("'ping_port'    => array('key' => 'ping_port', 'min' => 1, 'max' => 65534)");
	expect($source)->toContain("'threads'      => array('key' => 'device_threads', 'min' => 1, 'max' => 10)");
	expect($source)->toContain("'max_oids'     => array('key' => 'max_oids', 'min' => 1, 'max' => 60)");
	expect($source)->toContain("'bulk_walk'    => array('key' => 'bulk_walk_size', 'min' => 1, 'max' => 60, 'allow' => array(-1))");
	expect($source)->toContain("\$converted = convert_override_value('max_oids', \$value);");
	expect($source)->toContain("\$converted = convert_override_value('bulk_walk', \$value);");
});

test('bulk walk handling cannot fall through to version output', function () use ($source) {
	$bulkWalk = strpos($source, "case '--bulk_walk':");
	$version  = strpos($source, "case '-V':", $bulkWalk);
	$break    = strpos($source, 'break;', $bulkWalk);

	expect($bulkWalk)->not->toBeFalse();
	expect($version)->not->toBeFalse();
	expect($break)->not->toBeFalse();
	expect($break < $version)->toBeTrue();
});

test('disable values follow the documented semantics', function () use ($source) {
	expect($source)->toContain("case '1':");
	expect($source)->toContain("case 'on':");
	expect($source)->toContain("case '0':");
	expect($source)->toContain("case 'off':");
	expect($source)->toContain('Invalid disable value');
});

test('batch saves isolate API errors and reject stale snapshots', function () use ($source) {
	expect(substr_count($source, 'clear_cli_messages();'))->toBeGreaterThanOrEqual(2);
	expect($source)->not->toContain('clear_messages();');
	expect($source)->toContain('device_editable_state_changed($original, $current)');
	expect($source)->toContain('changed after preview; it was not saved');
});

test('preview and save output do not expose SNMP secrets', function () use ($source) {
	expect($source)->toContain("\$sensitive = array('snmp_community', 'snmp_password', 'snmp_priv_passphrase')");
	expect($source)->toContain('[redacted] -> [redacted]');

	$saveStart = strpos($source, 'function save_device(');
	$apiStart  = strpos($source, '$host_id = api_device_save(', $saveStart);
	$output    = substr($source, $saveStart, $apiStart - $saveStart);

	expect($output)->not->toContain('snmp_community');
	expect($output)->not->toContain('snmp_password');
	expect($output)->not->toContain('snmp_priv_passphrase');
});

test('CSV structure and duplicate device ids are rejected', function () use ($source) {
	expect($source)->toContain('cacti_count(array_unique($header))');
	expect($source)->toContain('cacti_count($row) != cacti_count($header)');
	expect($source)->toContain('Duplicate device id on line');
	expect($source)->toContain('substr($header[0], 0, 3)');
});

test('remote pollers switch administrative changes to the main database', function () use ($source) {
	expect($source)->toContain("if (\$config['poller_id'] > 1)");
	expect($source)->toContain('db_switch_remote_to_main();');
});

test('no-op rows are excluded from device saves', function () use ($source) {
	expect($source)->toContain('if (!device_has_requested_changes($original, $merged))');
	expect($source)->toContain('already has the requested settings; no changes are needed.');
	expect($source)->toContain('No devices require changes.');
});

test('CSV parser uses explicit escape behavior and validates its header', function () use ($source) {
	expect($source)->toContain("if (\$header[0] !== 'id')");
	expect($source)->toContain("fgetcsv(\$fh, 0, ',', '\"', '')");
	expect($source)->toContain("Unknown CSV column '\$column'");
});
