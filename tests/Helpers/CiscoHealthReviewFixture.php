<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiTests\CiscoHealthReview;
final class State {
	public static int $reads     = 0;
	public static array $oids    = [];
	public static int $clock     = 0;
	public static array $hashes  = [];
	public static int $hashReads = 0;
}
function hrtime($numeric) {
	return State::$clock;
}
function db_fetch_row_prepared($sql, $args) {
	if (str_contains($sql, 'FROM graph_local')) {
		return ['id'=>55, 'host_id'=>1, 'graph_template_id'=>7, 'hash'=>'d0e843dee0462653833556d6eafa87d3', 'snmp_query_id'=>0];
	}
	State::$reads++;

	return ['hostname'   => 'test', 'snmp_community'=>'test', 'snmp_version'=>2,
		'snmp_username'     => '', 'snmp_password'=>'', 'snmp_auth_protocol'=>'', 'snmp_priv_passphrase'=>'',
		'snmp_priv_protocol'=> '', 'snmp_context'=>'', 'snmp_port'=>161, 'snmp_timeout'=>500, 'ping_retries'=>1];
}
function db_fetch_cell_prepared($sql, $args) {
	if (str_contains($sql, 'SELECT hash')) {
		State::$hashReads++;

		return State::$hashes[$args[0]] ?? '';
	}

	return 55;
}
function cacti_snmp_get(...$args) {
	$oid               = $args[2];
	State::$oids[$oid] = (State::$oids[$oid] ?? 0) + 1;

	return str_contains($oid, '.1.1.1.1.6.') ? '1' : '100';
}
function cacti_log(...$args) {
}
