<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiAxisFixture;

// These boundaries are confined to this fixture namespace.
function get_rrdtool_version(): string {
	return $GLOBALS['axis_fixture_version'];
}
function read_config_option(string $name): string {
	return '';
}
function rrdtool_resolve_graph_text(string $value, array $graph): string {
	return $value;
}
function rrdtool_function_format_graph_date(array $data): string {
	return '';
}
function rrdtool_function_theme_font_options(array $data): string {
	return '';
}
class CactiValidator extends \CactiValidator {
}

function db_fetch_row_prepared(string $sql, array $params): array {
	return $GLOBALS['axis_fixture_template'];
}
function db_fetch_cell_prepared(string $sql, array $params): int {
	return 1;
}
function db_execute_prepared(string $sql, array $params): bool {
	$GLOBALS['axis_fixture_updates'][] = [$sql, $params];

	return true;
}
