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
 * api_data_source_disable_multi() reassigned $poller_ids on every 1000-id
 * chunk, so the trailing api_data_source_cache_crc_update loop only saw
 * the pollers attached to the last chunk. Pollers that owned only items
 * in earlier chunks kept stale poller_output_boost rows and their CRCs
 * never advanced, so remote pollers continued to push data for already-
 * disabled local_data_ids until the next manual cache rebuild. The fix
 * is an $all_poller_ids accumulator built with the `+` union so keys
 * survive across chunks and the CRC update covers every poller touched.
 */

$source = file_get_contents(__DIR__ . '/../../lib/api_data_source.php');

$start = strpos($source, 'function api_data_source_disable_multi(');
expect($start)->not->toBeFalse();

$end  = strpos($source, "\nfunction ", $start + 1);
$body = substr($source, $start, $end !== false ? $end - $start : 8000);

test('api_data_source_disable_multi initialises the accumulator once', function () use ($body) {
	expect($body)->toContain('$all_poller_ids = array();');

	/* Only one initialisation, otherwise we are clobbering across chunks again. */
	expect(substr_count($body, '$all_poller_ids = array();'))->toBe(1);
});

test('both chunk paths append to $all_poller_ids', function () use ($body) {
	expect(substr_count($body, '$all_poller_ids = $all_poller_ids + $poller_ids;'))->toBe(2);
});

test('trailing CRC update reads $all_poller_ids, not the last-chunk local', function () use ($body) {
	expect($body)->toContain('if (cacti_sizeof($all_poller_ids))');
	expect($body)->toContain('foreach ($all_poller_ids as $poller_id)');
	expect($body)->toContain('api_data_source_cache_crc_update($poller_id);');

	$crcSection = substr($body, strpos($body, 'if (cacti_sizeof($all_poller_ids))'));
	expect(strpos($crcSection, 'if (cacti_sizeof($poller_ids))'))
		->toBeFalse('the trailing CRC guard must not fall back to $poller_ids');
});

test('chunk union behaviour: $all_poller_ids preserves pollers from every chunk', function () {
	/* Build a fixture that mirrors the production layout: 2500 local_data_ids,
	 * the first 1000 attached to poller 1, the next 1000 split between
	 * pollers 2 and 3, the final 500 attached to poller 4. The old single-
	 * $poller_ids shape only retained poller 4 after the loop completed. */
	$chunks = [
		array_fill_keys([1], 1),               /* chunk 1: poller 1 only */
		array_fill_keys([2, 3], null),         /* chunk 2: pollers 2 and 3 */
		array_fill_keys([4], 4),               /* chunk 3: poller 4 only */
	];

	/* Old shape: $poller_ids is reassigned every chunk. */
	$poller_ids = array();
	foreach ($chunks as $chunk) {
		$poller_ids = array_combine(array_keys($chunk), array_keys($chunk));
	}
	expect(array_keys($poller_ids))->toBe([4]);

	/* New shape: $all_poller_ids accumulates via `+` union. */
	$all_poller_ids = array();
	foreach ($chunks as $chunk) {
		$poller_ids     = array_combine(array_keys($chunk), array_keys($chunk));
		$all_poller_ids = $all_poller_ids + $poller_ids;
	}
	$keys = array_keys($all_poller_ids);
	sort($keys);
	expect($keys)->toBe([1, 2, 3, 4]);
});
