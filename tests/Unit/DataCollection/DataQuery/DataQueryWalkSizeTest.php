<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-scan test for the SNMP walk size used by output_format fields (#7493).
 *
 * query_snmp_host() normalizes the device bulk walk size into $walk_size, then
 * hands it to cacti_snmp_session() for the index walk.  The output_format
 * branch called cacti_snmp_walk() with $host['max_oids'] in the argument slot
 * that cacti_snmp_walk() reads as $bulk_walk_size, so snmpbulkwalk ran with
 * -Cr set from max OIDs per get instead of the device's configured walk size.
 * Devices that reject large bulk walks then returned nothing for those fields.
 */

function _data_query_source() {
	$path = dirname(__DIR__, 4) . '/lib/data_query.php';
	$src  = file_get_contents($path);

	expect($src)->not->toBeFalse('Failed to read lib/data_query.php');

	return $src;
}

function _query_snmp_host_body() {
	$src = _data_query_source();

	$start = strpos($src, 'function query_snmp_host(');
	expect($start)->not->toBeFalse('query_snmp_host() must exist');

	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, ($end === false ? strlen($src) : $end) - $start);
}

test('the output_format walk uses the normalized bulk walk size', function () {
	$body = _query_snmp_host_body();

	$start = strpos($body, 'cacti_snmp_walk(');
	expect($start)->not->toBeFalse('query_snmp_host() must still walk for output_format fields');

	$call = substr($body, $start, strpos($body, ';', $start) - $start);

	/* the walk must be given the normalized bulk walk size, not max OIDs per get */
	expect($call)->toContain('$walk_size');
	expect($call)->not->toContain("\$host['max_oids']");
});

test('the walk size is still normalized before the walk runs', function () {
	$body = _query_snmp_host_body();

	expect(strpos($body, '$walk_size = $host[\'bulk_walk_size\']'))->not->toBeFalse(
		'the fixed-size branch must assign $walk_size from the device setting');
	expect(strpos($body, '$walk_size'))->toBeLessThan(strpos($body, 'cacti_snmp_walk('),
		'$walk_size must be assigned before the output_format walk uses it');
});
