<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-m3fh-gxqj-76hq: the SNMPv3 credential device fields (sibling of the
 * snmp_community newline defect) must strip control characters before the
 * values reach the net-snmp command line.
 */

$root = dirname(__DIR__, 4);

test('SNMPv3 credential fields strip control characters (GHSA-m3fh)', function () use ($root) {
	$s = file_get_contents($root . '/lib/api_device.php');

	expect($s)->toContain('(string) form_input_validate($snmp_password')
		->and($s)->toContain('(string) form_input_validate($snmp_priv_passphrase')
		->and($s)->toContain('(string) form_input_validate($snmp_community');
});
