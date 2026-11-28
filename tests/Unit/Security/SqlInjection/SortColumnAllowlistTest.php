<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * A sort column is checked against the columns the script's table headers list
 * as sortable plus the page's default column. The list does not depend on the
 * action or tab a request names, a page that has not drawn a header yet sorts
 * on its default column only, and a column named like a credential is refused
 * even when it is listed. Each probe call is one page request in its own
 * process, running the production filter, sort and header code.
 */

function sort_request(array $spec) : array {
	$fixture = dirname(__DIR__, 3) . '/fixtures/SortRequestProbe.php';
	$process = proc_open([PHP_BINARY, $fixture, json_encode($spec, JSON_THROW_ON_ERROR)],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0, $errors);

	return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

/*
 * Runs requests one after another, each starting from the session the previous
 * one left behind, and returns the ORDER BY clause of every table drawn.
 */
function sort_requests(string $script, array $requests) : array {
	$orders  = [];
	$session = [];

	foreach ($requests as [$request, $tables]) {
		$result   = sort_request(['script' => $script, 'request' => $request, 'session' => $session, 'tables' => $tables]);
		$orders[] = implode(' | ', $result['order']);
		$session  = $result['session'];
	}

	return $orders;
}

function sort_clause(string $column, string $direction) : string {
	if (in_array($column, ['hostname', 'ip', 'ip_address'], true)) {
		return 'ORDER BY INET_ATON(' . $column . ') ' . $direction;
	}

	return 'ORDER BY `' . implode('`.`', explode('.', $column)) . '` ' . $direction;
}

/*
 * Every sortable header a core page draws, read from the page source: the
 * script it runs under, the header keys, the renderer and the default sort
 * column of its filter.
 */
function sortable_core_headers() : array {
	$root    = dirname(__DIR__, 4);
	$scripts = ['lib/api_automation.php' => '/automation_tree_rules.php', 'lib/html_reports.php' => '/reports_admin.php'];
	$headers = [];

	foreach (array_merge(glob($root . '/*.php'), glob($root . '/lib/*.php')) as $file) {
		$name = substr($file, strlen($root) + 1);

		if ($name === 'lib/html.php' || $name === 'lib/html_utility.php') {
			continue;
		}

		$source = file_get_contents($file);

		if (!preg_match_all('/html_header_sort(_checkbox)?\(\s*(\$\w+)/', $source, $calls, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
			continue;
		}

		preg_match_all("/'sort_column'\\s*=>\\s*array\\(.*?'default'\\s*=>\\s*'([^']*)'/s", $source, $defaults);

		foreach ($calls as $call) {
			$start = strrpos(substr($source, 0, $call[0][1]), "\nfunction ");
			$body  = substr($source, (int) $start, $call[0][1] - (int) $start);
			$var   = preg_quote($call[2][0], '/');
			$keys  = [];

			preg_match('/function\s+(\w+)/', $body, $function);
			preg_match_all('/' . $var . '\s*(?:\+?=\s*(?:array\(|\[)|\[)(.*?);\n/s', $body, $statements);

			foreach ($statements[0] as $statement) {
				if (preg_match('/^' . $var . "\\[\\s*'([^']*)'\\s*\\]/", $statement, $key)) {
					$keys[] = $key[1];
				}

				preg_match_all("/'([^']*)'\\s*=>\\s*(?:array\\(|\\[)/", $statement, $found);
				$keys = array_merge($keys, $found[1]);
			}

			preg_match_all("/'sort_column'\\s*=>\\s*array\\(.*?'default'\\s*=>\\s*'([^']*)'/s", $body, $own);

			$headers[$name . ' ' . ($function[1] ?? '')] = [
				$scripts[$name] ?? '/' . $name,
				array_values(array_unique($keys)),
				$call[1][0] === '' ? 'plain' : 'checkbox',
				$own[1] ? end($own[1]) : $defaults[1][0],
			];
		}
	}

	return $headers;
}

test('the scan finds the sortable headers of the core pages', function () {
	$headers = sortable_core_headers();

	expect(count($headers))->toBeGreaterThanOrEqual(40)
		->and($headers['host.php host'])->toBe(['/host.php', ['description', 'hostname', 'id', 'graphs', 'data_sources', 'status', 'instate',
			'snmp_sysUpTimeInstance', 'polling_time', 'cur_time', 'avg_time', 'availability'], 'checkbox', 'description'])
		->and($headers['utilities.php utilities_view_poller_cache'])->toBe(['/utilities.php', ['dtd.name_cache', 'h.description', 'nosort'], 'plain', 'dtd.name_cache']);
});

test('a core page keeps its default sort and every sortable column of its header', function (string $script, array $columns, string $renderer, string $default) {
	$table    = ['prefix' => 'sess_page', 'default' => $default, 'columns' => $columns, 'renderer' => $renderer];
	$sortable = array_values(array_filter($columns, static fn (string $column) : bool => $column !== '' && !str_contains($column, 'nosort')));

	// first view of the session: the default sort, and the header lists its columns
	$view = sort_request(['script' => $script, 'request' => [], 'tables' => [$table]]);

	expect($view['order'])->toBe([sort_clause($default, 'ASC')])
		->and(array_diff($sortable, $view['session']['valid_sort_columns'][$script]))->toBe([])
		->and(preg_grep('/nosort/', $view['session']['valid_sort_columns'][$script]))->toBe([]);

	// a header click on each column, with an action and tab the first view did not name
	foreach ($sortable as $column) {
		$click = sort_request(['script' => $script, 'session' => $view['session'], 'tables' => [$table],
			'request' => ['action' => 'edit', 'tab' => 'other', 'sort_column' => $column, 'sort_direction' => 'DESC', 'add' => 'reset']]);

		expect($click['sort'])->toBe([$column])
			->and($click['order'])->toBe([sort_clause($column, 'DESC')]);
	}

	// a column the page does not show gives way to the default
	$other = sort_request(['script' => $script, 'session' => $view['session'], 'tables' => [$table],
		'request' => ['sort_column' => 'snmp_version', 'sort_direction' => 'DESC', 'add' => 'reset']]);

	expect($other['sort'])->toBe([$default])
		->and($other['order'])->toBe([sort_clause($default, 'ASC')]);
})->with(sortable_core_headers());

test('a page that has not drawn its header yet sorts on its declared default once', function () {
	$table = ['prefix' => 'sess_pollers', 'default' => 'name', 'columns' => ['name', 'id', 'poller.hostname', 'status', 'nosort0']];
	$click = ['sort_column' => 'status', 'sort_direction' => 'DESC', 'add' => 'reset'];

	expect(sort_requests('/pollers.php', [[$click, [$table]], [$click, [$table]]]))
		->toBe(['ORDER BY `name` ASC', 'ORDER BY `status` DESC']);
});

test('the action and tab of a request do not change which columns apply', function () {
	$table = ['prefix' => 'sess_cdef', 'default' => 'name', 'columns' => ['name', 'nosort', 'graphs', 'templates']];

	expect(sort_requests('/cdef.php', [
		[[], [$table]],
		[['action' => 'other', 'tab' => 'extra', 'sort_column' => 'hash', 'add' => 'reset'], [$table]],
		[['action' => 'other', 'tab' => 'extra', 'sort_column' => 'graphs', 'add' => 'reset'], [$table]],
		[['action' => 'third', 'sort_column' => 'system', 'add' => 'reset'], [$table]],
		[['tab' => 'fourth', 'sort_column' => 'nosort', 'add' => 'reset'], [$table]],
	]))->toBe([
		'ORDER BY `name` ASC',
		'ORDER BY `name` ASC',
		'ORDER BY `graphs` ASC',
		'ORDER BY `name` ASC',
		'ORDER BY `name` ASC',
	]);
});

test('a column listed by another script is not sortable', function () {
	$result = sort_request(['script' => '/vdef.php', 'request' => ['sort_column' => 'graphs'],
		'session' => ['valid_sort_columns' => ['/cdef.php' => ['name', 'graphs', 'templates']]],
		'tables'  => [['prefix' => 'sess_vdef', 'default' => 'name', 'columns' => ['name']]]]);

	expect($result['order'])->toBe(['ORDER BY `name` ASC']);
});

test('a header column named like a credential gives way to the default', function (string $column) {
	$table = ['prefix' => 'sess_pollers', 'default' => 'name', 'columns' => ['name', 'status', $column]];

	expect(sort_requests('/pollers.php', [
		[[], [$table]],
		[['sort_column' => $column, 'sort_direction' => 'DESC', 'add' => 'reset'], [$table]],
		[['sort_column' => 'status', 'sort_direction' => 'DESC', 'add' => 'reset'], [$table]],
	]))->toBe(['ORDER BY `name` ASC', 'ORDER BY `name` ASC', 'ORDER BY `status` DESC']);
})->with(['dbpass', 'snmp_community', 'api_key', 'repo_api_key', 'token_expiry', 'hash', 'p.dbsslkey']);

test('a script with several tables keeps the sort of each of them', function () {
	$list  = ['prefix' => 'sess_agraph', 'default' => 'title_cache', 'columns' => ['title_cache', 'local_graph_id', 'name', 'height']];
	$items = ['prefix' => 'sess_agraph_item', 'default' => 'title_cache', 'columns' => ['title_cache', 'local_graph_id', 'agg_graph_id', 'height']];

	expect(sort_requests('/aggregate_graphs.php', [
		[[], [$list]],
		[['action' => 'edit', 'tab' => 'items'], [$items]],
		[['sort_column' => 'name', 'sort_direction' => 'DESC', 'add' => 'reset'], [$list]],
		[['action' => 'edit', 'tab' => 'items', 'sort_column' => 'agg_graph_id', 'add' => 'reset'], [$items]],
		[[], [$list]],
		[['action' => 'edit', 'tab' => 'items'], [$items]],
		[['action' => 'edit', 'tab' => 'details', 'sort_column' => 'snmp_version', 'add' => 'reset'], [$items]],
	]))->toBe([
		'ORDER BY `title_cache` ASC',
		'ORDER BY `title_cache` ASC',
		'ORDER BY `name` DESC',
		'ORDER BY `agg_graph_id` ASC',
		'ORDER BY `name` DESC',
		'ORDER BY `agg_graph_id` ASC',
		'ORDER BY `title_cache` ASC',
	]);
});

test('two tables drawn by one request are both sortable afterwards', function () {
	$hosts  = ['prefix' => 'sess_auto', 'default' => 'description', 'renderer' => 'plain', 'columns' => ['description', 'hostname', 'status', 'id', 'nosort1']];
	$graphs = ['prefix' => 'sess_autog', 'default' => 'title_cache', 'renderer' => 'plain', 'columns' => ['description', 'title_cache', 'local_graph_id', 'name']];

	$view  = sort_request(['script' => '/automation_tree_rules.php', 'request' => ['action' => 'edit'], 'tables' => [$hosts, $graphs]]);
	$click = sort_request(['script' => '/automation_tree_rules.php', 'session' => $view['session'], 'tables' => [$hosts, $graphs],
		'request' => ['action' => 'edit', 'sort_column' => 'description', 'sort_direction' => 'DESC', 'add' => 'reset']]);

	expect($view['session']['valid_sort_columns'])->toBe(['/automation_tree_rules.php' => ['description', 'hostname', 'status', 'id', 'title_cache', 'local_graph_id', 'name']])
		->and($click['sort'])->toBe(['description', 'description'])
		->and($click['order'][0])->toBe('ORDER BY `description` DESC');
});

test('a stored sort is rebuilt without the columns that are refused', function () {
	$result = sort_request(['script' => '/pollers.php', 'request' => [],
		'session' => [
			'valid_sort_columns' => ['/pollers.php' => ['name', 'status', 'dbpass']],
			'sort_data'          => ['0_pollers' => ['dbpass' => 'ASC', 'status' => 'DESC', 'last_update' => 'ASC']],
			'sort_string'        => ['0_pollers' => 'ORDER BY dbpass ASC, status DESC, last_update ASC'],
		],
		'tables' => [['prefix' => 'sess_pollers', 'default' => 'name', 'columns' => ['name', 'status']]]]);

	expect($result['order'])->toBe(['ORDER BY `status` DESC, `name` ASC'])
		->and($result['session']['sort_data']['0_pollers'])->toBe(['status' => 'DESC', 'name' => 'ASC']);
});

test('a key the header registers itself is sorted on as the page wrote it', function () {
	$table = ['prefix' => 'sess_net', 'default' => 'name', 'raw' => true, 'columns' => ['name', 'INET_ATON(subnet)']];

	expect(sort_requests('/networks.php', [
		[[], [$table]],
		[['sort_column' => 'INET_ATON(subnet)', 'sort_direction' => 'DESC', 'add' => 'reset'], [$table]],
		[['sort_column' => 'SLEEP(1)', 'add' => 'reset'], [$table]],
	]))->toBe(['ORDER BY `name` ASC', 'ORDER BY INET_ATON(subnet) DESC', 'ORDER BY `name` ASC']);
});

test('a page without a declared default sorts on the columns its header has listed', function () {
	$table = ['default' => null, 'renderer' => 'plain', 'columns' => ['hostname', 'status', 'nosort']];

	expect(sort_requests('/plugins/example/example.php', [
		[['sort_column' => 'status'], [$table]],
		[['sort_column' => 'status'], [$table]],
		[['sort_column' => 'snmp_community', 'add' => 'reset'], [$table]],
	]))->toBe(['', 'ORDER BY `status` ASC', '']);
});
