<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_device_templates(int $templateId): void {
	global $export_errors;
	$packagingState = [$export_errors, $GLOBALS['debug'] ?? false, $GLOBALS['package_file'] ?? ''];
	require_once CACTI_PATH_LIBRARY . '/package.php';
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
