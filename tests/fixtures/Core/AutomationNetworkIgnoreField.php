<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiAutomationNetworkIgnoreFieldFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

/*
 * Build the Discovery Network edit form from the checked-in
 * network_get_field_array() and check that the ignore list field tells the
 * operator it accepts CIDR blocks, which automation_primeIPAddressTable() now
 * honours. Only the function is loaded, so the page's auth and dispatch never
 * run; settings and scheduler fields are stubbed in this namespace.
 */

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

$GLOBALS['ping_methods'] = [1 => 'ICMP'];
$GLOBALS['sched_types']  = [1 => 'Manual'];

function __($text, ...$args) {
	return $text;
}
function read_config_option($name) {
	return '';
}
function number_format_i18n($value) {
	return (string) $value;
}
function api_scheduler_form() {
	return [];
}

cacti_fixture_source_function($root . '/automation_networks.php', 'network_get_field_array', __NAMESPACE__);

$field = network_get_field_array(['ignore_ips' => '192.168.1.0/28'])['ignore_ips'] ?? null;

if (!is_array($field) || $field['method'] !== 'textarea') {
	throw new RuntimeException('The network form has no ignore list textarea');
}

foreach (['description', 'placeholder'] as $key) {
	if (!str_contains($field[$key], 'CIDR blocks')) {
		throw new RuntimeException('The ignore list ' . $key . ' does not mention CIDR blocks');
	}
}

print json_encode(['passed' => 2], JSON_THROW_ON_ERROR) . "\n";
