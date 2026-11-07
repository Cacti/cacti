<?php

function hsrp_query_fixture(): SimpleXMLElement {
	return simplexml_load_file(dirname(__DIR__, 4) . '/resource/snmp_queries/cisco_hsrp.xml');
}

test('HSRP rows retain both interface and group when the group is repeated on different interfaces', function () {
	$query      = hsrp_query_fixture();
	$prefix     = (string) $query->oid_index;
	$expression = substr((string) $query->oid_index_parse, strlen('OID/REGEXP:'));
	$indexes    = [];

	foreach (['3.1', '15.1', '17.1', '17.4095'] as $index) {
		expect(preg_match('/' . $expression . '/', $prefix . '.' . $index, $matches))->toBe(1);
		$indexes[] = $matches[1];
	}
	expect($indexes)->toBe(['3.1', '15.1', '17.1', '17.4095']);
	expect((string) $query->fields->hsrpIndex->source)->toBe((string) $query->oid_index_parse);
	expect((string) $query->index_order)->toBe('hsrpIndex');
	expect(isset($query->oid_num_indexes))->toBeFalse();
});

test('HSRP fields construct table instance OIDs without a fixed interface', function () {
	$query = hsrp_query_fixture();
	$table = '.1.3.6.1.4.1.9.9.106.1.2.1.1';
	expect((string) $query->oid_index)->toBe($table . '.15');

	foreach (['hsrpState' => 15, 'hsrp_vip' => 11, 'hsrp_priority' => 3, 'hsrp_hold_timer' => 10] as $field => $column) {
		expect((string) $query->fields->$field->oid . '.17.1')->toBe($table . '.' . $column . '.17.1');
	}
	expect((string) $query->fields->hsrpState->direction)->toBe('input-output');
});

test('the Cisco Router package ships the corrected HSRP query with valid signatures', function () {
	$root     = dirname(__DIR__, 4);
	$xml      = gzdecode(file_get_contents($root . '/install/templates/Cisco_Router.xml.gz'));
	$package  = simplexml_load_string($xml);
	$key      = base64_decode((string) $package->publickey, true);
	$unsigned = preg_replace('/<signature>[^<]*<\/signature>/', '<signature></signature>', $xml);
	expect(openssl_verify($unsigned, base64_decode((string) $package->signature, true), $key, OPENSSL_ALGO_SHA256))->toBe(1);
	$found = false;

	foreach ($package->files->file as $file) {
		$data = base64_decode((string) $file->data, true);
		expect(openssl_verify($data, base64_decode((string) $file->filesignature, true), $key, OPENSSL_ALGO_SHA256))->toBe(1);

		if ((string) $file->name === 'resource/snmp_queries/cisco_hsrp.xml') {
			expect($data)->toBe(file_get_contents($root . '/resource/snmp_queries/cisco_hsrp.xml'));
			$found = true;
		}
	}
	expect($found)->toBeTrue();
});
