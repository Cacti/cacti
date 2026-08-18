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

/*
 * sanitize_uri() decided whether a graph_view.php URI already carried an
 * action, and whether it already carried a query string, with bare strpos()
 * calls. strpos() returns 0 for a match at offset 0, which is falsy, so a URI
 * that opened with either token was treated as though it lacked it.
 */

require_once CACTI_PATH_LIBRARY . '/functions.php';
require_once CACTI_PATH_LIBRARY . '/html_utility.php';
require_once CACTI_PATH_LIBRARY . '/html.php';

beforeEach(function () : void {
	global $_CACTI_REQUEST;

	$_REQUEST['action']       = 'preview';
	$_CACTI_REQUEST['action'] = 'preview';
});

afterEach(function () : void {
	global $_CACTI_REQUEST;

	unset($_REQUEST['action']);
	unset($_CACTI_REQUEST['action']);
});

test('an action at the head of the uri is not appended a second time', function () {
	$uri = 'action=list&page=graph_view.php';

	// the offset the old test could not see
	expect(strpos($uri, 'action='))->toBe(0);

	expect(sanitize_uri($uri))->toBe($uri)
		->and(substr_count(sanitize_uri($uri), 'action='))->toBe(1);
});

test('a leading question mark yields an ampersand, not a second question mark', function () {
	$uri = '?a=1&x=graph_view.php';

	expect(strpos($uri, '?'))->toBe(0);

	$result = sanitize_uri($uri);

	expect($result)->toBe('?a=1&x=graph_view.php&action=preview')
		->and(substr_count($result, '?'))->toBe(1);
});

test('an action still gets appended when the uri genuinely lacks one', function () {
	expect(sanitize_uri('graph_view.php?local_graph_id=3'))
		->toBe('graph_view.php?local_graph_id=3&action=preview');
});

test('a uri that is not graph_view is left alone', function () {
	expect(sanitize_uri('index.php?a=1'))->toBe('index.php?a=1');
});

test('selected graphs refuses a nested array, as its contract states', function () {
	expect(sanitize_unserialize_selected_graphs(serialize([1, 2, '3'])))->toBe([1, 2, '3'])
		->and(sanitize_unserialize_selected_graphs(serialize([1, [2]])))->toBeFalse()
		->and(sanitize_unserialize_selected_graphs(serialize([1, 'abc'])))->toBeFalse()
		->and(sanitize_unserialize_selected_graphs(serialize([1, true])))->toBeFalse();
});

test('filter_value still escapes markup and the grave accent', function () {
	expect(filter_value('a<b>c', ''))->toBe('a&lt;b&gt;c')
		->and(filter_value('a`b', ''))->toContain('&#96;');
});
