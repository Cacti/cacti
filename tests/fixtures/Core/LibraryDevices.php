<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Duplicate, export and remove a device template, restoring packaging globals afterwards. */
function library_device_templates(int $templateId): void {
	global $export_errors;
	$packagingState = [$export_errors, $GLOBALS['debug'] ?? false, $GLOBALS['package_file'] ?? ''];
	require_once CACTI_PATH_LIBRARY . '/package.php';
	require_once __DIR__ . '/LibraryForms.php';
	$original = db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', [$templateId]);
	$suffix   = ' library fixture';
	$cloneXml = $cloneScript = false;
	$before   = (int) db_fetch_cell('SELECT COUNT(*) FROM host_template');
	$missing  = api_clone_device_template_check_for_errors(0, 'Missing fixture', '', '', '', '', '', '', $suffix, $cloneXml, $cloneScript);
	library_expect(count($missing['errors']), 1, 'Missing source template is rejected before cloning');

	foreach ([['all', '', 'all', '', 'all', ''], ['all', 'all', 'all', 'all', 'all', 'all'], ['invalid', '', 'invalid', '', 'invalid', '']] as $selection) {
		ob_start();

		try {
			$result = api_clone_device_template_check_for_errors($templateId, 'Library template copy', ...[...$selection, &$suffix, &$cloneXml, &$cloneScript]);
		} finally {
			ob_end_clean();
		}
		library_expect(count($result['errors']) === 0, $selection === ['all', '', 'all', '', 'all', ''], 'Clone validation distinguishes valid inclusion from conflicts and invalid identifiers');
		library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM host_template'), $before, 'Clone validation creates no template');
	}
	ob_start();

	try {
		$copy = api_clone_device_template($templateId, 'Library template copy', 'all', '', 'all', '', 'all', '', $suffix, false, false);
	} finally {
		ob_end_clean();
	}
	library_expect(is_int($copy) && $copy > 0 && $copy !== $templateId, true, 'Including existing objects creates a distinct device template');
	$device          = 0;
	$ownedKeys       = [];
	$hadMetadata     = file_exists(CACTI_PATH_PKI . '/package.db');
	$hadKeyDirectory = is_dir(CACTI_PATH_PKI);

	try {
		$stored = db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', [$copy]);
		library_expect($stored['name'], 'Library template copy', 'Cloned template retains the requested name');
		library_expect($stored['hash'] !== $original['hash'], true, 'Cloned template has a distinct export identity');

		foreach (['host_template_graph' => 'graph_template_id', 'host_template_snmp_query' => 'snmp_query_id'] as $table => $column) {
			$expected = db_fetch_assoc_prepared("SELECT $column FROM $table WHERE host_template_id = ? ORDER BY $column", [$templateId]);
			library_expect(db_fetch_assoc_prepared("SELECT $column FROM $table WHERE host_template_id = ? ORDER BY $column", [$copy]), $expected, 'Include mode shares existing ' . $column . ' identities');
		}
		$preview = library_form_dom(static function () use ($copy): void { print get_package_contents('host_template', $copy); });
		library_expect(str_contains($preview->document->textContent, 'Resource Files'), true, 'Package preview lists dependent query resources');
		library_expect($preview->evaluate('count(//i[contains(@class,"deviceUp")])') > 0, true, 'Package preview verifies existing dependent files');
		$missingFile = CACTI_PATH_SCRIPTS . '/coverage-missing-' . bin2hex(random_bytes(8)) . '.php';
		library_expect(process_paths($missingFile, [], true), [], 'Missing script is excluded from package contents');
		library_expect(isset($_SESSION[SESS_MESSAGES]['missing_' . $missingFile]), true, 'Missing dependency supplies a packaging error');
		$device = library_save_device(['description' => 'Template synchronization fixture', 'snmp_version' => 0]);
		library_expect($device > 0, true, 'Template synchronization uses an owned disabled device');
		api_device_update_host_template($device, $copy);
		library_expect((int) db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?', [$device]), $copy, 'Changing device template persists the new identity');

		foreach (['host_graph' => ['host_template_graph', 'graph_template_id'], 'host_snmp_query' => ['host_template_snmp_query', 'snmp_query_id']] as $table => [$mapping, $column]) {
			$expected = db_fetch_assoc_prepared("SELECT $column FROM $mapping WHERE host_template_id = ? ORDER BY $column", [$copy]);
			library_expect(db_fetch_assoc_prepared("SELECT $column FROM $table WHERE host_id = ? ORDER BY $column", [$device]), $expected, 'Synchronization supplies template ' . $column . ' associations');
		}
		api_device_update_host_template($device, $copy);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$device]), (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_template_graph WHERE host_template_id = ?', [$copy]), 'Repeated synchronization does not duplicate graph mappings');

		// This disposable install has no packaging identity. Create keys only at
		// exclusively owned paths, and remove them after validating the archive.
		if (!$hadKeyDirectory) {
			library_expect(mkdir(CACTI_PATH_PKI, 0700, true), true, 'Disposable packaging directory is created');
		}
		$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		library_expect($key !== false, true, 'Disposable package signing key is generated');
		library_expect(openssl_pkey_export($key, $private), true, 'Disposable signing key can be exported');
		$public = openssl_pkey_get_details($key)['key'];

		foreach (['package.key' => $private, 'package.pem' => $public] as $filename => $bytes) {
			$path   = CACTI_PATH_PKI . '/' . $filename;
			$stream = fopen($path, 'x');
			library_expect(is_resource($stream), true, 'Signing fixture owns its new key file');
			$ownedKeys[] = $path;

			try {
				library_expect(chmod($path, 0600), true, 'Disposable key is private');
				library_expect(fwrite($stream, $bytes), strlen($bytes), 'Complete disposable key is stored');
			} finally {
				fclose($stream);
			}
		}
		$export_errors = false;
		library_expect(api_device_template_archive($copy, 'Fixture archive note'), true, 'Device template archive is stored');
		$archive = db_fetch_row_prepared('SELECT * FROM host_template_archive WHERE host_template_id = ? ORDER BY id DESC LIMIT 1', [$copy]);
		library_expect($archive['archive_note'], 'Fixture archive note', 'Archive retains its operator note');
		library_expect($archive['name'], $stored['name'], 'Archive records its template name');
		library_expect($archive['hash'], $stored['hash'], 'Archive retains template identity');

		foreach ([base64_decode($archive['archive'], true), api_device_template_archive_for_export($copy)] as $bytes) {
			library_expect(is_string($bytes) && strlen($bytes) > 100, true, 'Archive contains a compressed package');
			$xml = gzdecode($bytes);
			library_expect(is_string($xml), true, 'Package decompresses');
			$document = simplexml_load_string($xml);
			library_expect($document !== false, true, 'Package is valid XML');
			library_expect((string) $document->info->name, $stored['name'], 'Package metadata identifies the cloned template');
			library_expect(count($document->files->file) > 0, true, 'Package carries its dependent files');

			foreach ($document->files->file as $file) {
				library_expect(openssl_verify(base64_decode((string) $file->data, true), base64_decode((string) $file->filesignature, true), $public, OPENSSL_ALGO_SHA256), 1, 'Each packaged file has a valid signature from the disposable signer');
			}
		}
		$wrongKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		library_expect($wrongKey !== false, true, 'Signing failure fixture owns a second key');
		$wrongPublic  = openssl_pkey_get_details($wrongKey)['key'];
		$archiveCount = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_template_archive WHERE host_template_id = ?', [$copy]);

		try {
			library_expect(file_put_contents(CACTI_PATH_PKI . '/package.pem', $wrongPublic), strlen($wrongPublic), 'Fixture reproduces a mismatched signing certificate');

			foreach (['archive', 'export'] as $operation) {
				unset($_SESSION[SESS_MESSAGES]['package_error_' . $copy]);
				$result = $operation === 'archive' ? api_device_template_archive($copy, 'Rejected signature') : api_device_template_archive_for_export($copy);
				library_expect($result, false, 'Mismatched signing identity cannot publish an archive');
				library_expect(isset($_SESSION[SESS_MESSAGES]['package_error_' . $copy]), true, 'Signing failure reaches the operator');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_template_archive WHERE host_template_id = ?', [$copy]), $archiveCount, 'Failed signing preserves the existing archive history');
			}
		} finally {
			library_expect(file_put_contents(CACTI_PATH_PKI . '/package.pem', $public), strlen($public), 'Fixture restores the matching public key');
		}
		library_expect(is_string(api_device_template_archive_for_export($copy)), true, 'Matching signing identity recovers a valid export');
		// A metadata store that rejects writes must stop both archive and export.
		$metadata = new SQLite3(CACTI_PATH_PKI . '/package.db');
		$warning  = false;
		$prior    = set_error_handler(static function (int $level, string $message, string $file, int $line) use (&$warning, &$prior): bool {
			if ($level === E_WARNING && str_contains($message, 'coverage metadata rejection')) {
				$warning = true;

				return true;
			}

			return $prior ? $prior($level, $message, $file, $line) : false;
		});

		try {
			library_expect($metadata->exec("CREATE TRIGGER coverage_reject BEFORE INSERT ON package BEGIN SELECT RAISE(ABORT, 'coverage metadata rejection'); END"), true, 'Metadata rejection is armed');

			foreach (['archive', 'export'] as $operation) {
				unset($_SESSION[SESS_MESSAGES]['export_failed_' . $copy]);
				$result = $operation === 'archive' ? api_device_template_archive($copy, 'Rejected metadata') : api_device_template_archive_for_export($copy);
				library_expect($result, false, 'Rejected metadata cannot publish an archive');
				library_expect($_SESSION[SESS_MESSAGES]['export_failed_' . $copy]['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Metadata failure reaches the operator');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_template_archive WHERE host_template_id = ?', [$copy]), $archiveCount, 'Metadata failure preserves the archive history');
			}
			library_expect($warning, true, 'Metadata failure comes from the real store');
		} finally {
			restore_error_handler();
			$metadata->exec('DROP TRIGGER IF EXISTS coverage_reject');
			$metadata->close();
		}
		library_expect(api_device_template_archive(0, 'Missing'), false, 'Missing template cannot be archived');
		library_expect(api_device_template_archive_for_export(0), false, 'Missing template cannot be exported');
		library_expect(db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', [$templateId]), $original, 'Clone, sync, and archive preserve the source template');
	} finally {
		[$export_errors, $GLOBALS['debug'], $GLOBALS['package_file']] = $packagingState;

		foreach ($ownedKeys as $path) {
			unlink($path);
		}

		if (!$hadMetadata && file_exists(CACTI_PATH_PKI . '/package.db')) {
			unlink(CACTI_PATH_PKI . '/package.db');
		}

		if (!$hadKeyDirectory && is_dir(CACTI_PATH_PKI)) {
			rmdir(CACTI_PATH_PKI);
		}

		if ($device > 0) {
			api_device_remove($device);
		}

		foreach (['host_template_archive', 'host_template_graph', 'host_template_snmp_query'] as $table) {
			db_execute_prepared("DELETE FROM $table WHERE host_template_id = ?", [$copy]);
		}
		db_execute_prepared('DELETE FROM host_template WHERE id = ?', [$copy]);
	}
}

/** Run a clone check or clone with its console narration captured. */
function library_clone_quietly(callable $operation): mixed {
	ob_start();

	try {
		return $operation();
	} finally {
		ob_end_clean();
	}
}

/** Validate and clone device templates whose queries, scripts and names collide with or escape the filesystem. */
function library_device_template_clones(int $deviceTemplate, int $interfaceQuery): void {
	$tables = ['host_template', 'snmp_query', 'snmp_query_graph', 'snmp_query_graph_rrd_sv', 'snmp_query_graph_sv', 'graph_templates', 'graph_templates_graph',
		'graph_templates_item', 'graph_template_input', 'data_template', 'data_template_data', 'data_template_rrd', 'data_input', 'data_input_fields'];
	$marks = [];

	foreach ($tables as $table) {
		$marks[$table] = (int) db_fetch_cell('SELECT COALESCE(MAX(id), 0) FROM ' . $table);
	}
	$taskItems = db_fetch_assoc('SELECT id, task_item_id FROM graph_templates_item WHERE local_graph_id = 0');
	$inputs    = db_fetch_assoc('SELECT id, input_string FROM data_input');
	$owned     = [];
	$link      = CACTI_PATH_BASE . '/coverage-readonly-' . bin2hex(random_bytes(6));
	$input     = (int) db_fetch_cell_prepared('SELECT data_input_id FROM snmp_query WHERE id = ?', [$interfaceQuery]);
	$suffix    = ' (clone fixture)';

	/** Register a query that only the fixture template uses. */
	$query = static function (string $xmlPath) use ($input): int {
		$id = (int) sql_save(['id' => 0, 'hash' => generate_hash(), 'xml_path' => $xmlPath, 'name' => 'Clone fixture query ' . bin2hex(random_bytes(4)), 'description' => '', 'data_input_id' => $input], 'snmp_query');
		library_expect($id > 0, true, 'Clone fixture query exists');

		return $id;
	};
	/** Write a query definition inside the Cacti tree that points its script at the given path. */
	$definition = static function (string $script) use (&$owned): string {
		$path = CACTI_PATH_BASE . '/resource/coverage-clone-' . bin2hex(random_bytes(6)) . '.xml';
		library_expect(file_put_contents($path, '<interface><name>Clone fixture</name><script_path>' . $script . '</script_path><arg_index>index</arg_index><fields></fields></interface>') > 0, true, 'Clone fixture writes its query definition');
		$owned[] = $path;

		return $path;
	};
	/** Point the fixture device template at exactly one query. */
	$attach = static function (int $template, int $query): void {
		db_execute_prepared('DELETE FROM host_template_snmp_query WHERE host_template_id = ?', [$template]);
		library_expect(db_execute_prepared('INSERT INTO host_template_snmp_query (host_template_id, snmp_query_id) VALUES (?, ?)', [$template, $query]), true, 'Clone fixture template owns its query');
	};
	/** Validate the fixture template and return its messages. */
	$check = static function (int $template, string $cloneDq, string $cloneDt, bool $xml, bool $script) use (&$suffix): array {
		return library_clone_quietly(static fn () => api_clone_device_template_check_for_errors($template, '', '', '', '', $cloneDq, '', $cloneDt, $suffix, $xml, $script));
	};
	/** Report whether any message contains the expected text. */
	$contains = static fn (array $messages, string $needle): bool => count(array_filter($messages, static fn (string $message): bool => str_contains($message, $needle))) > 0;

	$structure = $GLOBALS['struct_data_source'];

	try {
		$template = (int) sql_save(['id' => 0, 'hash' => generate_hash(), 'name' => 'Clone fixture template ' . bin2hex(random_bytes(4))], 'host_template');
		library_expect($template > 0, true, 'Clone fixture template exists');
		$graphs = db_fetch_assoc_prepared('SELECT graph_template_id FROM host_template_graph WHERE host_template_id = ?', [$deviceTemplate]);
		library_expect(count($graphs) > 0, true, 'Source device template supplies graph templates');

		foreach ($graphs as $graph) {
			db_execute_prepared('INSERT INTO host_template_graph (host_template_id, graph_template_id) VALUES (?, ?)', [$template, $graph['graph_template_id']]);
		}

		// Query definition and script locations are validated before any copy. The
		// container mounts /proc/sys read-only, so even root sees an unwritable directory.
		foreach ([
			[CACTI_PATH_BASE . '/resource/coverage-missing-' . bin2hex(random_bytes(6)) . '.xml', true, false, 'Query XML Base path'],
			['/proc/sys/kernel/hostname', true, false, 'Query XML Base directory'],
			[$definition(CACTI_PATH_BASE . '/scripts/coverage-missing-' . bin2hex(random_bytes(6)) . '.php'), false, true, 'Query Script Base path'],
			[$definition('/proc/sys/kernel/hostname'), false, true, 'Query Script Base directory'],
		] as [$xmlPath, $xml, $script, $needle]) {
			$attach($template, $query($xmlPath));
			$result = $check($template, 'all', '', $xml, $script);
			library_expect($contains($result['errors'], 'FATAL: Data ' . $needle), true, 'Clone validation rejects an unusable Data ' . $needle);
		}
		db_execute_prepared('DELETE FROM host_template_snmp_query WHERE host_template_id = ?', [$template]);

		$objects   = api_clone_device_template_get_objects($template);
		$dataInput = (int) reset($objects['data_templates'])['data_input_id'];
		library_expect($dataInput > 0, true, 'Template data sources use a data input method');
		library_expect(symlink('/proc/sys', $link), true, 'Clone fixture links a read-only directory into the Cacti tree');

		foreach ([[CACTI_PATH_BASE . '/scripts', 'path'], [$link . '/kernel/hostname', 'directory']] as [$scriptPath, $needle]) {
			db_execute_prepared('UPDATE data_input SET input_string = ? WHERE id = ?', ['php ' . $scriptPath . ' <index>', $dataInput]);
			$result = $check($template, '', 'all', false, true);
			library_expect($contains($result['errors'], 'FATAL: Data Template Script Base ' . $needle), true, 'Clone validation rejects an unusable data template script ' . $needle);
		}

		$result = $check($template, '', '', false, true);
		library_expect($contains($result['warnings'], 'Ignoring --clone-script'), true, 'Script cloning without a cloned object is ignored with a warning');

		$name      = db_fetch_cell_prepared('SELECT name FROM data_input WHERE id = ?', [$dataInput]);
		$collision = (int) sql_save(['id' => 0, 'hash' => generate_hash(), 'name' => $name . $suffix, 'input_string' => '', 'type_id' => 1], 'data_input');
		library_expect($collision > 0, true, 'A data input method already uses the cloned name');
		$result = $check($template, '', 'all', false, false);
		library_expect($contains($result['warnings'], 'Data Input Method \'' . $name . $suffix . '\' already exists'), true, 'Clone validation warns about a data input name collision');

		$queryGraph = (int) db_fetch_cell_prepared('SELECT graph_template_id FROM snmp_query_graph WHERE snmp_query_id = ? ORDER BY id LIMIT 1', [$interfaceQuery]);
		$bare       = $query('<path_cacti>/resource/snmp_queries/interface.xml');
		library_expect((int) sql_save(['id' => 0, 'hash' => generate_hash(), 'snmp_query_id' => $bare, 'name' => 'Clone fixture graph', 'graph_template_id' => $queryGraph], 'snmp_query_graph') > 0, true, 'Fixture query maps a graph template');
		$attach($template, $bare);
		$graphName = db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', [$queryGraph]);
		library_expect((int) sql_save(['id' => 0, 'hash' => generate_hash(), 'name' => $graphName . $suffix], 'graph_templates') > 0, true, 'A graph template already uses the cloned name');
		$result = $check($template, 'all', '', false, false);
		library_expect($contains($result['warnings'], 'for Data Query'), true, 'Clone validation warns about a query graph template name collision');

		library_expect(library_clone_quietly(static fn () => api_clone_device_template($template, '', 'all', '', '', '', '', '', $suffix, false, false)), false, 'Graph templates cannot be cloned without their data templates');

		// Script copies fail when the source cannot be copied or every candidate name is taken.
		$directory = CACTI_PATH_BASE . '/resource/coverage-clone-' . bin2hex(random_bytes(6)) . '.php';
		library_expect(mkdir($directory), true, 'Clone fixture owns an uncopyable script path');
		$owned[] = $directory;
		$script  = CACTI_PATH_BASE . '/resource/coverage-script-' . bin2hex(random_bytes(6)) . '.php';

		// Candidate names keep the extension separator, as api_clone_get_unique_filename() builds them.
		foreach (array_merge([$script], array_map(static fn (int $i): string => substr($script, 0, -3) . '_' . substr('0' . $i, -2) . '.php', range(1, 19))) as $candidate) {
			library_expect(file_put_contents($candidate, '<?php') > 0, true, 'Clone fixture occupies a candidate script name');
			$owned[] = $candidate;
		}

		foreach ([[$directory, 'Unable to copy Script Base'], [$script, 'Unable to allocate a unique script filename']] as [$source, $needle]) {
			$attach($template, $query($definition($source)));
			$warning = '';
			$prior   = set_error_handler(static function (int $level, string $message, string $file, int $line) use (&$warning, &$prior): bool {
				if ($level === E_WARNING && str_contains($message, 'copy(')) {
					$warning = $message;

					return true;
				}

				return $prior ? $prior($level, $message, $file, $line) : false;
			});

			try {
				$output = '';
				ob_start();

				try {
					$cloned = api_clone_device_template($template, '', '', '', '', 'all', '', '', $suffix, true, true, true);
				} finally {
					$output = ob_get_clean();
				}
			} finally {
				restore_error_handler();
			}
			library_expect($cloned, false, 'Script copy failure stops the clone');
			library_expect(str_contains($output, $needle), true, 'Script copy failure explains the cause');

			foreach (glob(CACTI_PATH_BASE . '/resource/coverage-clone-*_01.xml') as $copy) {
				$owned[] = $copy;
			}
		}

		// A real query clone duplicates its graph and data templates, and reports
		// query graphs that have no data template mapping.
		$source = data_query_duplicate($interfaceQuery, 'Clone fixture interface ' . bin2hex(random_bytes(4)));
		library_expect(is_int($source) && $source > 0, true, 'Clone fixture owns a query copy');
		$keep = (int) db_fetch_cell_prepared('SELECT MIN(id) FROM snmp_query_graph WHERE snmp_query_id = ?', [$source]);
		db_execute_prepared('DELETE FROM snmp_query_graph WHERE snmp_query_id = ? AND id != ?', [$source, $keep]);
		$unmapped = (int) db_fetch_cell_prepared('SELECT id FROM graph_templates WHERE id NOT IN (SELECT graph_template_id FROM snmp_query_graph WHERE snmp_query_id = ?) ORDER BY id LIMIT 1', [$source]);
		library_expect((int) sql_save(['id' => 0, 'hash' => generate_hash(), 'snmp_query_id' => $source, 'name' => 'Unmapped clone graph', 'graph_template_id' => $unmapped], 'snmp_query_graph') > 0, true, 'Fixture query has a graph without a data template');
		$attach($template, $source);
		$output = '';
		$debug  = $GLOBALS['debug'] ?? false;
		// Unmapped query graphs are only narrated in debug mode.
		$GLOBALS['debug'] = true;
		ob_start();

		try {
			$cloned = api_clone_device_template($template, 'Clone fixture result', '', '', '', 'all', '', '', $suffix, false, false, true);
		} finally {
			$output           = ob_get_clean();
			$GLOBALS['debug'] = $debug;
		}
		library_expect(is_int($cloned) && $cloned > $marks['host_template'], true, 'Query clone creates a new device template');
		$clonedQuery = (int) db_fetch_cell_prepared('SELECT snmp_query_id FROM host_template_snmp_query WHERE host_template_id = ?', [$cloned]);
		library_expect($clonedQuery > $source, true, 'Clone owns a distinct data query');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM snmp_query_graph WHERE snmp_query_id = ? AND graph_template_id > ?', [$clonedQuery, $marks['graph_templates']]), 2, 'Each query graph points at a cloned graph template');
		library_expect(str_contains($output, 'Cloning Data Query Graph Template'), true, 'Query clone narrates graph template duplication');
		$inputDefs = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_template_input_defs AS gtid INNER JOIN graph_template_input AS gti ON gti.id = gtid.graph_template_input_id WHERE gti.graph_template_id > ?', [$marks['graph_templates']]);
		library_expect($inputDefs > 0, true, 'Cloned graph templates keep their item input mappings');
		library_expect(str_contains($output, 'not mapped to a Data Template'), true, 'Unmapped query graph is reported');
	} finally {
		// Duplicating a data template drops the path field from the shared form structure.
		$GLOBALS['struct_data_source'] = $structure;

		foreach ($inputs as $row) {
			db_execute_prepared('UPDATE data_input SET input_string = ? WHERE id = ?', [$row['input_string'], $row['id']]);
		}

		foreach (['host_template_graph' => 'host_template', 'host_template_snmp_query' => 'host_template'] as $table => $owner) {
			db_execute_prepared('DELETE FROM ' . $table . ' WHERE host_template_id > ?', [$marks[$owner]]);
		}
		db_execute_prepared('DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id > ?', [$marks['snmp_query_graph']]);
		db_execute_prepared('DELETE FROM graph_template_input_defs WHERE graph_template_input_id > ?', [$marks['graph_template_input']]);
		db_execute_prepared('DELETE FROM data_input_data WHERE data_template_data_id > ?', [$marks['data_template_data']]);

		foreach ($tables as $table) {
			db_execute_prepared('DELETE FROM ' . $table . ' WHERE id > ?', [$marks[$table]]);
		}

		foreach ($taskItems as $row) {
			db_execute_prepared('UPDATE graph_templates_item SET task_item_id = ? WHERE id = ?', [$row['task_item_id'], $row['id']]);
		}

		if (is_link($link)) {
			unlink($link);
		}

		foreach (array_reverse($owned) as $path) {
			if (is_dir($path)) {
				rmdir($path);
			} elseif (file_exists($path)) {
				unlink($path);
			}
		}
	}
}

/** Reject downloads that select nothing exportable, and fail cleanly when no private staging directory can be created. */
function library_device_template_downloads(): void {
	$session = $_SESSION;

	try {
		foreach ([[['0', 'x', '-1'], 'invalid_device_download_ids'], [[(string) PHP_INT_MAX], 'device_download_empty']] as [$ids, $message]) {
			$_SESSION[SESS_MESSAGES] = [];
			ob_start();

			try {
				api_device_template_download('templates', $ids);
			} finally {
				library_expect(ob_get_clean(), '', 'Rejected download sends no archive');
			}
			library_expect($_SESSION[SESS_MESSAGES][$message]['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Download reports ' . $message);
		}
	} finally {
		$_SESSION = $session;
	}
	$directory = sys_get_temp_dir() . '/cacti-download-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Download fixture owns its evidence directory');
	$source = <<<'PHP'
require 'tests/Helpers/RequestCoverage.php';
require 'include/global.php';
require_once CACTI_PATH_LIBRARY . '/api_device.php';
set_error_handler(static fn (int $level, string $message): bool => $level === E_WARNING && str_contains($message, 'mkdir('));
api_device_template_download('templates', [$argv[1]]);
print json_encode($_SESSION[SESS_MESSAGES] ?? [], JSON_THROW_ON_ERROR);
PHP;
	$environment                          = getenv();
	$environment['CACTI_PR_RAW_COVERAGE'] = $directory;
	$environment['XDEBUG_MODE']           = 'coverage';
	$template                             = (string) db_fetch_cell('SELECT id FROM host_template ORDER BY id LIMIT 1');

	try {
		// A read-only temporary directory reproduces a staging area that cannot be created.
		$process = proc_open([PHP_BINARY, '-d', 'sys_temp_dir=/proc/sys/kernel', '-r', $source, $template], [0 => ['pipe', 'r'],
			1                                                                                                     => ['file', $directory . '/output.txt', 'w'], 2 => ['file', $directory . '/error.txt', 'w']], $pipes, CACTI_PATH_BASE, $environment);
		library_expect(is_resource($process), true, 'Download probe starts in its own process');
		fclose($pipes[0]);
		library_expect(proc_close($process), 0, 'Download probe completes: ' . file_get_contents($directory . '/error.txt'));
		$messages = json_decode(file_get_contents($directory . '/output.txt'), true, 512, JSON_THROW_ON_ERROR);
		library_expect($messages['device_download_temp_failed']['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Unwritable staging reports a download error');
		$reports = glob($directory . '/*.json');
		library_expect(count($reports), 1, 'Download probe produces coverage');
		library_import_coverage($reports);
	} finally {
		foreach (glob($directory . '/*') as $path) {
			unlink($path);
		}
		rmdir($directory);
	}
}
