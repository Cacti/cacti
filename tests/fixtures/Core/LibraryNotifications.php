<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Observe real process arguments without sending a notification to any device. */
function library_notifications(): void {
	require_once CACTI_PATH_LIBRARY . '/snmpagent.php';
	$directory = sys_get_temp_dir() . '/cacti-notification-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Notification fixture owns its command recorder');
	$previous     = read_config_option('path_snmptrap');
	$notification = 'coverageNativeNotification';
	$mib          = 'COVERAGE-NATIVE-MIB';
	$oid          = '.1.3.6.1.4.1.999999.99';
	$manager      = 0;

	try {
		$script = '#!' . PHP_BINARY . "\n<?php file_put_contents(__DIR__ . '/arguments.json', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR));\n";
		library_expect(file_put_contents($directory . '/record', $script), strlen($script), 'Notification recorder is written completely');
		library_expect(chmod($directory . '/record', 0700), true, 'Recorder is executable');
		set_config_option('path_snmptrap', $directory . '/record');
		library_expect(db_execute_prepared('INSERT INTO snmpagent_cache (oid, name, mib, type) VALUES (?, ?, ?, ?), (?, ?, ?, ?)', [$oid, $notification, $mib, 'notification', $oid . '.1', 'coveragePayload', $mib, 'DisplayString']), true, 'Fixture owns its notification and payload schema');
		library_expect(db_execute_prepared('INSERT INTO snmpagent_cache_notifications (name, mib, attribute, sequence_id) VALUES (?, ?, ?, ?)', [$notification, $mib, 'coveragePayload', 1]), true, 'Fixture registers its required payload');
		$base = ['id'     => 0, 'hostname' => '127.0.0.1', 'description' => 'Native notification recorder', 'disabled' => '0',
			'max_log_size'   => 10, 'snmp_version' => 1, 'snmp_community' => 'fixture community; literal', 'snmp_username' => 'fixture user',
			'snmp_password'  => '', 'snmp_auth_protocol' => 'SHA', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => 'AES',
			'snmp_engine_id' => '8000000001020304', 'snmp_port' => 1162, 'snmp_message_type' => 1];
		$manager = (int) sql_save($base, 'snmpagent_managers');
		library_expect($manager > 0, true, 'Fixture owns its notification receiver');
		library_expect(db_execute_prepared('INSERT INTO snmpagent_managers_notifications (manager_id, notification, mib) VALUES (?, ?, ?)', [$manager, $notification, $mib]), true, 'Receiver subscribes only to the fixture notification');
		library_expect(snmpagent_notification($notification, $mib, [], SNMPAGENT_EVENT_SEVERITY_HIGH), false, 'Missing required varbind prevents process launch');
		library_expect(file_exists($directory . '/arguments.json'), false, 'Invalid varbind cannot launch the recorder');
		library_expect(snmpagent_notification($notification, $mib, [], 999), false, 'Invalid severity prevents process launch');
		$value         = "spaces; dollar \$VALUE and 'single' and \"double\"";
		$expectedValue = str_replace('"', "'", $value);

		foreach ([[1, 1, '', '', null], [2, 1, '', '', null], [2, 2, '', '', null],
			[3, 1, '', '', 'noAuthNoPriv'], [3, 1, 'fixture authentication', '', 'authNoPriv'],
			[3, 2, 'fixture authentication', 'fixture privacy', 'authPriv']] as [$version, $message, $authentication, $privacy, $level]) {
			$settings                         = $base;
			$settings['id']                   = $manager;
			$settings['snmp_version']         = $version;
			$settings['snmp_message_type']    = $message;
			$settings['snmp_password']        = $authentication;
			$settings['snmp_priv_passphrase'] = $privacy;
			library_expect((int) sql_save($settings, 'snmpagent_managers'), $manager, 'Receiver protocol settings persist');
			$before = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM snmpagent_notifications_log WHERE manager_id = ?', [$manager]);
			library_expect(snmpagent_notification($notification, $mib, ['coveragePayload' => $value], SNMPAGENT_EVENT_SEVERITY_HIGH, ['snmp_engine_id' => '8000000005060708']), true, 'Valid notification is dispatched');

			for ($attempt = 0; $attempt < 100 && !is_file($directory . '/arguments.json'); $attempt++) {
				usleep(20000);
			}
			library_expect(is_file($directory . '/arguments.json'), true, 'Notification child supplies its actual arguments before deadline');
			$args = json_decode(file_get_contents($directory . '/arguments.json'), true, 512, JSON_THROW_ON_ERROR);
			unlink($directory . '/arguments.json');
			$expected = match ($version) {
				1 => ['-v', '1', '-c', $base['snmp_community'], '127.0.0.1:1162', $oid, '', '6', '99', ''],
				2 => array_merge(['-v', '2c', '-c', $base['snmp_community']], $message === 2 ? ['-Ci'] : [], ['127.0.0.1:1162', '', $oid]),
				3 => array_merge(['-v', '3', '-e', '8000000005060708'], $message === 2 ? ['-Ci'] : [], ['-u', 'fixture user', '-l', $level],
					$authentication !== '' ? ['-a', 'SHA', '-A', $authentication] : [], $privacy !== '' ? ['-x', 'AES', '-X', $privacy] : [], ['127.0.0.1:1162', '', $oid]),
			};
			library_expect($args, array_merge($expected, [$oid . '.1', 's', $expectedValue]), 'Protocol arguments retain positional empties and literal payload bytes');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM snmpagent_notifications_log WHERE manager_id = ?', [$manager]), $before + 1, 'Dispatched notification records exactly one receiver event');
		}
		db_execute_prepared('UPDATE snmpagent_managers SET disabled = 1 WHERE id = ?', [$manager]);
		library_expect(snmpagent_notification($notification, $mib, ['coveragePayload' => $value], SNMPAGENT_EVENT_SEVERITY_HIGH), false, 'A disabled receiver cannot receive a notification');
		library_expect(file_exists($directory . '/arguments.json'), false, 'Disabled receiver cannot launch a notification process');
		library_expect(snmpagent_notification($notification, $mib, ['coveragePayload' => $value], SNMPAGENT_EVENT_SEVERITY_MEDIUM), false, 'Lower-severity event without a receiver is not dispatched');
		library_expect($GLOBALS['config']['snmpagent']['notifications']['ignore'][$notification], 1, 'Repeated low-severity notices without receivers are suppressed');
	} finally {
		unset($GLOBALS['config']['snmpagent']['notifications']['ignore'][$notification]);

		if ($previous === null) {
			db_execute_prepared('DELETE FROM settings WHERE name = ?', ['path_snmptrap']);
			unset($GLOBALS['config'][OPTIONS_CLI]['path_snmptrap']);
		} else {
			set_config_option('path_snmptrap', $previous);
		}
		db_execute_prepared('DELETE FROM snmpagent_notifications_log WHERE manager_id = ?', [$manager]);
		db_execute_prepared('DELETE FROM snmpagent_managers_notifications WHERE manager_id = ?', [$manager]);
		db_execute_prepared('DELETE FROM snmpagent_managers WHERE id = ?', [$manager]);
		db_execute_prepared('DELETE FROM snmpagent_cache_notifications WHERE mib = ?', [$mib]);
		db_execute_prepared('DELETE FROM snmpagent_cache WHERE mib = ?', [$mib]);

		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
}
