<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$deviceSource  = file_get_contents(dirname(__DIR__, 4) . '/lib/api_device.php');
$managerSource = file_get_contents(dirname(__DIR__, 4) . '/managers.php');

test('SNMP protocol validators match complete values', function () use ($deviceSource, $managerSource) {
	foreach(array($deviceSource, $managerSource) as $source) {
		expect($source)->toContain('^(?:\[None\]|MD5|SHA|SHA224|SHA256|SHA384|SHA512)$')
			->and($source)->toContain('^(?:\[None\]|DES|AES|AES128|AES192|AES192C|AES256|AES256C)$')
			->and($source)->not->toContain('SHA392');
	}
});
