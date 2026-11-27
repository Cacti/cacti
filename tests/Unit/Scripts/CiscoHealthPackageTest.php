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

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', implode('.', array_slice(explode('.', trim(file_get_contents(__DIR__ . '/../../../include/cacti_version'))), 0, 3)));
}
require_once __DIR__ . '/../../../lib/import.php';
require_once __DIR__ . '/../../../lib/xml.php';
require_once __DIR__ . '/../../../lib/graph_template_input.php';

test('signed package contains current sources', function () {
	$root = __DIR__ . '/../../../';
	$data = gzdecode(file_get_contents($root . 'install/templates/Cisco_Router.xml.gz'));
	$xml  = simplexml_load_string($data);
	$key  = base64_decode((string)$xml->publickey, true);
	$this->assertSame(1, openssl_verify(
		preg_replace('/^[ \t]*<signature>[^<]*<\/signature>\r?$/m', '   <signature></signature>', $data),
		base64_decode((string)$xml->signature, true), $key, OPENSSL_ALGO_SHA256
	));
	$expected = [
		'scripts/ss_cisco_health.php',
		'resource/script_server/cisco_cellular_radio.xml',
		'resource/snmp_queries/cisco_qfp_processors.xml',
		'scripts/ss_memory_combined.php',
		'scripts/ss_temperature_slots.php',
		'resource/script_server/memory_combined.xml',
		'resource/script_server/qfp_memory_combined.xml',
		'resource/script_server/temperature_slots.xml',
		'Cisco_Router.xml',
	];
	$found = [];

	foreach ($xml->files->file as $file) {
		$name    = (string)$file->name;
		$content = base64_decode((string)$file->data, true);
		$this->assertSame(1, openssl_verify($content, base64_decode((string)$file->filesignature, true), $key, OPENSSL_ALGO_SHA256), $name);

		if (in_array($name, $expected, true)) {
			$path = $name === 'Cisco_Router.xml' ? 'install/templates/' . $name : $name;
			$this->assertSame(file_get_contents($root . $path), $content, $name . ' differs from the signed package');
			$found[] = $name;
		}
	}
	$this->assertEqualsCanonicalizing($expected, $found);
});

test('every graph passes cacti input relationship preflight', function () {
	global $hash_type_codes, $cacti_version_codes;
	// Read the branch's hash mappings without initializing UI/plugin/database state.
	$source = file_get_contents(__DIR__ . '/../../../include/global_arrays.php');

	foreach (['hash_type_codes', 'cacti_version_codes'] as $name) {
		$this->assertSame(1, preg_match('/\\$' . $name . '\\s*=\\s*(?:array\\(|\\[)(.*?)(?:\\)|\\]);/s', $source, $block));
		preg_match_all("/'([^']+)'\\s*=>\\s*'([^']+)'/", $block[1], $pairs, PREG_SET_ORDER);
		$GLOBALS[$name] = array_column($pairs, 2, 1);
	}
	$xml     = file_get_contents(__DIR__ . '/../../../install/templates/Cisco_Router.xml');
	$objects = xml2array($xml);
	$this->assertNotEmpty($objects);

	foreach ($objects as $hash => $object) {
		if (str_starts_with($hash, 'hash_00')) {
			$this->assertTrue(graph_template_input_xml_preflight($object), $object['name']);
		}
	}
});

test('cisco router associates five queries without fixed graphs', function () {
	$xml     = simplexml_load_file(__DIR__ . '/../../../install/templates/Cisco_Router.xml');
	$objects = [];
	$host    = null;

	foreach ($xml->children() as $hash => $object) {
		$identity = substr($hash, -32);
		$this->assertArrayNotHasKey($identity, $objects, 'Duplicate template identity');
		$objects[$identity] = $object;

		if (str_starts_with($hash, 'hash_02')) {
			$host = $object;
		}
	}
	$this->assertNotNull($host);
	$queries = explode('|', (string)$host->data_queries);
	$this->assertCount(8, $queries);
	$names = [];

	foreach ($queries as $hash) {
		$names[] = (string)$objects[substr($hash, -32)]->name;
	}

	foreach (['Cisco - Cellular Radio', 'Cisco - QFP Processors', 'Cisco - Memory Usage - Combined', 'Cisco - QFP Memory Usage - Combined', 'Cisco - Temperature - All Sensors'] as $name) {
		$this->assertContains($name, $names);
	}
	$graphNames = [];

	foreach ($queries as $hash) {
		$query = $objects[substr($hash, -32)];

		if (!in_array((string)$query->name, ['Cisco - Cellular Radio', 'Cisco - QFP Processors', 'Cisco - Memory Usage - Combined', 'Cisco - QFP Memory Usage - Combined', 'Cisco - Temperature - All Sensors'], true)) {
			continue;
		}

		foreach ($query->graphs->children() as $association) {
			$graph        = $objects[substr((string)$association->graph_template_id, -32)];
			$graphNames[] = (string)$graph->name;
			$this->assertSame('3', (string)$graph->graph->image_format_id);
			$this->assertStringNotContainsString((string)$association->graph_template_id, (string)$host->graph_templates);
		}
	}
	$expected = ['Cisco - Cellular Signal Quality', 'Cisco - Cellular Signal Strength', 'Cisco - QFP Processor Utilisation'];

	foreach (range(1, 8) as $count) {
		$expected[] = 'Cisco - Memory Usage - ' . $count . ' pools';
		$expected[] = 'Cisco - QFP Memory Usage - ' . $count . ' pools';
		$expected[] = 'Cisco - Temperature - ' . $count . ' sensors';
		$expected[] = 'Cisco - Temperature - ' . $count . ' sensors - Fahrenheit';
	}
	$this->assertEqualsCanonicalizing($expected, $graphNames);
	$this->assertStringNotContainsString('a96490cb87fb4f11757feaba2806593c', (string)$host->graph_templates);
});

