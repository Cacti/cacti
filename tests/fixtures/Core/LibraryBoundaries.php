<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** A configured query bound is resolved before RRDtool receives its create command. */
function library_rrd_bounds(int $device, int $query, array $sources): void {
	require_once CACTI_PATH_LIBRARY . '/boost.php';

	foreach ($sources as $source) {
		$id    = (int) $source['local_data_id'];
		$rows  = db_fetch_assoc_prepared('SELECT id, rrd_minimum, rrd_maximum, data_source_type_id FROM data_template_rrd WHERE local_data_id = ?', [$id]);
		$index = db_fetch_cell_prepared('SELECT snmp_index FROM data_local WHERE id = ?', [$id]);
		$limit = db_fetch_cell_prepared('SELECT field_value FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? AND snmp_index = ? AND field_name = ?', [$device, $query, $index, 'ifType']);
		library_expect(is_numeric($limit) && (int) $limit > 0, true, 'Query-bound fixture uses the real numeric interface type');

		try {
			db_execute_prepared('UPDATE data_template_rrd SET rrd_minimum = ?, rrd_maximum = ? WHERE local_data_id = ?', ['0', '|query_ifType|', $id]);

			foreach (['rrdtool_function_create', 'boost_rrdtool_function_create'] as $create) {
				$command = $create($id, true, null);
				library_expect(is_string($command) && str_contains($command, ':0:' . $limit), true, 'RRD creation resolves a non-speed query bound');
				library_expect(str_contains($command, '|query_'), false, 'No unresolved query token reaches RRDtool');
			}
			db_execute_prepared('UPDATE data_template_rrd SET rrd_minimum = ?, rrd_maximum = ?, data_source_type_id = 1 WHERE local_data_id = ?', ['10', '5', $id]);
			$command = rrdtool_function_create($id, true, null);
			library_expect(str_contains($command, ':10:U'), true, 'Reversed gauge bounds use an unknown upper bound');
			db_execute_prepared('UPDATE data_template_rrd SET rrd_minimum = ?, rrd_maximum = ? WHERE local_data_id = ?', ['invalid', 'U', $id]);
			$command = rrdtool_function_create($id, true, null);
			library_expect(str_contains($command, ':U:U') && !str_contains($command, 'invalid'), true, 'Malformed stored bounds become unknown before reaching RRDtool');
		} finally {
			foreach ($rows as $row) {
				db_execute_prepared('UPDATE data_template_rrd SET rrd_minimum = ?, rrd_maximum = ?, data_source_type_id = ? WHERE id = ?', [$row['rrd_minimum'], $row['rrd_maximum'], $row['data_source_type_id'], $row['id']]);
			}
		}
	}
}

/** Enrollment persists a new secret but requires a valid code before enabling 2FA. */
function library_two_factor(): void {
	$session = $_SESSION;
	$server  = $_SERVER;
	$user    = (int) sql_save(['id' => 0, 'username' => 'coverage_2fa_' . bin2hex(random_bytes(6)), 'realm' => 0, 'enabled' => 'on'], 'user_auth');
	library_expect($user > 0, true, 'Two-factor fixture owns its account');

	try {
		$_SESSION[SESS_USER_ID] = $user;
		$_SERVER['HTTP_HOST']   = 'cacti.example.invalid';
		$result                 = json_decode(enable_2fa($user), true, 512, JSON_THROW_ON_ERROR);
		library_expect($result['status'], 200, 'Two-factor enrollment returns its verification challenge');
		$stored = db_fetch_row_prepared('SELECT username, tfa_secret, tfa_enabled FROM user_auth WHERE id = ?', [$user]);
		library_expect(strlen($stored['tfa_secret']) > 0, true, 'Enrollment persists a secret');
		library_expect($stored['tfa_enabled'], '', 'Enrollment alone does not enable two-factor authentication');
		library_expect(str_contains(urldecode($result['link']), $stored['username'] . '@cacti.example.invalid'), true, 'Enrollment QR link identifies the intended account and installation');
		$authenticator = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
		$code          = $authenticator->getCode($stored['tfa_secret']);
		$result        = json_decode(verify_2fa($user, $code), true, 512, JSON_THROW_ON_ERROR);
		library_expect($result['status'], 200, 'A valid current code completes enrollment');
		library_expect(db_fetch_cell_prepared('SELECT tfa_enabled FROM user_auth WHERE id = ?', [$user]), 'on', 'Verified enrollment reaches persisted state');
		library_expect(json_decode(disable_2fa($user), true)['status'], 200, 'Disabling two-factor authentication reports success');
		library_expect(db_fetch_row_prepared('SELECT tfa_secret, tfa_enabled FROM user_auth WHERE id = ?', [$user]), ['tfa_secret' => '', 'tfa_enabled' => ''], 'Disabling removes both the secret and enabled flag');
	} finally {
		user_remove($user);
		$_SESSION = $session;
		$_SERVER  = $server;
	}
}
