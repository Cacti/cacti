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

require_once __DIR__ . '/../../../lib/import.php';
require_once __DIR__ . '/../../../lib/xml.php';
require_once __DIR__ . '/../../../lib/graph_template_input.php';

test('signed package contains current sources', function () {
	$root = __DIR__ . '/../../../';
	$data = gzdecode(file_get_contents($root . 'install/templates/Cisco_Router.xml.gz'));
	$xml  = simplexml_load_string($data);
	$key  = base64_decode((string)$xml->publickey, true);
	$this->assertSame(1, openssl_verify(
		preg_replace('/<signature>[^<]*<\/signature>/', '<signature></signature>', $data),
		base64_decode((string)$xml->signature, true), $key, OPENSSL_ALGO_SHA256
	));
	$expected = [
		'scripts/ss_cisco_health.php',
		'resource/snmp_queries/cisco_cellular_radio.xml',
		'resource/snmp_queries/cisco_qfp_processors.xml',
		'resource/script_server/cisco_memory.xml',
		'resource/script_server/cisco_qfp_memory.xml',
		'resource/script_server/cisco_temperature.xml',
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
	require __DIR__ . '/../../../include/global_arrays.php';
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

	foreach (['Cisco - Cellular Radio', 'Cisco - QFP Processors', 'Cisco - Memory Usage', 'Cisco - QFP Memory Usage', 'Cisco - Temperature'] as $name) {
		$this->assertContains($name, $names);
	}
	$graphNames = [];

	foreach ($queries as $hash) {
		$query = $objects[substr($hash, -32)];

		if (!in_array((string)$query->name, ['Cisco - Cellular Radio', 'Cisco - QFP Processors', 'Cisco - Memory Usage', 'Cisco - QFP Memory Usage', 'Cisco - Temperature'], true)) {
			continue;
		}

		foreach ($query->graphs->children() as $association) {
			$graph        = $objects[substr((string)$association->graph_template_id, -32)];
			$graphNames[] = (string)$graph->name;
			$this->assertSame('3', (string)$graph->graph->image_format_id);
			$this->assertStringNotContainsString((string)$association->graph_template_id, (string)$host->graph_templates);
		}
	}
	$this->assertEqualsCanonicalizing([
		'Cisco - Cellular Signal Quality', 'Cisco - Cellular Signal Strength',
		'Cisco - Memory Usage', 'Cisco - QFP Memory Usage',
		'Cisco - QFP Processor Utilisation', 'Cisco - Temperature',
	], $graphNames);
	$this->assertStringNotContainsString('a96490cb87fb4f11757feaba2806593c', (string)$host->graph_templates);
});

test('package manifest matches the bundled Cisco package metadata and signer', function () {
	$root     = __DIR__ . '/../../../install/templates/';
	$xml      = simplexml_load_string(gzdecode(file_get_contents($root . 'Cisco_Router.xml.gz')));
	$manifest = json_decode(file_get_contents($root . 'package.manifest'), true);
	$entries  = array_values(array_filter($manifest['manifest'], static fn ($entry) => $entry['filename'] === 'Cisco_Router.xml.gz'));
	$this->assertCount(1, $entries);
	$info = json_decode(json_encode($xml->info), true);
	unset($info['name']);
	$this->assertSame($info, $entries[0]['metadata']);
	$this->assertContains([
		'publickey'     => base64_decode((string)$xml->publickey, true),
		'publickeyname' => (string)$xml->publickeyname,
	], $manifest['keys']);
});
