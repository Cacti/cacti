<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

test('traffic migration preserves calculations and links across repeated imports', function () {
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/traffic_legend_migration.php'), $output, $status);
	expect($status)->toBe(0);
	expect(implode("\n", $output))->toStartWith('PASS: five templates');
});

test('release preparation updates every bundled traffic definition without changing signed archives', function () {
	$root = dirname(__DIR__, 4);
	$directory = sys_get_temp_dir() . '/cacti-traffic-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$before = [];
	foreach (glob($root . '/install/templates/*.xml.gz') as $file) {
		$before[$file] = hash_file('sha256', $file);
	}
	try {
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/tools/prepare_traffic_templates.php') . ' ' . escapeshellarg($directory), $output, $status);
		expect($status)->toBe(0);
		$files = glob($directory . '/*.xml');
		expect($files)->toHaveCount(13);
		foreach ($files as $file) {
			$xml = simplexml_load_file($file);
			$updated = 0;
			foreach ($xml->children() as $template) {
				if (!isset($template->items)) {
					continue;
				}
				$items = [];
				foreach ($template->items->children() as $id => $item) {
					$items[(int) $item->sequence] = ['id' => $id, 'item' => $item];
				}
				ksort($items);
				$previous = null;
				$peaks = 0;
				foreach ($items as $entry) {
					$item = $entry['item'];
					if (trim((string) $item->text_format) === 'Max peak:') {
						expect((string) $previous->graph_type_id)->toBe('4');
						expect((string) $previous->consolidation_function_id)->toBe('3');
						expect((string) $item->task_item_id)->toBe((string) $previous->task_item_id);
						expect((string) $item->cdef_id)->toBe((string) $previous->cdef_id);
						$linked = false;
						foreach ($template->inputs->children() as $input) {
							foreach (explode('|', (string) $input->items) as $ref) {
								$linked = $linked || substr($ref, -32) === substr($entry['id'], -32);
							}
						}
						expect($linked)->toBeTrue();
						$peaks++;
					}
					$previous = $item;
				}
				if ($peaks) {
					expect($peaks)->toBe(2);
					$updated++;
				}
			}
			expect($updated)->toBe(5);
		}
		foreach ($before as $file => $hash) {
			expect(hash_file('sha256', $file))->toBe($hash);
		}
	} finally {
		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
});
