<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiTests\CiscoHealthRetry;
final class State {
	public static bool $exists = false;
	public static bool $fail = false;
	public static array $builds = [];
	public static array $events = [];
	public static bool $created = true;
}
function cisco_health_graph_kind($id) { return $id === 7 ? 'memory' : null; }
function cisco_health_graph_existing(...$args) { return State::$exists ? ['local_graph_id'=>55] : []; }
function cisco_health_graph_build(...$args) {
	State::$builds[] = $args;
	if (State::$fail) { throw new \RuntimeException('Transient failure'); }
	State::$exists = true;
	return ['applied'=>true,'graph_id'=>55,'created'=>State::$created];
}
function cacti_log(...$args) {}

function set_config_option($key,$value) { State::$events[] = ['cache',$key]; }
function automation_hook_graph_create_tree($save) { State::$events[] = ['tree',$save]; }
function api_plugin_hook_function($name,$save) { State::$events[] = ['plugin',$name,$save]; }
