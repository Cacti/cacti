<?php

// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('combined health collectors preserve resource identity and numeric values', function ($fixture, $mode) {
	$command = [PHP_BINARY, dirname(__DIR__, 2) . '/Helpers/CiscoCombined_' . $fixture . '.php'];
	if ($mode !== '') {
		$command[] = $mode;
	}
	$pipes = [];
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$this->assertIsResource($process);
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$this->assertSame(0, proc_close($process), $output . $error);
	$this->assertSame('', $error);
	$this->assertStringContainsString('PASS', $output);
})->with([
	'discovery request count' => ['discovery', ''],
	'memory identity' => ['memory', ''],
	'temperature identity' => ['temperature', ''],
	'array SNMP API' => ['snmp_compat', 'array'],
	'string SNMP API' => ['snmp_compat', 'string'],
	'shared Script Server process' => ['shared_loader', ''],
]);

test('automation manifest resolves all twenty seven imported graph variants', function () {
	$root = dirname(__DIR__, 3);
	$xml = simplexml_load_file($root . '/install/templates/Cisco_Router.xml');
	$rules = json_decode(file_get_contents($root . '/cli/cisco_health_rules.json'), true, 512, JSON_THROW_ON_ERROR);
	$this->assertCount(27, $rules);
	$this->assertCount(27, array_unique(array_column($rules, 'variant_hash')));
	$queries = [];
	foreach ($xml->children() as $hash => $object) {
		if (str_starts_with($hash, 'hash_04')) {
			$queries[substr($hash, -32)] = $object;
		}
	}
	foreach ($rules as $rule) {
		$this->assertArrayHasKey($rule['query_hash'], $queries);
		$variants = [];
		foreach ($queries[$rule['query_hash']]->graphs->children() as $hash => $object) {
			$variants[substr($hash, -32)] = (string)$object->name;
		}
		$this->assertSame($rule['graph_type'], $variants[$rule['variant_hash']]);
		if ($rule['field'] !== null) {
			$this->assertContains($rule['field'], ['poolCount', 'sensorCount']);
			$this->assertContains($rule['matches'], range(1, 8));
			$this->assertStringContainsString(' - ' . $rule['matches'] . ' ', $rule['graph_type']);
		}
	}
});


test('combined graph presentation keeps each pool separate and temperature automatic', function () {
	$xml = simplexml_load_file(dirname(__DIR__, 3) . '/install/templates/Cisco_Router.xml');
	$objects = [];
	foreach ($xml->children() as $hash => $object) {
		$objects[$hash] = $object;
	}
	$memory = 0;
	$temperature = 0;
	foreach ($objects as $hash => $graph) {
		if (!str_starts_with($hash, 'hash_00')) {
			continue;
		}
		$name = (string)$graph->name;
		if (str_starts_with($name, 'Cisco - Temperature - ')) {
			$temperature++;
			$this->assertSame('on', (string)$graph->graph->auto_scale);
			$this->assertSame('1', (string)$graph->graph->auto_scale_opts);
			$this->assertSame('', (string)$graph->graph->lower_limit);
			$unit = str_ends_with($name, ' - Fahrenheit') ? '°F' : '°C';
			$this->assertSame('Temperature (' . $unit . ')', (string)$graph->graph->vertical_label);
			foreach ($graph->items->children() as $item) {
				if ((string)$item->graph_type_id === '9') {
					$this->assertSame('%5.1lf ' . $unit, (string)$objects[(string)$item->gprint_id]->gprint_text);
				}
			}
		}
		if (!str_starts_with($name, 'Cisco - Memory Usage - ') && !str_starts_with($name, 'Cisco - QFP Memory Usage - ')) {
			continue;
		}
		$memory++;
		$this->assertSame('', (string)$graph->graph->auto_scale);
		$this->assertSame('0', (string)$graph->graph->lower_limit);
		$this->assertSame('100', (string)$graph->graph->upper_limit);
		$this->assertSame('1000', (string)$graph->graph->base_value);
		$items = array_values(iterator_to_array($graph->items->children()));
		preg_match('/ - ([1-8]) pools$/', $name, $match);
		$this->assertCount(4 * (int)$match[1], $items);
		foreach (array_chunk($items, 4) as $pool => [$line, $percent, $used, $free]) {
			$this->assertSame((string)$line->task_item_id, (string)$used->task_item_id);
			$this->assertSame((string)$line->task_item_id, (string)$percent->task_item_id);
			$this->assertNotSame((string)$used->task_item_id, (string)$free->task_item_id);
			$this->assertSame((string)$line->cdef_id, (string)$percent->cdef_id);
			$this->assertSame('0', (string)$used->cdef_id);
			$this->assertSame('0', (string)$free->cdef_id);
			$a = strtr((string)($pool * 2), '0123456789', 'abcdefghij');
			$b = strtr((string)($pool * 2 + 1), '0123456789', 'abcdefghij');
			$cdef = $objects[(string)$line->cdef_id];
			$expression = $cdef->items->children()[0];
			$this->assertSame("$a,$b,+,0,GT,$a,$a,$b,+,/,100,*,UNKN,IF", (string)$expression->value);
			foreach ([$percent, $used, $free] as $item) {
				$this->assertSame('11', (string)$item->graph_type_id);
				$this->assertSame('1', (string)$item->consolidation_function_id);
			}
		}
	}
	$this->assertSame(16, $memory);
	$this->assertSame(16, $temperature);
});


test('rule setup checks the main database and automation associations before writing', function ($scenario, $mode, $status, $message, $result) {
	$command = [PHP_BINARY, dirname(__DIR__, 2) . '/Helpers/CiscoRulesFixture.php', $scenario, $mode];
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$this->assertIsResource($process);
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	$this->assertSame($status, proc_close($process), $output . $error);
	$this->assertStringContainsString($message, $output . $error);
	$this->assertStringContainsString($result, $output);
})->with([
	'1.2 main database' => ['local', 'apply', 0, 'Installed 27 new rules', 'main=1 writes=78'],
	'remote collector selects main' => ['remote-online', 'apply', 0, 'Installed 27 new rules', 'main=1 writes=78'],
	'remote offline fails closed' => ['remote-offline', 'apply', 1, 'Cannot reach the main Cacti database', 'main=0 writes=0'],
	'read-only remote plan' => ['remote-online', 'plan', 0, 'Plan only', 'main=1 writes=0'],
	'new rules excluded by associations' => ['linked-missing', 'apply', 1, 'Create and link', 'main=1 writes=0'],
	'existing rules excluded by associations' => ['linked-unlinked', 'apply', 1, 'Create and link', 'main=1 writes=0'],
	'existing linked rules reused' => ['linked-all', 'apply', 0, 'Installed 0 new rules', 'main=1 writes=0'],
	'empty Automation Template permits ordinary rules' => ['empty-template', 'apply', 0, 'Installed 27 new rules', 'main=1 writes=78'],
]);
