<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_transfer(): void {
	global $export_errors, $import_debug_info, $import_messages, $legacy_template, $ignorable_hashes;
	$state = [$export_errors, $import_debug_info, $import_messages, $legacy_template, $ignorable_hashes, $_SESSION];

	try {
		foreach (['data_input_method_to_xml' => 26, 'cdef_to_xml' => 25, 'gprint_preset_to_xml' => 24,
			'data_source_profile_to_xml'        => 23, 'host_template_to_xml' => 28, 'data_query_to_xml' => 28] as $export => $message) {
			$export_errors = 0;
			unset($_SESSION[SESS_MESSAGES][$message]);
			library_expect($export(0), '', 'Missing export dependency cannot produce a usable XML document');
			library_expect($export_errors, 1, 'Missing export dependency increments the error count exactly once');
			library_expect(isset($_SESSION[SESS_MESSAGES][$message]), true, 'Missing export dependency produces an operator message');
		}

		foreach (['hash_ff0101' . str_repeat('a', 32), 'hash_ff' . str_repeat('b', 32)] as $hash) {
			$import_messages = [];
			library_expect(parse_xml_hash($hash), false, 'Unknown template type is rejected in both modern and legacy hashes');
			library_expect($import_messages, [18, 7], 'Unsupported template hash records the unknown type and parse error');
		}
		$hash              = 'hash_' . get_hash_version('data_template') . str_repeat('a', 32);
		$cache             = [];
		$import_debug_info = [];
		library_expect(resolve_hash_to_id($hash, $cache, 'data_template'), 0, 'Unresolved dependency cannot acquire an unrelated database identity');
		library_expect($import_debug_info['dep'][$hash], 'unmet', 'Missing dependency remains visible in import diagnostics');
		$parsed                                  = parse_xml_hash($hash);
		$cache[$parsed['type']][$parsed['hash']] = 17;
		library_expect(resolve_hash_to_id($hash, $cache, 'data_template'), 17, 'Resolved dependency uses the importer identity map');
		library_expect($import_debug_info['dep'][$hash], 'met', 'Resolved dependency updates its diagnostic state');
		$info = ['graph_template' => [['result' => 'success', 'type' => 'new', 'title' => 'Import <fixture>',
			'orphans'                              => ['Orphan <item>'], 'new_items' => ['New <item>'], 'dep' => [$hash => 'unmet']]]];
		$ignorable_hashes = [];

		foreach ([false, true] as $preview) {
			foreach ([[], ['scripts/fixture <file>.php' => 'written']] as $files) {
				$dom  = library_form_dom(static function () use ($info, $files, $preview): void { import_display_results($info, $files, true, $preview); });
				$text = $dom->document->textContent;
				library_expect(str_contains($text, $preview ? 'would make' : 'has imported'), true, 'Import result distinguishes preview from persisted changes');
				library_expect(str_contains($text, $files ? 'Package' : 'Template'), true, 'Import result identifies package versus template scope');
				library_expect(str_contains($text, 'Import <fixture>') && str_contains($text, 'Orphan <item>') && str_contains($text, 'New <item>'), true, 'Import result retains literal resource names');
				library_expect($dom->query('//fixture | //item | //file')->length, 0, 'Operator-controlled names cannot inject markup');
				library_expect(str_contains($text, 'Unmet Dependency:'), !$preview, 'Completed import exposes unresolved dependencies');

				if ($files) {
					library_expect(str_contains($text, 'scripts/fixture <file>.php'), true, 'Package result names its actual file');
				}
			}
		}
	} finally {
		[$export_errors, $import_debug_info, $import_messages, $legacy_template, $ignorable_hashes, $_SESSION] = $state;
	}
}
