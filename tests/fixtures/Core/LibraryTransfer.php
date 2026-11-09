<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise template export and import failures for missing dependencies and unknown types. */
function library_transfer(): void {
	global $export_errors, $import_debug_info, $import_messages, $legacy_template, $ignorable_hashes;
	$state = [$export_errors, $import_debug_info, $import_messages, $legacy_template, $ignorable_hashes, $_SESSION];

	try {
		foreach (['data_input_method_to_xml' => 26, 'cdef_to_xml' => 25, 'gprint_preset_to_xml' => 24,
			'data_source_profile_to_xml'        => 23, 'host_template_to_xml' => 28, 'data_query_to_xml' => 28,
			'graph_template_to_xml'             => 30, 'data_template_to_xml' => 27] as $export => $message) {
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

/** Export references to VDEFs, nested CDEFs and suggested values, and import edited template XML. */
function library_transfer_templates(): void {
	global $preview_only;
	$preview_only = false;
	$restore      = [];

	try {
		foreach (array_column(db_fetch_assoc('SELECT id, name FROM vdef ORDER BY id'), 'name', 'id') as $id => $name) {
			$xml = vdef_to_xml((int) $id);
			library_expect((string) simplexml_load_string($xml)->name, $name, 'VDEF export is valid XML that keeps its name');
		}
		$vdef = (int) db_fetch_cell('SELECT id FROM vdef ORDER BY id LIMIT 1');
		$item = db_fetch_row('SELECT id, graph_template_id, vdef_id FROM graph_templates_item WHERE local_graph_id = 0 AND hash != "" ORDER BY graph_template_id, sequence LIMIT 1');
		library_expect($vdef > 0 && cacti_sizeof($item) > 0, true, 'Installed templates supply a VDEF and a graph item');
		$restore[] = ['UPDATE graph_templates_item SET vdef_id = ? WHERE id = ?', [$item['vdef_id'], $item['id']]];
		db_execute_prepared('UPDATE graph_templates_item SET vdef_id = ? WHERE id = ?', [$vdef, $item['id']]);
		library_expect(str_contains(graph_template_to_xml((int) $item['graph_template_id']), '<vdef_id>hash_' . get_hash_version('vdef') . get_hash_vdef($vdef) . '</vdef_id>'), true, 'A graph item exports its VDEF by hash');

		$inner     = (int) db_fetch_cell('SELECT id FROM cdef ORDER BY id LIMIT 1');
		$outer     = (int) sql_save(['id' => 0, 'hash' => generate_hash(), 'name' => 'Nested fixture CDEF', 'system' => 0], 'cdef');
		$restore[] = ['DELETE FROM cdef_items WHERE cdef_id = ?', [$outer]];
		$restore[] = ['DELETE FROM cdef WHERE id = ?', [$outer]];
		library_expect(sql_save(['id' => 0, 'hash' => generate_hash(), 'cdef_id' => $outer, 'sequence' => 1, 'type' => 5, 'value' => $inner], 'cdef_items') > 0, true, 'Nested CDEF item persists');
		library_expect(str_contains(cdef_to_xml($outer), '<value>hash_' . get_hash_version('cdef') . get_hash_cdef($inner) . '</value>'), true, 'A nested CDEF exports its inner CDEF by hash');

		$suggested = false;

		foreach (array_column(db_fetch_assoc('SELECT id FROM snmp_query ORDER BY id'), 'id') as $query) {
			$document = simplexml_load_string(data_query_to_xml((int) $query));
			library_expect($document !== false, true, 'Data query export is valid XML');
			$suggested = $suggested || ($document->xpath('//sv_graph/*/field_name') && $document->xpath('//sv_data_source/*/data_template_id'));
		}
		library_expect($suggested, true, 'An installed data query exports graph and data source suggested values');
		library_transfer_imports();
	} finally {
		foreach ($restore as [$sql, $parameters]) {
			db_execute_prepared($sql, $parameters);
		}
	}
}

/** Import legacy, invalid and edited template XML through import_xml_data(). */
function library_transfer_imports(): void {
	$cdef   = (int) db_fetch_cell('SELECT id FROM cdef ORDER BY id LIMIT 1');
	$hash   = get_hash_cdef($cdef);
	$legacy = str_replace('hash_' . get_hash_version('cdef') . $hash, 'hash_' . substr(get_hash_version('cdef'), 0, 2) . $hash, get_item_xml('cdef', $cdef, false));
	$count  = (int) db_fetch_cell('SELECT COUNT(*) FROM cdef');
	library_expect(str_contains($legacy, '<hash_' . substr(get_hash_version('cdef'), 0, 2) . $hash . '>'), true, 'Legacy fixture removes the version from the CDEF hash');
	library_expect(import_xml_data($legacy, false, 1)['cdef'][0]['result'] ?? null, 'success', 'A CDEF exported before hash versions still imports');
	library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM cdef'), $count, 'A legacy CDEF hash resolves to the installed CDEF');

	$template = (int) db_fetch_cell('SELECT graph_template_id FROM graph_template_input GROUP BY graph_template_id ORDER BY graph_template_id LIMIT 1');
	$original = get_item_xml('graph_template', $template, false);
	$invalid  = preg_replace('#<column_name>[^<]*</column_name>#', '<column_name>not_a_graph_column</column_name>', $original, 1);
	library_expect(import_xml_data($invalid, false, 1), false, 'A graph template input naming an unknown column is refused before writing');
	library_expect(library_xml_content(get_item_xml('graph_template', $template, false)), library_xml_content($original), 'A refused graph template import leaves the template unchanged');

	library_expect(preg_match_all('#<(hash_' . get_hash_version('graph_template_item') . '[a-f0-9]{32})>.*?</\1>#s', $original, $items) > 0, true, 'Graph template export lists its items');
	$extraHash = generate_hash();
	$extra     = str_replace($items[1][0], 'hash_' . get_hash_version('graph_template_item') . $extraHash, $items[0][0]);
	$added     = str_replace("\t</items>", $extra . "\n\t</items>", $original);
	$result    = import_xml_data($added, false, 1);
	library_expect(str_starts_with($result['graph_template'][0]['new_items'][0] ?? '', 'New Graph Items, Type: '), true, 'Importing an extra item reports it as new');
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE hash = ? AND local_graph_id = 0', [$extraHash]), 1, 'The extra template item is stored');
	$result = import_xml_data($original, false, 1, true);
	library_expect(str_starts_with($result['graph_template'][0]['orphans'][0] ?? '', 'Removed Orphaned Graph Items, Type: '), true, 'Removing orphans reports the deleted item');
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE hash = ?', [$extraHash]), 0, 'Removing orphans deletes the template item and its graph copies');

	library_expect(library_xml_content(get_item_xml('graph_template', $template, false)), library_xml_content($original), 'Adding and then removing an item round trips to the original template');

	// Drop an item no input references, so the shortened template still passes its input preflight.
	$orphan = db_fetch_row('SELECT gti.graph_template_id, gti.hash FROM graph_templates_item AS gti LEFT JOIN graph_template_input_defs AS gtid ON gtid.graph_template_item_id = gti.id WHERE gti.local_graph_id = 0 AND gti.hash != "" AND gtid.graph_template_item_id IS NULL ORDER BY gti.id LIMIT 1');
	library_expect(cacti_sizeof($orphan) > 0, true, 'Installed templates include an item no input references');
	$orphanXml = get_item_xml('graph_template', (int) $orphan['graph_template_id'], false);
	$key       = 'hash_' . get_hash_version('graph_template_item') . $orphan['hash'];
	$shortened = preg_replace('#\s*<' . $key . '>.*?</' . $key . '>#s', '', $orphanXml);
	library_expect($shortened !== $orphanXml, true, 'The orphan fixture removes one exported item');
	$result = import_xml_data($shortened, false, 1);
	library_expect(str_starts_with($result['graph_template'][0]['orphans'][0] ?? '', 'Found Orphaned Graph Items, Type: '), true, 'Omitting an item reports it as an orphan');
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE hash = ? AND local_graph_id = 0', [$orphan['hash']]), 1, 'A reported orphan is kept unless removal is requested');
	library_expect(library_xml_content(get_item_xml('graph_template', (int) $orphan['graph_template_id'], false)), library_xml_content($orphanXml), 'Reporting an orphan leaves its template unchanged');

	foreach (array_column(db_fetch_assoc('SELECT DISTINCT sqg.snmp_query_id FROM snmp_query_graph_rrd_sv AS sv INNER JOIN snmp_query_graph AS sqg ON sqg.id = sv.snmp_query_graph_id'), 'snmp_query_id') as $query) {
		$before = db_fetch_assoc('SELECT * FROM snmp_query_graph_rrd_sv ORDER BY id');
		$xml    = preg_replace_callback('#<sv_data_source>.*?</sv_data_source>#s', static fn (array $match): string => preg_replace('#<data_template_id>hash_[a-f0-9]{38}</data_template_id>#', '<data_template_id>hash_' . get_hash_version('data_template') . generate_hash() . '</data_template_id>', $match[0]), get_item_xml('data_query', (int) $query, false));
		library_expect(import_xml_data($xml, false, 1) !== false, true, 'A data query with an unknown suggested-value template still imports');
		library_expect(db_fetch_assoc('SELECT * FROM snmp_query_graph_rrd_sv ORDER BY id'), $before, 'Suggested values with an unknown data template are not written');

		break;
	}
}
