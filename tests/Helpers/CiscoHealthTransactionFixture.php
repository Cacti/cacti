<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiTests\CiscoHealthTransaction;

use RuntimeException;
use InvalidArgumentException;
use Throwable;

final class State {
	public static array $rows = [];
	public static bool $normal = false;
	public static bool $identity = true;
	public static array $items = [];
	public static array $base = [];
	public static int $writes = 0;
	public static bool $begin = true;
	public static int $rollbacks = 0;
	public static int $releases = 0;
}
function db_fetch_row_prepared($sql, $args) {
	if (str_contains($sql, 'SELECT description FROM host')) { return ['description' => 'Test router']; }
	if (State::$normal && str_contains($sql, 'SELECT gtg.*')) {
		return ['id'=>1, 'graph_template_id'=>9, 'image_format_id'=>1, 'height'=>150, 'width'=>500];
	}
	return [];
}
function db_fetch_assoc_prepared($sql, $args) {
	return str_contains($sql, 'SELECT task_item_id, sequence') ? State::$items : State::$rows;
}
function db_fetch_cell_prepared($sql, $args) {
	if (str_contains($sql, 'RELEASE_LOCK')) { State::$releases++; }
	if (str_contains($sql, 'GET_LOCK')) { return 1; }
	if (State::$normal) {
		if (str_contains($sql, 'AND graph_template_id = (SELECT')) { return State::$identity ? 1 : 0; }
		return 1;
	}
	return 0;
}
function db_execute_prepared($sql, $args) { return true; }
function sql_save($record, $table) {
	if ($table === 'graph_templates_item') { State::$items[] = $record; State::$writes++; }
	if ($table === 'graph_templates_graph') { State::$base = $record; }
	return 1;
}
function db_begin_transaction() { return State::$begin; }
function db_commit_transaction() { return true; }
function db_rollback_transaction() { State::$rollbacks++; }

function generate_graph_def_name($rank) { return chr(97 + $rank); }
