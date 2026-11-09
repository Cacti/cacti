<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\GprintController;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
define('MESSAGE_LEVEL_ERROR', 3);

/** Records the calls gprint_presets.php makes, standing in for the service lib/gprint.php assembles. */
final class RecordingGprintPresetService {
	public array $calls = [];

	public function __construct(private readonly bool $deletable) {
	}

	public function find(mixed $id) : array|bool {
		$this->calls[] = ['find', $id];

		return ['id' => $id, 'name' => 'Preset ' . $id];
	}

	public function save(array $values) : mixed {
		$this->calls[] = ['save', $values];

		return 12;
	}

	public function delete(array $ids) : bool {
		$this->calls[] = ['delete', $ids];

		return $this->deletable;
	}
}

function gprint_preset_service() : RecordingGprintPresetService {
	return $GLOBALS['gprint_service'];
}
function isrv(string $name) : bool {
	return array_key_exists($name, $GLOBALS['gprint_request']);
}
function ierv(string $name) : bool {
	return empty($GLOBALS['gprint_request'][$name]);
}
function gfrv(string $name, mixed ...$args) : mixed {
	return $GLOBALS['gprint_request'][$name] ?? '';
}
function grv(string $name) : mixed {
	return $GLOBALS['gprint_request'][$name] ?? '';
}
function gnrv(string $name) : mixed {
	return $GLOBALS['gprint_request'][$name] ?? '';
}
function get_hash_gprint(mixed $id) : string {
	return 'hash-' . $id;
}
function form_input_validate(mixed $value, string $field, string $regex, bool $allow_nulls, int $message) : mixed {
	return $value;
}
function is_error_message() : bool {
	return false;
}
function sanitize_unserialize_selected_items(string $items) : array|false {
	return json_decode($items, true);
}
function raise_message(string|int $id, string $text = '', int $level = 0) : void {
	$GLOBALS['gprint_messages'][] = [$id, $text, $level];
}
function __(string $text) : string {
	return $text;
}
function __esc(string $text, mixed ...$args) : string {
	return vsprintf($text, $args);
}
function header(string $value) : void {
	$GLOBALS['gprint_redirect'] = $value;
}
function inject_form_variables(array $fields, array $values) : array {
	return ['fields' => $fields, 'values' => $values];
}
function form_start(mixed ...$args) : void {
}
function html_start_box(string $label, mixed ...$args) : void {
	$GLOBALS['gprint_label'] = $label;
}
function draw_edit_form(array $form) : void {
	$GLOBALS['gprint_form'] = $form['fields'];
}
function html_end_box(mixed ...$args) : void {
}
function form_save_button(mixed ...$args) : void {
}
function expect_same(mixed $actual, mixed $expected, string $case) : void {
	if ($actual !== $expected) {
		throw new \RuntimeException('GPRINT controller mismatch: ' . $case . ' ' . json_encode($actual));
	}
}

$case = $argv[1] ?? 'save';

if (!in_array($case, ['save', 'delete-used', 'delete-unused'], true)) {
	throw new \InvalidArgumentException('Unknown GPRINT controller case');
}
$file = dirname(__DIR__, 3) . '/gprint_presets.php';

foreach (['form_save', 'form_actions', 'gprint_presets_edit'] as $function) {
	\cacti_fixture_source_function($file, $function, __NAMESPACE__);
}
$GLOBALS['fields_grprint_presets_edit'] = ['name' => ['method' => 'textbox']];
$GLOBALS['gprint_service']              = new RecordingGprintPresetService($case !== 'delete-used');
$GLOBALS['gprint_messages']             = [];
$GLOBALS['gprint_request']              = ['id' => '5'];
gprint_presets_edit();
expect_same($GLOBALS['gprint_service']->calls, [['find', '5']], 'edit lookup');
expect_same($GLOBALS['gprint_label'], 'GPRINT Presets [edit: Preset 5]', 'edit label');
expect_same($GLOBALS['gprint_form'], ['fields' => ['name' => ['method' => 'textbox']], 'values' => ['id' => '5', 'name' => 'Preset 5']], 'edit values');
$GLOBALS['gprint_service']->calls = [];

if ($case === 'save') {
	$GLOBALS['gprint_request'] = ['save_component_gprint_presets' => '1', 'id' => '5', 'name' => 'Renamed', 'gprint_text' => '%8.2lf %s'];
	$expected                  = [['save', ['id' => '5', 'hash' => 'hash-5', 'name' => 'Renamed', 'gprint_text' => '%8.2lf %s']]];
	$messages                  = [];
} else {
	$GLOBALS['gprint_request'] = ['drp_action' => '1', 'selected_items' => '[5,6]'];
	$expected                  = [['delete', [5, 6]]];
	$messages                  = $case === 'delete-used'
		? [['gprint_in_use', 'One or more GPRINT Presets are in use and cannot be deleted.', MESSAGE_LEVEL_ERROR]]
		: [];
}

// Both handlers exit after their redirect, so the result is checked at shutdown.
register_shutdown_function(static function () use ($expected, $messages) : void {
	expect_same($GLOBALS['gprint_service']->calls, $expected, 'handler calls');
	expect_same($GLOBALS['gprint_messages'], $messages, 'handler messages');
	expect_same($GLOBALS['gprint_redirect'], 'Location: gprint_presets.php', 'handler redirect');
	print json_encode(['passed' => 2], JSON_THROW_ON_ERROR) . "\n";
});

if ($case === 'save') {
	form_save();
} else {
	form_actions();
}
