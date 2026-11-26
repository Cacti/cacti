<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\SitesController;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
define('MESSAGE_LEVEL_ERROR', 3);

/** Records the calls sites.php makes, standing in for the service lib/sites.php assembles. */
final class RecordingSiteService {
	public array $calls   = [];
	public mixed $saved   = 42;
	public array $missing = [];

	public function save(array $values) : mixed {
		$this->calls[] = ['save', $values];

		return $this->saved;
	}

	public function disable(array $ids) : void {
		$this->calls[] = ['disable', $ids];
	}

	public function enable(array $ids) : void {
		$this->calls[] = ['enable', $ids];
	}

	public function duplicate(mixed $template_id, string $name, \Closure $missing) : void {
		$this->calls[] = ['duplicate', $template_id, $name];
		$missing();
	}

	public function delete(array $ids) : void {
		$this->calls[] = ['delete', $ids];
	}
}

function site_service() : RecordingSiteService {
	return $GLOBALS['sites_service'];
}
function isrv(string $name) : bool {
	return array_key_exists($name, $GLOBALS['sites_request']);
}
function gfrv(string $name, mixed ...$args) : mixed {
	return $GLOBALS['sites_request'][$name] ?? '';
}
function gnrv(string $name) : mixed {
	return $GLOBALS['sites_request'][$name] ?? '';
}
function form_input_validate(mixed $value, string $field, string $regex, bool $allow_nulls, int $message) : mixed {
	return $value;
}
function is_error_message() : bool {
	return $GLOBALS['sites_error'];
}
function sanitize_unserialize_selected_items(string $items) : array|false {
	return json_decode($items, true);
}
function raise_message(string|int $id, string $text = '', int $level = 0) : void {
	$GLOBALS['sites_messages'][] = [$id, $text, $level];
}
function __(string $text) : string {
	return $text;
}
function header(string $value) : void {
	$GLOBALS['sites_redirect'] = $value;
}
function expect_same(mixed $actual, mixed $expected, string $case) : void {
	if ($actual !== $expected) {
		throw new \RuntimeException('Sites controller mismatch: ' . $case . ' ' . json_encode($actual));
	}
}

$file = dirname(__DIR__, 3) . '/sites.php';

foreach (['form_save', 'disable_site', 'enable_site', 'duplicate_site', 'form_actions'] as $function) {
	\cacti_fixture_source_function($file, $function, __NAMESPACE__);
}
$fields = ['name', 'disabled', 'address1', 'address2', 'city', 'state', 'postal_code', 'country', 'region',
	'timezone', 'latitude', 'longitude', 'zoom', 'alternate_id', 'notes'];
$passed = 0;

foreach ([[false, 42, 'Location: sites.php?action=edit&id=42'], [false, false, 'Location: sites.php?action=edit&id=7'],
	[true, 42, 'Location: sites.php?action=edit&id=7']] as [$error, $result, $redirect]) {
	$GLOBALS['sites_service']        = new RecordingSiteService();
	$GLOBALS['sites_service']->saved = $result;
	$GLOBALS['sites_error']          = $error;
	$GLOBALS['sites_request']        = ['save_component_site' => '1', 'id' => '7'] + array_combine($fields, $fields);
	form_save();
	$expected = $error ? [] : [['save', ['id' => '7'] + array_combine($fields, $fields)]];
	expect_same($GLOBALS['sites_service']->calls, $expected, 'save');
	expect_same($GLOBALS['sites_redirect'], $redirect, 'save redirect');
	$passed++;
}

$GLOBALS['sites_service']  = new RecordingSiteService();
$GLOBALS['sites_messages'] = [];
disable_site([1, '2']);
enable_site([3]);
duplicate_site(['4'], '<site> Copy');
expect_same($GLOBALS['sites_service']->calls, [['disable', [1, '2']], ['enable', [3]], ['duplicate', ['4'], '<site> Copy']], 'actions');
expect_same($GLOBALS['sites_messages'], [['site_error', 'Template Site was not found! Unable to duplicate.', MESSAGE_LEVEL_ERROR]], 'missing template');
$passed++;

// form_actions() exits after its redirect, so it runs last and is checked at shutdown.
$GLOBALS['sites_service'] = new RecordingSiteService();
$GLOBALS['sites_request'] = ['drp_action' => '1', 'selected_items' => '[5,6]'];
register_shutdown_function(static function () use (&$passed) : void {
	expect_same($GLOBALS['sites_service']->calls, [['delete', [5, 6]]], 'delete');
	expect_same($GLOBALS['sites_redirect'], 'Location: sites.php', 'delete redirect');
	$passed++;
	print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
});
form_actions();
