<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$source = file_get_contents(__DIR__ . '/../../../../cli/change_device.php');

test('security level option is parsed and documented', function () use ($source) {
	expect($source)->toContain("case '--security-level':");
	expect($source)->toContain('noAuthNoPriv|authNoPriv|authPriv');
	expect($source)->toContain('[None]|MD5|SHA|SHA224|SHA256|SHA384|SHA512');
	expect($source)->toContain('[None]|DES|AES|AES128|AES192|AES192C|AES256|AES256C');
});

test('lower security levels clear protocols and secrets they do not use', function () use ($source) {
	$noAuth = strpos($source, "case 'noauthnopriv':");
	$auth   = strpos($source, "case 'authnopriv':", $noAuth);
	$priv   = strpos($source, "case 'authpriv':", $auth);

	expect($noAuth)->not->toBeFalse();
	expect($auth)->not->toBeFalse();
	expect($priv)->not->toBeFalse();

	$noAuthBlock = substr($source, $noAuth, $auth - $noAuth);
	$authBlock   = substr($source, $auth, $priv - $auth);

	expect($noAuthBlock)->toContain("\$host['snmp_auth_protocol']   = '[None]';");
	expect($noAuthBlock)->toContain("\$host['snmp_password']        = '';");
	expect($noAuthBlock)->toContain("\$host['snmp_priv_passphrase'] = '';");
	expect($authBlock)->toContain("\$host['snmp_priv_protocol']   = '[None]';");
	expect($authBlock)->toContain("\$host['snmp_priv_passphrase'] = '';");
});

test('SNMPv3 password is only required with an auth protocol', function () use ($source) {
	expect($source)->toContain("\$host['snmp_auth_protocol'] != '[None]' && \$host['snmp_password'] == ''");
	expect($source)->not->toContain("\$host['snmp_username'] == '' || \$host['snmp_password'] == ''");
});

test('security level is rejected for SNMP versions below 3', function () use ($source) {
	expect($source)->toContain('--security-level is only valid with SNMP version 3');
});
