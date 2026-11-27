#!/usr/bin/env php
<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
// Offline maintainer tool. Produces XML for normal import/export and release signing.
require_once dirname(__DIR__, 2) . '/lib/traffic_legend.php';

if (PHP_SAPI !== 'cli' || $argc !== 2 || !is_dir($argv[1])) {
	fwrite(STDERR, "Usage: php tests/tools/prepare_traffic_templates.php EXISTING_OUTPUT_DIRECTORY\n");
	exit(1);
}
$hashes = ['5deb0d66c81262843dce5f3861be9966', '1742b2066384637022d178cc5072905a',
	'13b47e10b2d5db45707d61851f69c52b', 'df244b337547b434b486662c3c5c7472', '8ad6790c22b693680e041f21d62537ac'];
foreach (glob(dirname(__DIR__, 2) . '/install/templates/*.xml.gz') as $path) {
	$package = simplexml_load_string(gzdecode(file_get_contents($path)));
	foreach ($package->files->file as $file) {
		$name = (string) $file->name;
		if (!str_ends_with($name, '.xml') || str_contains($name, '/')) {
			continue;
		}
		$document = simplexml_load_string(base64_decode((string) $file->data, true));
		$count = 0;
		foreach ($document->children() as $hash => $template) {
			if (!in_array(substr($hash, -32), $hashes, true)) {
				continue;
			}
			$rows = [];
			foreach ($template->items->children() as $id => $item) {
				$row = [];
				foreach ($item->children() as $field => $value) {
					$row[$field] = (string) $value;
				}
				$row['id'] = $id;
				$row['hash'] = substr($id, -32);
				$row['local_graph_id'] = 0;
				$rows[] = $row;
			}
			usort($rows, static fn ($a, $b) => (int) $a['sequence'] <=> (int) $b['sequence']);
			$plan = traffic_legend_plan($rows);
			if ($plan === false) {
				throw new RuntimeException("Unrecognised or already updated layout in $name: $hash");
			}
			$sequence = 1;
			foreach ($plan as $entry) {
				if (isset($entry['existing'])) {
					$id = $entry['existing']['id'];
					$item = $template->items->$id;
					$item->sequence = $sequence++;
					if ((string) $item->text_format === 'Maximum:') {
						$item->text_format = 'Max average:';
					}
					continue;
				}
				$row = $entry['peak'];
				$new_hash = traffic_legend_peak_hash($row['hash']);
				if ($new_hash === null) {
					throw new RuntimeException("Unknown source identity in $name");
				}
				$id = substr($row['id'], 0, -32) . $new_hash;
				$item = $template->items->addChild($id);
				foreach ($template->items->{$row['id']}->children() as $field => $value) {
					$item->addChild($field, htmlspecialchars((string) $value, ENT_XML1));
				}
				$item->sequence = $sequence++;
				$item->text_format = str_repeat(' ', 53) . 'Max peak:   ';
				$item->hard_return = 'on';
				foreach ($template->inputs->children() as $input) {
					$refs = explode('|', (string) $input->items);
					foreach ($refs as $ref) {
						if (substr($ref, -32) === $row['hash']) {
							$refs[] = substr($ref, 0, -32) . $new_hash;
							break;
						}
					}
					$input->items = implode('|', array_unique($refs));
				}
			}
			$count++;
		}
		if ($count) {
			$destination = rtrim($argv[1], '/') . '/' . basename($name);
			if (file_exists($destination)) {
				throw new RuntimeException("Refusing to overwrite $destination");
			}
			if ($document->asXML($destination) === false) {
				throw new RuntimeException("Unable to write $destination");
			}
			print "$name: prepared $count traffic templates; import/export with the official release key is still required\n";
		}
	}
}
