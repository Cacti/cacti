<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

/*
 * A sort column is checked against the columns the script's table headers list
 * as sortable plus the page's default column. The list does not depend on the
 * action or tab a request names, and a page that has not drawn a header yet
 * sorts on its default column only. Each probe call is one page request in its
 * own process, running the production sort and header code against the header
 * definitions of real pages.
 */

function sort_probe(string $mode, string $case = ''): array {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/SortLookupChecks.php', [$mode, $case]);
}

/*
 * Runs requests one after another, each starting from the session the previous
 * one left behind, and returns the ORDER BY clause of every request.
 */
function sort_requests(array $page, array $requests): array {
	$orders  = [];
	$session = [];

	foreach ($requests as $request) {
		$result   = sort_probe('request', json_encode($page + ['request' => $request, 'session' => $session], JSON_THROW_ON_ERROR));
		$orders[] = $result['order'];
		$session  = $result['session'];
	}

	return $orders;
}

test('a table request applies the sort column rules', function (string $scenario, string $order) {
	expect(sort_probe('sort', $scenario)['order'])->toBe($order);
})->with([
	'first view uses the default sort'             => ['first-view', 'ORDER BY `name` ASC'],
	'page without a column list uses its default'  => ['unregistered-page', 'ORDER BY `name` ASC'],
	'listed column joins the stored sort'          => ['listed-column', 'ORDER BY `name` ASC, `graphs` DESC'],
	'unsortable column gives way to the default'   => ['unsortable-column', 'ORDER BY `graphs` DESC, `name` ASC'],
	'unlisted column gives way to the default'     => ['unlisted-column', 'ORDER BY `name` ASC'],
	'refused column without a default'             => ['no-default', ''],
	'another action and tab, unlisted column'      => ['other-action-unlisted', 'ORDER BY `name` ASC'],
	'another action and tab, listed column'        => ['other-action-listed', 'ORDER BY `graphs` ASC'],
	'column listed by another script'              => ['other-script', 'ORDER BY `name` ASC'],
	'single column sort on a listed column'        => ['single-column', 'ORDER BY NATURAL_SORT_KEY(`hostname`) DESC'],
	'single column sort on an unlisted column'     => ['single-column-refused', 'ORDER BY `description` ASC'],
	'page default outside the drawn header'        => ['default-outside-header', 'ORDER BY `pi`.`plugin` ASC'],
	'wrapped column listed by the page'            => ['wrapped-column', 'ORDER BY INET_ATON(hostname) ASC'],
	'stored sort with an unlisted column'          => ['stale-stored-sort', 'ORDER BY `username` DESC'],
	'stored clause without its columns'            => ['orphan-stored-sort', ''],
	'stored sort with listed columns'              => ['stored-sort', 'ORDER BY `time` DESC, `username` ASC'],
	'removing one stored column'                   => ['remove-column', 'ORDER BY name ASC, templates DESC'],
	'removing the only stored column'              => ['remove-last-column', 'ORDER BY `name` ASC'],
]);

test('the CDEF list keeps its default, multiple column and single column sorts across requests', function () {
	expect(sort_requests(['script' => 'cdef.php', 'columns' => 'cdef.php'], [
		[],
		['sort_column' => 'graphs', 'sort_direction' => 'DESC'],
		['sort_column' => 'nosort', 'sort_direction' => 'DESC'],
		['sort_column' => 'hash'],
		['sort_column' => 'templates', 'sort_direction' => 'DESC', 'add' => 'reset'],
		[],
		['clear' => '1'],
	]))->toBe([
		'ORDER BY `name` ASC',
		'ORDER BY `name` ASC, `graphs` DESC',
		'ORDER BY `name` ASC, `graphs` DESC',
		'ORDER BY `name` ASC, `graphs` DESC',
		'ORDER BY `templates` DESC',
		'ORDER BY `templates` DESC',
		'ORDER BY `name` ASC',
	]);
});

test('the device list sorts on its listed columns and uses its default for columns it does not show', function () {
	expect(sort_requests(['script' => 'host.php', 'columns' => 'host.php', 'default' => 'description', 'natural' => true], [
		[],
		['sort_column' => 'hostname', 'sort_direction' => 'DESC'],
		['sort_column' => 'snmp_community'],
		['sort_column' => 'snmp_password', 'add' => 'reset'],
		['sort_column' => 'status', 'reset' => '1'],
	]))->toBe([
		'ORDER BY `description` ASC',
		'ORDER BY `description` ASC, NATURAL_SORT_KEY(`hostname`) DESC',
		'ORDER BY `description` ASC, NATURAL_SORT_KEY(`hostname`) DESC',
		'ORDER BY `description` ASC',
		'ORDER BY `status` ASC',
	]);
});

test('the data collector list uses its default instead of a credential column before and after its header is drawn', function () {
	expect(sort_requests(['script' => 'pollers.php', 'columns' => 'pollers.php'], [
		['sort_column' => 'dbpass'],
		['sort_column' => 'dbpass'],
		['sort_column' => 'dbsslkey', 'add' => 'reset'],
		['sort_column' => 'poller.hostname', 'sort_direction' => 'DESC', 'add' => 'reset'],
	]))->toBe([
		'ORDER BY `name` ASC',
		'ORDER BY `name` ASC',
		'ORDER BY `name` ASC',
		'ORDER BY `poller`.`hostname` DESC',
	]);
});

test('the plugin list keeps its default sort and earlier columns when the filter state changes its header', function () {
	$page    = ['script' => 'plugins.php', 'header' => 'plain', 'default' => 'pi.plugin'];
	$session = [];
	$orders  = [];

	foreach ([
		['plugins.php:archive', []],
		['plugins.php:archive', []],
		['plugins.php:archive', ['sort_column' => 'pa.compat', 'sort_direction' => 'DESC', 'add' => 'reset']],
		['plugins.php:loaded', []],
		['plugins.php:loaded', []],
		['plugins.php:loaded', ['sort_column' => 'pi.version', 'sort_direction' => 'ASC', 'add' => 'reset']],
	] as [$header, $request]) {
		$result   = sort_probe('request', json_encode($page + ['columns' => $header, 'request' => $request, 'session' => $session], JSON_THROW_ON_ERROR));
		$orders[] = $result['order'];
		$session  = $result['session'];
	}

	expect($orders)->toBe([
		'ORDER BY `pi`.`plugin` ASC',
		'ORDER BY `pi`.`plugin` ASC',
		'ORDER BY `pa`.`compat` DESC',
		'ORDER BY `pa`.`compat` DESC',
		'ORDER BY `pa`.`compat` DESC',
		'ORDER BY `pi`.`version` ASC',
	]);
});

test('column checks and the group list query accept only listed columns', function () {
	expect(sort_probe('helpers'))->toBe(['columns' => 22, 'group_queries' => 11]);
});
