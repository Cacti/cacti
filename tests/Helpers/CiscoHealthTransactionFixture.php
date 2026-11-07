<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiTests\CiscoHealthTransaction;

use RuntimeException;
use InvalidArgumentException;
use Throwable;

final class State {
	public static array $rows = [];
	public static bool $begin = true;
	public static int $rollbacks = 0;
	public static int $releases = 0;
}
function db_fetch_row_prepared($sql, $args) {
	return str_contains($sql, 'SELECT description FROM host') ? ['description' => 'Test router'] : [];
}
function db_fetch_assoc_prepared($sql, $args) { return State::$rows; }
function db_fetch_cell_prepared($sql, $args) {
	if (str_contains($sql, 'RELEASE_LOCK')) { State::$releases++; }
	return str_contains($sql, 'GET_LOCK') ? 1 : 0;
}
function db_begin_transaction() { return State::$begin; }
function db_rollback_transaction() { State::$rollbacks++; }
