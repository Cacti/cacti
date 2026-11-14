<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiTests\CiscoNumeric;
const SNMP_VALUE_PLAIN = 1;
final class State {
	public static int $method     = 1;
	public static int $retrieval  = 0;
	public static string $warning = '';
	public static string $value   = '-95';
	public static array $args     = [];
	public static string $command = '';
}
function cacti_snmp_options_sanitize(...$args) {
	return true;
}
function snmp_get_method(...$args) {
	return State::$method;
}
function snmp_set_quick_print($v) {
}
function snmp_get_valueretrieval() {
	return State::$retrieval;
}
function snmp_set_valueretrieval($v) {
	State::$retrieval = $v;
}
function snmp_format_agent($h,$p) {
	return $h;
}
function snmp2_get(...$args) {
	if (State::$warning !== '') {
		trigger_error('snmp2_get(): ' . State::$warning, E_USER_WARNING);

		return false;
	}

	return State::$retrieval === SNMP_VALUE_PLAIN ? State::$value : '-9.5 dB';
}
function cacti_log(...$args) {
}
function cacti_format_ipv6_colon($h) {
	return $h;
}
function snmp_escape_string($v) {
	return escapeshellarg($v);
}
function cacti_escapeshellcmd($v) {
	return escapeshellcmd($v);
}
function cacti_escapeshellarg_cmd($v,...$args) {
	return escapeshellarg($v);
}
function cacti_escapeshellarg($v) {
	return escapeshellarg($v);
}
function read_config_option($v) {
	return '/usr/bin/snmpget';
}
function debug_log_insert(...$args) {
}
function __esc($v,...$args) {
	return $v;
}
function exec($command, &$output, &$status) {
	State::$command = $command;
	$output         = [State::$value];
	$status         = 0;
}
function cacti_get_snmp_auth_args(&$version,...$args) {
	$version = '2c';

	return ['-c', 'test'];
}
function snmp_format_target_arg($h,$p) {
	return $h . ':' . $p;
}
function cacti_snmp_debug_command(...$args) {
}
function cacti_snmp_command_timeout(...$args) {
	return 1;
}
function cacti_exec($binary,$args,&$output,...$other) {
	State::$args = $args;
	$output      = [State::$value];
}
