<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Duplicate configured archives must prevent destructive resize recommendations. */
function library_duplicate_profile(int $id): void {
	$original           = (int) db_fetch_cell_prepared('SELECT data_source_profile_id FROM data_template_data WHERE local_data_id = ?', [$id]);
	$profile            = db_fetch_row_prepared('SELECT * FROM data_source_profiles WHERE id = ?', [$original]);
	$profile['id']      = 0;
	$profile['hash']    = bin2hex(random_bytes(16));
	$profile['name']    = 'Duplicate archive fixture';
	$profile['default'] = '';
	$owned              = (int) sql_save($profile, 'data_source_profiles');

	try {
		foreach (db_fetch_assoc_prepared('SELECT consolidation_function_id FROM data_source_profiles_cf WHERE data_source_profile_id = ?', [$original]) as $cf) {
			db_execute_prepared('INSERT INTO data_source_profiles_cf (data_source_profile_id, consolidation_function_id) VALUES (?, ?)', [$owned, $cf['consolidation_function_id']]);
		}
		$archives = db_fetch_assoc_prepared('SELECT * FROM data_source_profiles_rra WHERE data_source_profile_id = ? ORDER BY id', [$original]);
		library_expect(count($archives) > 0, true, 'Source profile supplies real archive definitions');
		$archives[] = $archives[0];

		foreach ($archives as $archive) {
			$archive['id']                     = 0;
			$archive['data_source_profile_id'] = $owned;
			library_expect((int) sql_save($archive, 'data_source_profiles_rra') > 0, true, 'Owned profile retains its archive rows');
		}
		db_execute_prepared('UPDATE data_template_data SET data_source_profile_id = ? WHERE local_data_id = ?', [$owned, $id]);
		$path   = get_data_source_path($id, true);
		$hash   = hash_file('sha256', $path);
		$info   = rrdtool_function_info($id);
		$diff   = rrdtool_cacti_compare($id, $info);
		$errors = array_filter(array_column($diff['rra'] ?? [], 'error'), static fn ($message) => str_contains($message, 'Cacti RRA') && str_contains($message, 'same CF/steps'));
		library_expect(count($errors) > 0, true, 'Duplicate configured archives produce an explicit ambiguity diagnostic');
		library_expect(isset($diff['resize']), false, 'Ambiguous configured archives cannot recommend a resize');
		library_expect(hash_file('sha256', $path), $hash, 'Ambiguity diagnostics preserve stored samples');
	} finally {
		db_execute_prepared('UPDATE data_template_data SET data_source_profile_id = ? WHERE local_data_id = ?', [$original, $id]);

		foreach (['data_source_profiles_rra', 'data_source_profiles_cf'] as $table) {
			db_execute_prepared("DELETE FROM $table WHERE data_source_profile_id = ?", [$owned]);
		}
		db_execute_prepared('DELETE FROM data_source_profiles WHERE id = ?', [$owned]);
	}
}
