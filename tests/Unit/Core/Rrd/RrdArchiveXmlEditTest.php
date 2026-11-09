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
 * rrd_delete_rra() and rrd_copy_rra() edit the XML dump that rrdtool restores
 * during an RRA resize. Both must touch exactly one archive, the one whose
 * consolidation function, resolution, XFF and row count all match.
 */

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 4) . '/lib/rrd.php';

/**
 * Build an RRD XML dump holding one archive per [cf, pdp_per_row, rows] entry.
 */
function rrd_archive_xml_dump(array $archives) : DOMDocument {
	$xml = '<?xml version="1.0" encoding="utf-8"?><rrd><version>0003</version><step>300</step>';

	foreach ($archives as [$cf, $pdp, $rows]) {
		$xml .= '<rra><cf>' . $cf . '</cf><pdp_per_row>' . $pdp . '</pdp_per_row>' .
			'<params><xff>5.0000000000e-01</xff></params><database>' .
			str_repeat('<row><v>1.0000000000e+00</v></row>', $rows) . '</database></rra>';
	}

	$dom = new DOMDocument();
	$dom->loadXML($xml . '</rrd>');

	return $dom;
}

/**
 * List the consolidation function of every archive in document order.
 */
function rrd_archive_xml_cfs(DOMDocument $dom) : array {
	$cfs = [];

	foreach ($dom->getElementsByTagName('rra') as $rra) {
		$cfs[] = $rra->getElementsByTagName('cf')->item(0)->nodeValue;
	}

	return $cfs;
}

test('deleting an archive removes only the first exact match and reports it', function () {
	$dom = rrd_archive_xml_dump([['AVERAGE', 1, 3], ['AVERAGE', 1, 2], ['AVERAGE', 1, 2], ['MAX', 1, 2]]);

	ob_start();
	$result = rrd_delete_rra($dom, ['cf' => 'AVERAGE', 'pdp_per_row' => '1', 'xff' => '0.5', 'rows' => 2]);
	$output = ob_get_clean();

	expect($result)->toBe($dom)
		->and(rrd_archive_xml_cfs($dom))->toBe(['AVERAGE', 'AVERAGE', 'MAX'])
		->and($dom->getElementsByTagName('row')->length)->toBe(7)
		->and($output)->toBe('RRA (CF=AVERAGE, ROWS=2, PDP_PER_ROW=1, XFF=0.50) removed from RRD file' . PHP_EOL);
});

test('deleting an archive with no exact match leaves the dump untouched', function () {
	$dom    = rrd_archive_xml_dump([['AVERAGE', 1, 2]]);
	$before = $dom->saveXML();

	ob_start();
	rrd_delete_rra($dom, ['cf' => 'AVERAGE', 'pdp_per_row' => '6', 'xff' => '0.5', 'rows' => 2]);

	expect(ob_get_clean())->toBe('')
		->and($dom->saveXML())->toBe($before);
});

test('copying an archive appends one clone under the new consolidation function', function () {
	$dom = rrd_archive_xml_dump([['AVERAGE', 1, 2], ['AVERAGE', 6, 2], ['AVERAGE', 6, 2]]);

	ob_start();
	$result = rrd_copy_rra($dom, 'MAX', ['cf' => 'AVERAGE', 'pdp_per_row' => '6', 'xff' => '0.5', 'rows' => 2]);
	$output = ob_get_clean();
	$rras   = $dom->getElementsByTagName('rra');
	$clone  = $rras->item($rras->length - 1);

	expect($result)->toBe($dom)
		->and(rrd_archive_xml_cfs($dom))->toBe(['AVERAGE', 'AVERAGE', 'AVERAGE', 'MAX'])
		->and($clone->getElementsByTagName('pdp_per_row')->item(0)->nodeValue)->toBe('6')
		->and($clone->getElementsByTagName('row')->length)->toBe(2)
		->and($output)->toBe('RRA (CF=MAX, ROWS=2, PDP_PER_ROW=6, XFF=0.50) adding to RRD file' . PHP_EOL);
});

test('archive edits ignore a value that is not a DOM document', function () {
	$value = new stdClass();

	expect(rrd_delete_rra($value, []))->toBe($value)
		->and(rrd_copy_rra($value, 'MAX', []))->toBe($value);
});
