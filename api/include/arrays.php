<?php

$allowed_hosts_filter = [
	'host_id',
	'poller_id',
	'site_id',
	'template_id',
	'status',
	'snmp_location',
	'hostname',
	'description'
];

$allowed_host_templates_filter = [
	'template_id'
];

$allowed_thold_filter = [
	'host_id',
	'host_description',
	'hostname',
	'data_source_name',
	'template_id'
];

$allowed_automation_networks_filter = [
	'network_id',
	'network_name',
	'subnet_range',
];
