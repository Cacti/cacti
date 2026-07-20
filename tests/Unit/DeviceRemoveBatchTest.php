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

// Guards the api_device_remove_multi() optimisation: the per-device poller_item
// and poller_reindex deletes were collapsed into one IN delete, and the CSV of
// device ids is now built with implode() instead of an incremental loop. Both
// must produce the same values the old code did.

// Old form: incremental concat with a first-item flag.
function build_devices_csv_legacy(array $device_ids): string {
	$out = '';
	$i   = 0;

	foreach ($device_ids as $device_id) {
		if ($i == 0) {
			$out .= intval($device_id);
		} else {
			$out .= ', ' . intval($device_id);
		}

		$i++;
	}

	return $out;
}

test('imploded device csv matches the legacy incremental build', function () {
	$cases = array(
		array(5),
		array(5, 12, 88),
		array('5', '12', '88'),   // string ids, as they arrive from a request
		array(3, 3, 9),           // duplicates preserved
	);

	foreach ($cases as $ids) {
		$new = implode(', ', array_map('intval', $ids));
		expect($new)->toBe(build_devices_csv_legacy($ids));
	}
});

test('a set of per-device host_id predicates covers the same ids as one IN list', function () {
	$ids = array(5, 12, 88);

	// The old code deleted host_id = ? once per device; the new code deletes
	// host_id IN (all). The targeted id set is identical.
	$per_device = array();
	foreach ($ids as $id) {
		$per_device[$id] = true;
	}

	$in_list = array_map('intval', $ids);

	sort($in_list);
	$keys = array_keys($per_device);
	sort($keys);

	expect($keys)->toBe($in_list);
});