test('the release package uses an official Cacti signing key', function () {
	$xml = simplexml_load_string(gzdecode(file_get_contents(dirname(__DIR__, 3) . '/install/templates/Cisco_Router.xml.gz')));
	expect(is_cacti_public_key(base64_decode((string) $xml->publickey, true)))->toBeTrue('Maintainer signing is required before release');
});

test('fahrenheit variants share Celsius data and convert every displayed value', function () {
	$xml     = simplexml_load_file(__DIR__ . '/../../../install/templates/Cisco_Router.xml');
	$objects = [];
	$named   = [];

	foreach ($xml->children() as $hash => $object) {
		$objects[$hash] = $object;

		if (str_starts_with($hash, 'hash_00') || str_starts_with($hash, 'hash_04')) {
			$named[(string)$object->name] = $object;
		}
	}
	$query = $named['Cisco - Temperature - All Sensors'];
	$this->assertCount(16, $query->graphs->children());
	$this->assertSame('Cisco - Temperature - 1 sensors', (string)$query->graphs->children()[0]->name);

	foreach (range(1, 8) as $count) {
		$name = 'Cisco - Temperature - ' . $count . ' sensors';
		$c    = $named[$name];
		$f    = $named[$name . ' - Fahrenheit'];
		$this->assertSame('Temperature (°C)', (string)$c->graph->vertical_label);
		$this->assertSame('Temperature (°F)', (string)$f->graph->vertical_label);
		$this->assertSame('on', (string)$f->graph->auto_scale);
		$this->assertCount($count * 4, $f->items->children());
		$citems = array_values(iterator_to_array($c->items->children()));

		foreach (array_values(iterator_to_array($f->items->children())) as $i => $item) {
			$this->assertSame((string)$citems[$i]->task_item_id, (string)$item->task_item_id);
			$this->assertSame((string)$citems[$i]->color_id, (string)$item->color_id);
			$cdef = $objects[(string)$item->cdef_id];
			$this->assertSame('CURRENT_DATA_SOURCE,9,*,5,/,32,+', (string)$cdef->items->children()[0]->value);

			if ((string)$item->graph_type_id === '9') {
				$this->assertSame('%5.1lf °F', (string)$objects[(string)$item->gprint_id]->gprint_text);
			}
		}
		$associations = [];

		foreach ($query->graphs->children() as $association) {
			$associations[(string)$association->name] = $association;
		}
		$this->assertXmlStringEqualsXmlString($associations[$name]->rrd->asXML(), $associations[$name . ' - Fahrenheit']->rrd->asXML());
	}
});

// Snapshot of the stock develop package: health additions must not replace it.
test('health package preserves native develop stock templates and associations', function () {
	$expected = json_decode(file_get_contents(dirname(__DIR__, 2) . '/fixtures/cisco-develop-stock.json'), true, 512, JSON_THROW_ON_ERROR);
	$doc = new DOMDocument();
	$doc->preserveWhiteSpace = false;
	$doc->load(dirname(__DIR__, 3) . '/install/templates/Cisco_Router.xml');
	$objects = [];
	foreach ($doc->documentElement->childNodes as $node) {
		if ($node instanceof DOMElement) {
			$objects[$node->tagName] = $node;
		}
	}
	foreach ($expected['objects'] as $hash => $digest) {
		$this->assertArrayHasKey($hash, $objects);
		$this->assertSame($digest, hash('sha256', $objects[$hash]->C14N()), $hash);
	}
	$defaults = [];
	foreach ($objects as $hash => $object) {
		if (str_starts_with($hash, 'hash_20') && $object->getElementsByTagName('default')->item(0)->textContent === 'on') {
			$defaults[] = $object->getElementsByTagName('name')->item(0)->textContent;
		}
	}
	$this->assertSame(['1 Minute Collection (Long Retention)'], $defaults);
	$host = $objects[$expected['host']];
	$this->assertSame($expected['graph_templates'], $host->getElementsByTagName('graph_templates')->item(0)->textContent);
	$queries = explode('|', $host->getElementsByTagName('data_queries')->item(0)->textContent);
	foreach ($expected['data_queries'] as $query) {
		$this->assertContains($query, $queries);
	}
	$this->assertCount(count($expected['data_queries']) + 5, $queries);
	$package = simplexml_load_string(gzdecode(file_get_contents(dirname(__DIR__, 3) . '/install/templates/Cisco_Router.xml.gz')));
	$this->assertSame('The Cacti Group', (string)$package->info->author);
	$this->assertSame('developers@cacti.net', (string)$package->info->email);
});
