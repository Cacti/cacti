<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Executes the unchanged layouts_form_actions() with its real request and
// selection validation, recording the persistence and response boundaries.

namespace Cacti\Test\LayoutActions {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
	require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

	class Stopped extends \RuntimeException {
	}

	function filter_layouts_delete(int $id) : bool {
		$GLOBALS['layout_calls'][] = ['delete', $id];

		return true;
	}

	function filter_layouts_publish(int $id) : bool {
		$GLOBALS['layout_calls'][] = ['publish', $id];

		return true;
	}

	function filter_layouts_unpublish(int $id, int $user_id) : bool {
		$GLOBALS['layout_calls'][] = ['unpublish', $id, $user_id];

		return true;
	}

	function cacti_redirect(string $path, array $params = []) : never {
		throw new Stopped('redirect:' . $path);
	}

	function die_html_input_error(mixed $variable = null, mixed $value = null, string $message = '') : void {
		throw new Stopped('rejected:' . $variable);
	}

	function db_fetch_cell_prepared(string $sql, array $params = [], string $col_name = '', bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
		return 'Fixture & layout';
	}

	function form_continue_confirmation(array $form_data, string $plugin_hook = '', array $save = []) : void {
		$GLOBALS['layout_confirmation'] = $form_data;
	}

	function htmle(mixed $string) : string {
		return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
	}

	function __(string $text, mixed ...$args) : string {
		return $text;
	}

	$root = dirname(__DIR__, 3);

	foreach (['isset_request_var', 'set_request_var', 'get_nfilter_request_var', 'request_present_value', 'request_string', 'request_enum'] as $function) {
		\cacti_fixture_source_function($root . '/lib/html_utility.php', $function, __NAMESPACE__);
	}
	\cacti_fixture_source_function($root . '/lib/functions.php', 'sanitize_unserialize_selected_items', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/html_validate.php', 'input_validate_input_number', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/layouts.php', 'layouts_form_actions', __NAMESPACE__);

	$GLOBALS['layout_count'] = 0;

	function expect_that(bool $condition, string $message) : void {
		if (!$condition) {
			throw new \RuntimeException($message);
		}
		$GLOBALS['layout_count']++;
	}

	/**
	 * Post one bulk-action request as user 17.
	 *
	 * @param array<string,mixed> $post    The posted fields.
	 * @param array<int,string>   $allowed The actions offered to the user.
	 *
	 * @return array{calls:array<int,array<int,int|string>>,confirmation:array<string,mixed>|null,stop:string|null}
	 */
	function run_fixture(array $post, array $allowed = [1 => 'Delete', 2 => 'Global', 3 => 'Local']) : array {
		$_GET                           = [];
		$_POST                          = $_REQUEST = $post;
		$_SESSION['sess_user_id']       = 17;
		$GLOBALS['_CACTI_REQUEST']      = [];
		$GLOBALS['actions']             = $allowed;
		$GLOBALS['layout_calls']        = [];
		$GLOBALS['layout_confirmation'] = null;
		$stop                           = null;

		try {
			layouts_form_actions();
		} catch (Stopped $e) {
			$stop = $e->getMessage();
		}

		return ['calls' => $GLOBALS['layout_calls'], 'confirmation' => $GLOBALS['layout_confirmation'], 'stop' => $stop];
	}

	$untouched = static fn (string $stop) : array => ['calls' => [], 'confirmation' => null, 'stop' => $stop];

	// Each offered action applies to every selected id, as the current user.
	foreach (['1' => [['delete', 4], ['delete', 8]], '2' => [['publish', 4], ['publish', 8]], '3' => [['unpublish', 4, 17], ['unpublish', 8, 17]]] as $action => $calls) {
		$result = run_fixture(['drp_action' => (string) $action, 'selected_items' => serialize(['4', '8'])]);
		expect_that($result === ['calls' => $calls, 'confirmation' => null, 'stop' => 'redirect:layouts.php'], 'Action ' . $action . ' must apply to the selection');
	}

	// A missing or empty action returns to the list without mutation.
	foreach ([[], ['drp_action' => ''], ['drp_action' => 'undefined']] as $post) {
		expect_that(run_fixture($post + ['chk_4' => 'on']) === $untouched('redirect:layouts.php'), 'A missing action must redirect: ' . json_encode($post));
	}

	// An action the user was not offered is a validation error, as is any
	// non-member or non-scalar value, before confirmation or mutation.
	foreach (['unknown', 'two words', '01', '1 ', ['1'], new \stdClass()] as $action) {
		expect_that(run_fixture(['drp_action' => $action, 'chk_4' => 'on']) === $untouched('rejected:drp_action'), 'Unsupported action must be rejected');
	}
	expect_that(run_fixture(['drp_action' => '2', 'selected_items' => serialize(['4'])], [1 => 'Delete']) === $untouched('rejected:drp_action'), 'An action not offered to the user must be rejected');

	// The serialized selection keeps its numeric-list contract.
	foreach (['', 'not a selection', serialize(['word']), serialize([]), serialize([['4']])] as $selection) {
		expect_that(run_fixture(['drp_action' => '1', 'selected_items' => $selection]) === $untouched('redirect:layouts.php'), 'Invalid selection must not mutate: ' . $selection);
	}
	expect_that(run_fixture(['drp_action' => '1', 'selected_items' => ['4']]) === $untouched('rejected:selected_items'), 'An array selection must be rejected');

	// Checkbox selections reach the confirmation before any mutation.
	$result = run_fixture(['drp_action' => '1', 'chk_4' => 'on', 'other' => 'ignored']);
	expect_that($result['calls'] === [] && $result['stop'] === null, 'Checkbox selection must not mutate');
	expect_that($result['confirmation']['general']['item_array'] === ['4'], 'Confirmation must list the checked id');
	expect_that($result['confirmation']['general']['item_list'] === '<li>Fixture &amp; layout</li>', 'Confirmation must escape the layout name');

	print json_encode(['passed' => $GLOBALS['layout_count']], JSON_THROW_ON_ERROR) . "\n";
}
