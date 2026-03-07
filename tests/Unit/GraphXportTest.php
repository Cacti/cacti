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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/xml.php';

// --- rrdxport2array: valid xport XML produces correct structure ---

test('rrdxport2array parses valid xport XML into meta and data', function () {
	$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xport>
  <meta>
    <start>1700000000</start>
    <end>1700003600</end>
    <step>300</step>
    <rows>2</rows>
    <columns>2</columns>
    <legend>
      <entry>traffic_in</entry>
      <entry>traffic_out</entry>
    </legend>
  </meta>
  <data>
    <row><t>1700000000</t><v>1234.56</v><v>7890.12</v></row>
    <row><t>1700000300</t><v>2345.67</v><v>8901.23</v></row>
  </data>
</xport>
XML;

	$result = rrdxport2array($xml);

	expect($result)->toBeArray()
		->and($result['meta'])->toBeArray()
		->and($result['meta']['start'])->toBe('1700000000')
		->and($result['meta']['end'])->toBe('1700003600')
		->and($result['meta']['step'])->toBe('300')
		->and($result['meta']['rows'])->toBe('2')
		->and($result['meta']['columns'])->toBe('2')
		->and($result['meta']['legend'])->toBeArray()
		->and($result['meta']['legend']['col1'])->toBe('traffic_in')
		->and($result['meta']['legend']['col2'])->toBe('traffic_out')
		->and($result['data'])->toBeArray()
		->and($result['data'])->toHaveCount(2)
		->and($result['data'][1]['timestamp'])->toBe('1700000000')
		->and($result['data'][1]['col1'])->toBe('1234.56');
});

// --- rrdxport2array: rrdtool error XML has no meta key ---

test('rrdxport2array returns no meta key for rrdtool error XML', function () {
	$xml = '<?xml version="1.0" encoding="UTF-8"?><error>ERROR: opening rrd: No such file</error>';

	$result = rrdxport2array($xml);

	expect($result)->toBeArray()
		->and(isset($result['meta']))->toBeFalse();
});

// --- rrdxport2array: empty string input returns array without meta ---

test('rrdxport2array returns array without meta for empty input', function () {
	$result = rrdxport2array('');

	expect($result)->toBeArray()
		->and(isset($result['meta']))->toBeFalse();
});

// --- rrdxport2array: xport with zero data rows has meta but empty data ---

test('rrdxport2array handles xport with no data rows', function () {
	$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xport>
  <meta>
    <start>1700000000</start>
    <end>1700003600</end>
    <step>300</step>
    <rows>0</rows>
    <columns>1</columns>
    <legend>
      <entry>traffic_in</entry>
    </legend>
  </meta>
  <data>
  </data>
</xport>
XML;

	$result = rrdxport2array($xml);

	expect($result)->toBeArray()
		->and(isset($result['meta']))->toBeTrue()
		->and($result['meta']['start'])->toBe('1700000000')
		->and(empty($result['data']))->toBeTrue();
});

// --- rrdxport2array: strips non-XML preamble lines (rrdtool 1.2.30 bug) ---

test('rrdxport2array strips non-XML preamble from rrdtool output', function () {
	$xml = "OK u:0.00 s:0.00 r:0.01\n" .
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		"<xport>\n<meta>\n<start>1700000000</start>\n<end>1700003600</end>\n" .
		"<step>300</step>\n<rows>0</rows>\n<columns>1</columns>\n" .
		"<legend>\n<entry>col</entry>\n</legend>\n</meta>\n<data>\n</data>\n</xport>";

	$result = rrdxport2array($xml);

	expect(isset($result['meta']))->toBeTrue()
		->and($result['meta']['start'])->toBe('1700000000');
});

// --- Export guard: mirrors the early-exit logic in graph_xport.php ---

function xportShouldFail(mixed $xport_array): bool {
	return !is_array($xport_array) || !isset($xport_array['meta']['start']);
}

test('export guard rejects false return from rrdtool_function_xport', function () {
	expect(xportShouldFail(false))->toBeTrue();
});

test('export guard rejects empty array', function () {
	expect(xportShouldFail([]))->toBeTrue();
});

test('export guard rejects array with error key but no meta', function () {
	expect(xportShouldFail(['error' => 'some rrdtool error']))->toBeTrue();
});

test('export guard rejects meta without start key', function () {
	expect(xportShouldFail(['meta' => ['columns' => 1]]))->toBeTrue();
});

test('export guard passes valid xport structure', function () {
	$valid = [
		'meta' => [
			'start'   => '1700000000',
			'end'     => '1700003600',
			'step'    => '300',
			'rows'    => '2',
			'columns' => '1',
		],
		'data' => [],
	];

	expect(xportShouldFail($valid))->toBeFalse();
});

// --- rrd.php guard: mirrors the !isset($xport_array['meta']) check ---

function xportMetaMissing(array $xport_array): bool {
	return !isset($xport_array['meta']);
}

test('rrd guard detects missing meta from error XML parse', function () {
	$errorResult = rrdxport2array(
		'<?xml version="1.0" encoding="UTF-8"?><error>ERROR: ds not found</error>'
	);

	expect(xportMetaMissing($errorResult))->toBeTrue();
});

test('rrd guard passes when meta exists', function () {
	$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xport>
  <meta>
    <start>1700000000</start>
    <end>1700003600</end>
    <step>300</step>
    <rows>0</rows>
    <columns>1</columns>
    <legend><entry>a</entry></legend>
  </meta>
  <data></data>
</xport>
XML;

	$result = rrdxport2array($xml);

	expect(xportMetaMissing($result))->toBeFalse();
});
