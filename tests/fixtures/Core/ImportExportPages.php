<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs import and export page functions with their production source loaded
 * into this namespace and the request, database and output boundaries
 * replaced by the stand-ins below.  Each mode runs in its own process because
 * several of the page functions end the request.
 *
 * Usage: ImportExportPages.php MODE
 */

namespace Cacti\Test\ImportExportPages;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/html.php';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		fwrite(STDERR, 'FAIL: ' . $message . "\n");

		exit(1);
	}
}

$GLOBALS['fixture'] = ['request' => [], 'columns' => [], 'logs' => [], 'headers' => [], 'messages' => [], 'calls' => []];

function __(string $format, mixed ...$arguments) : string {
	return $arguments === [] ? $format : vsprintf($format, $arguments);
}

function __esc(string $format, mixed ...$arguments) : string {
	return html_escape(__($format, ...$arguments));
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_log(string $message, bool $output = false, string $environ = '', int $level = 0) : void {
	$GLOBALS['fixture']['logs'][] = $message;
}

function db_column_exists(string $table, string $column) : bool {
	return in_array($column, $GLOBALS['fixture']['columns'], true);
}

function get_public_key_sha1() : string {
	return 'legacy';
}

function get_public_key_sha256() : string {
	return 'built-in';
}

function import_get_package_info(string $xmlfile) : mixed {
	return $GLOBALS['fixture']['package'];
}

function import_public_key_meets_minimum(string $key) : bool {
	return true;
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return $GLOBALS['fixture']['cell'] ?? false;
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['fixture']['calls'][] = $params;

	return true;
}

function isrv(string $name) : bool {
	return isset($GLOBALS['fixture']['request'][$name]);
}

function gfrv(string $name, mixed ...$filter) : mixed {
	$value = $GLOBALS['fixture']['request'][$name] ?? '';

	// Stand in for the input validation error on a value the pattern rejects.
	if (($filter[0] ?? null) === FILTER_VALIDATE_REGEXP && $value !== '' && !preg_match($filter[1]['options']['regexp'], (string) $value)) {
		$GLOBALS['fixture']['input_error'] = $name;

		exit;
	}

	return $value;
}

function grv(string $name) : mixed {
	return $GLOBALS['fixture']['request'][$name] ?? '';
}

function gnrv(string $name) : mixed {
	return $GLOBALS['fixture']['request'][$name] ?? '';
}

function db_fetch_cell(string $sql) : mixed {
	return 1;
}

function header(string $header) : void {
	$GLOBALS['fixture']['headers'][] = $header;
}

function raise_message(string $id, string $message = '', int $level = 0) : void {
	$GLOBALS['fixture']['messages'][] = [$id, $message, $level];
}

function raise_message_javascript(string $title, string $header, string $message) : void {
	$GLOBALS['fixture']['messages'][] = ['javascript', $header, $message];
}

function import_xml_data(string &$xml_data, bool $import_as_new, int $profile_id, bool $remove_orphans = false, bool $replace_svalues = false, array $import_hashes = []) : mixed {
	$GLOBALS['fixture']['calls'][] = ['import_xml_data', $xml_data];

	return $GLOBALS['fixture']['import_result'];
}

function validate_request_vars() : void {
}

function import_package_get_name(string $xmlfile) : string {
	return 'Sample <Package>';
}

function import_package(string $xmlfile, mixed ...$arguments) : mixed {
	$GLOBALS['fixture']['calls'][] = ['import_package', $arguments, basename($xmlfile)];

	return $GLOBALS['fixture']['import_result'];
}

function package_prepare_import_array(array &$templates, array &$files, string $package_name, string $package_filename, array $import_info) : void {
	$GLOBALS['fixture']['calls'][] = ['prepare', $package_name];
}

// The differences link scenario loads the real function instead.
if (($argv[1] ?? '') !== 'package-diff-link') {
	function import_display_package_data(array $templates, array $files, string $package_name, string $filename, array $data, bool $multipackage = true) : void {
		$GLOBALS['fixture']['calls'][] = ['display', $package_name];
	}
}

function get_repo_manifest_file(string $repo_id) : string {
	return json_encode(['manifest' => [3 => ['filename' => 'sample.xml.gz', 'name' => 'Sample'], 4 => ['filename' => 'other.xml.gz', 'name' => 'Other']]]);
}

function get_repo_file(string $repo_id, string $filename = 'package.manifest', bool $raise = true) : mixed {
	return 'package data';
}

function package_private_tmpdir() : string {
	return sys_get_temp_dir();
}

function get_public_key() : string {
	return 'built-in';
}

function package_import_take_state(string $token) : mixed {
	return $GLOBALS['fixture']['state'];
}

function import_display_results(array $import_debug_info, array $filestatus, bool $web = false, bool $preview = false) : void {
	$GLOBALS['fixture']['calls'][] = ['results', $import_debug_info];
}

function get_item_xml(string $type, mixed $id, bool $follow_deps) : string {
	$GLOBALS['fixture']['calls'][] = ['get_item_xml', $type];

	return '<xml/>';
}

function automation_snmp_option_import(array $snmp) : array {
	return ['success' => ['Imported ' . $snmp['name']]];
}

function automation_log(string $message, int $level = 0) : void {
}

function is_resource_writable(string $path) : bool {
	$GLOBALS['fixture']['calls'][] = ['is_resource_writable', $path];

	return true;
}

function html_start_box(string $title, mixed ...$arguments) : void {
}

function html_end_box(bool $trailing_br = true, bool $div = false) : void {
}

function html_header_checkbox(array $header_items, mixed ...$arguments) : void {
}

function form_alternate_row(string $row_id = '', bool $light = false, bool $disabled = false) : void {
}

function form_selectable_ecell(mixed $contents, mixed $id, mixed ...$arguments) : bool {
	return true;
}

function form_selectable_cell(mixed $contents, mixed $id, mixed ...$arguments) : bool {
	$GLOBALS['fixture']['cells'][] = $contents;

	return true;
}

function form_checkbox_cell(string $title, mixed $id, bool $disabled = false, bool $checked = false) : void {
}

function form_end_row() : void {
}

function form_start(mixed $action, string $id = '', bool $multipart = false) : void {
}

function draw_edit_form(array $array) : void {
}

function form_hidden_box(string $form_name, mixed $prev_val, mixed $default_val, mixed $in_form = false) : void {
}

function kill_session_var(string $var_name) : void {
}

$mode = $argv[1] ?? '';

switch ($mode) {
	case 'profile-messages':
		foreach (['profile_import_execute', 'profile_validate_import_columns'] as $function) {
			\cacti_fixture_source_function($root . '/data_source_profiles.php', $function, __NAMESPACE__);
		}

		$GLOBALS['fixture']['columns'] = ['name', 'hash'];

		$result = profile_import_execute(['profile' => [
			['name' => '<b>empty</b>', 'cfs' => [], 'rras' => []],
			['name' => 'columns', '<i>extra</i>' => 1, 'cfs' => [1], 'rras' => [1]],
		]]);

		$errors = implode("\n", $result['errors'] ?? []);

		check(substr_count($errors, '&lt;b&gt;empty&lt;/b&gt;') === 2, 'Profile names in result lines are encoded');
		check(str_contains($errors, '&lt;i&gt;extra&lt;/i&gt;'), 'Column names in result lines are encoded');
		check(!str_contains($errors, '<'), 'No markup from the file reaches the result lines');

		break;
	case 'public-key-details':
		\cacti_fixture_source_function($root . '/package_import.php', 'import_validate_public_key', __NAMESPACE__);

		$GLOBALS['fixture']['package'] = ['pubkey' => 'built-in', 'author' => 'Someone', 'homepage' => 'https://example.invalid', 'email' => 'x@example.invalid'];

		check(import_validate_public_key('package.xml.gz', false) === 'built-in', 'The built in key is reported as trusted');
		check(import_validate_public_key('package.xml.gz', true) === 'built-in', 'Accepting the built in key reports it as trusted');
		check($GLOBALS['fixture']['calls'] === [], 'No key row is stored for the built in key');

		$GLOBALS['fixture']['package']['pubkey'] = 'legacy';

		check(import_validate_public_key('package.xml.gz', false) === 'legacy', 'The legacy built in key is reported as trusted');
		check($GLOBALS['fixture']['calls'] === [], 'No key row is stored for the legacy built in key');

		$GLOBALS['fixture']['package']['pubkey'] = 'other';

		check(import_validate_public_key('package.xml.gz', false) === false, 'An unknown key is not trusted from the details view');
		check(import_validate_public_key('package.xml.gz', true) === 'other', 'An accepted key is trusted');
		check(cacti_sizeof($GLOBALS['fixture']['calls']) === 1 && $GLOBALS['fixture']['calls'][0][4] === 'other', 'Only the accepted key is stored');

		break;
	case 'template-import-result':
		\cacti_fixture_source_function($root . '/templates_import.php', 'form_save', __NAMESPACE__);

		$upload = tempnam(sys_get_temp_dir(), 'cacti-template-');
		file_put_contents($upload, '<cacti/>');

		$_FILES                        = ['import_file' => ['tmp_name' => $upload, 'name' => 'template.xml']];
		$GLOBALS['fixture']['request'] = ['save_component_import' => '1', 'import_data_source_profile' => '1', 'preview_only' => 'false'];

		$GLOBALS['fixture']['import_result'] = false;
		form_save();

		check($GLOBALS['fixture']['messages'][0][0] === 'javascript', 'A failed import is reported as failed');
		check($GLOBALS['fixture']['headers'] === [], 'A failed import does not redirect as a success');

		$GLOBALS['fixture']['messages']      = [];
		$GLOBALS['fixture']['import_result'] = ['vdef' => []];
		form_save();

		check($GLOBALS['fixture']['messages'] === [['import_success', 'The Template Import Succeeded.', MESSAGE_LEVEL_INFO]], 'A completed import is reported as succeeded');
		check($GLOBALS['fixture']['headers'] === ['Location: templates_import.php'], 'A completed import returns to the import page');

		unlink($upload);

		break;
	case 'template-dependencies':
		\cacti_fixture_source_function($root . '/templates_import.php', 'prepare_template_display', __NAMESPACE__);

		$GLOBALS['hash_type_names'] = ['graph_template' => 'Graph Template'];

		$info      = ['graph_template' => [['hash' => 'abc', 'type' => 'new', 'title' => 'Traffic', 'dep' => ['hash_one' => 'met', 'hash_two' => 'unmet']]]];
		$templates = prepare_template_display($info);

		check(($templates['abc']['deps'] ?? null) === ['hash_one' => 'met', 'hash_two' => 'unmet'], 'The dependencies reach the preview table');
		check($templates['abc']['name'] === 'Traffic' && $templates['abc']['type_name'] === 'Graph Template', 'The template row is described');

		break;
	case 'package-preview-failure':
		\cacti_fixture_source_function($root . '/package_import.php', 'form_save', __NAMESPACE__);

		$upload = tempnam(sys_get_temp_dir(), 'cacti-package-');
		file_put_contents($upload, 'package');

		$_FILES                              = ['import_file' => ['tmp_name' => $upload]];
		$_SESSION                            = [];
		$GLOBALS['fixture']['request']       = ['save_component_import' => '1', 'data_source_profile' => '1', 'preview_only' => 'on'];
		$GLOBALS['fixture']['import_result'] = false;

		form_save();

		check(($GLOBALS['fixture']['messages'][0][0] ?? '') === 'javascript' && str_contains($GLOBALS['fixture']['messages'][0][1], 'Sample <Package>'), 'A package that cannot be read is reported');
		check(array_column($GLOBALS['fixture']['calls'], 0) === ['import_package'], 'Nothing is prepared or displayed for a package that cannot be read');

		$GLOBALS['fixture']['messages']      = [];
		$GLOBALS['fixture']['calls']         = [];
		$GLOBALS['fixture']['import_result'] = [[], []];
		$_FILES                              = ['import_file' => ['tmp_name' => $upload]];

		form_save();

		check($GLOBALS['fixture']['messages'] === [], 'A readable package raises no error');
		check(array_column($GLOBALS['fixture']['calls'], 0) === ['import_package', 'prepare', 'display'], 'A readable package is displayed');

		unlink($upload);

		break;
	case 'package-details-failure':
		foreach (['package_get_details', 'import_validate_public_key'] as $function) {
			\cacti_fixture_source_function($root . '/package_import.php', $function, __NAMESPACE__);
		}

		$GLOBALS['fixture']['package']       = ['pubkey' => 'other', 'author' => '', 'homepage' => '', 'email' => ''];
		$GLOBALS['fixture']['request']       = ['package_ids' => '3', 'package_location' => '1', 'data_source_profile' => '1'];
		$GLOBALS['fixture']['import_result'] = false;

		package_get_details();

		check(($GLOBALS['fixture']['messages'][0][0] ?? '') === 'javascript' && str_contains($GLOBALS['fixture']['messages'][0][1], 'Sample'), 'A repository package that cannot be read is reported');
		check(array_column($GLOBALS['fixture']['calls'], 0) === ['import_package', 'display'], 'A repository package that cannot be read is not prepared');
		check(!file_exists(sys_get_temp_dir() . '/sample.xml.gz'), 'The downloaded copy is removed');

		$GLOBALS['fixture']['messages']      = [];
		$GLOBALS['fixture']['calls']         = [];
		$GLOBALS['fixture']['import_result'] = [[], []];

		package_get_details();

		check($GLOBALS['fixture']['messages'] === [] && array_column($GLOBALS['fixture']['calls'], 0) === ['import_package', 'prepare', 'display'], 'A readable repository package is prepared and displayed');

		break;
	case 'repository-import-selection':
		\cacti_fixture_source_function($root . '/package_import.php', 'form_actions', __NAMESPACE__);

		$GLOBALS['fixture']['request']       = ['drp_action' => 1, 'import_state' => str_repeat('a', 32)];
		$GLOBALS['fixture']['import_result'] = [[], []];
		$GLOBALS['fixture']['state']         = [
			'package_location'    => '1',
			'data_source_profile' => 1,
			'remove_orphans'      => false,
			'replace_svalues'     => false,
			'selected_items'      => [],
			'selected_hashes'     => [['hash' => 'template_hash', 'files' => 'sample.xml.gz']],
			'selected_files'      => [['filename' => 'other.xml.gz', 'pfile' => 'scripts/other.php']],
		];

		register_shutdown_function(static function () : void {
			$imports = [];

			foreach ($GLOBALS['fixture']['calls'] as $call) {
				if ($call[0] === 'import_package') {
					$imports[$call[2]] = [$call[1][6], $call[1][7]];
				}
			}

			check(($imports['sample.xml.gz'] ?? null) === [['template_hash'], ['dont import']], 'Selecting only templates imports no files');
			check(($imports['other.xml.gz'] ?? null) === [['dont import'], ['scripts/other.php']], 'Selecting only files imports no templates');

			print "PASS: repository-import-selection\n";
		});

		form_actions();

		check(false, 'The import action ends the request');

		break;
	case 'export-type':
		\cacti_fixture_source_function($root . '/templates_export.php', 'form_save', __NAMESPACE__);

		$GLOBALS['export_types']       = ['graph_template' => ['name' => 'Graph Template', 'title_sql' => 'SELECT name FROM graph_templates WHERE id=|id|']];
		$GLOBALS['fixture']['request'] = ['save_component_export' => '1', 'export_type' => 'graph_template', 'export_item_id' => '1', 'output_format' => '9'];

		form_save();

		check($GLOBALS['fixture']['calls'] === [['get_item_xml', 'graph_template']], 'A known export type is exported');

		$GLOBALS['fixture']['calls']                  = [];
		$GLOBALS['fixture']['request']['export_type'] = 'unknown';

		register_shutdown_function(static function () : void {
			check($GLOBALS['fixture']['calls'] === [], 'An unknown export type is not exported');
			check($GLOBALS['fixture']['headers'] === ['Location: templates_export.php'], 'An unknown export type returns to the export page');

			print "PASS: export-type\n";
		});

		form_save();

		check(false, 'An unknown export type ends the request');

		break;
	case 'snmp-option-results':
		\cacti_fixture_source_function($root . '/automation_snmp.php', 'automation_import_process', __NAMESPACE__);

		$_SESSION                      = [];
		$GLOBALS['fixture']['request'] = ['import_text' => json_encode(['snmp' => [['name' => 'First'], ['name' => 'Second']]])];

		register_shutdown_function(static function () : void {
			check(count($_SESSION['import_debug_info'] ?? []) === 2, 'Each imported SNMP option set is reported');
			check(str_ends_with($_SESSION['import_debug_info'][1], 'Imported Second'), 'The second SNMP option set is reported');

			print "PASS: snmp-option-results\n";
		});

		automation_import_process();

		check(false, 'The import ends the request');

		break;
	case 'template-temp-directory':
		\cacti_fixture_source_function($root . '/templates_import.php', 'is_tmp_writable', __NAMESPACE__);

		is_tmp_writable('/var/tmp/');
		is_tmp_writable('/var/tmp');

		$locals = \cacti_fixture_source_block($root . '/templates_import.php', __NAMESPACE__, '~\n(\$tmp_dir  = sys_get_temp_dir\(\);.*?\$is_tmp   = is_tmp_writable\(\);)~s');

		check(array_column($GLOBALS['fixture']['calls'], 1) === ['/var/tmp/', '/var/tmp/', $locals['tmp_dir']], 'The temporary directory ends in one separator');
		check(str_ends_with($locals['tmp_dir'], '/') && !str_ends_with($locals['tmp_dir'], '//'), 'The page temporary directory ends in one separator');

		break;
	case 'package-temp-directory':
		\cacti_fixture_source_function($root . '/package_import.php', 'is_tmp_writable', __NAMESPACE__);

		is_tmp_writable();

		$path = $GLOBALS['fixture']['calls'][0][1];

		check(str_ends_with($path, '/') && !str_ends_with($path, '//'), 'The package temporary directory ends in one separator');

		break;
	case 'package-diff-link':
		\cacti_fixture_source_function($root . '/package_import.php', 'import_display_package_data', __NAMESPACE__);

		if (!\defined('CACTI_PATH_BASE')) {
			\define('CACTI_PATH_BASE', $root);
		}

		$GLOBALS['fixture']['request'] = ['package_location' => '2'];
		$files                         = ['sample&name.xml.gz|Disk #1 & More' => [$root . '/scripts/a b.php' => 'writable, differences']];

		ob_start();
		import_display_package_data([], $files, 'Sample', 'sample.xml.gz', []);
		ob_end_clean();

		preg_match("~href='([^']*)'~", $GLOBALS['fixture']['cells'][0] ?? '', $match);
		parse_str(parse_url(html_entity_decode($match[1] ?? '', ENT_QUOTES), PHP_URL_QUERY) ?? '', $query);

		check($query === ['action' => 'diff', 'package_location' => '2', 'package_file' => 'sample&name.xml.gz', 'package_name' => 'Disk #1 & More', 'filename' => 'scripts/a b.php'], 'The differences link carries each value intact');

		break;
	case 'tree-rule-import-form':
		\cacti_fixture_source_function($root . '/automation_tree_rules.php', 'automation_import', __NAMESPACE__);

		$_SESSION = [];

		ob_start();
		automation_import();
		$output = (string) ob_get_clean();

		check(substr_count($output, '<button') === 1 && substr_count($output, '</button>') === 1, 'The import button is closed');

		break;
	case 'import-flags':
		\cacti_fixture_source_function($root . '/package_import.php', 'form_actions', __NAMESPACE__);

		$GLOBALS['fixture']['request'] = ['drp_action' => '1', 'remove_orphans' => 'onion'];

		register_shutdown_function(static function () : void {
			check(($GLOBALS['fixture']['input_error'] ?? '') === 'remove_orphans', 'Only the value on is accepted for an option');

			print "PASS: import-flags\n";
		});

		form_actions();

		check(false, 'A rejected option ends the request');

		break;
	default:
		fwrite(STDERR, "Unknown mode\n");

		exit(2);
}

print "PASS: $mode\n";
