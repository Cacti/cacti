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
 * appendHeaderSuppression() guarded "already present?" with
 * strpos($url, 'header=false') < 0. strpos() returns false on no-match
 * and a non-negative integer on a hit; "< 0" is therefore always false
 * because false < 0 is also false. The guard never fired and the function
 * appended &header=false on every call, eventually producing URLs like
 *   ?action=edit&header=false&header=false&header=false
 * The fix is the canonical `=== false` strpos idiom.
 */

$source = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('lib/functions.php uses === false in appendHeaderSuppression', function () use ($source) {
	$start = strpos($source, 'function appendHeaderSuppression(');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, $end !== false ? $end - $start : 400);

	expect($body)->toContain("strpos(\$url, 'header=false') === false");
	expect(strpos($body, "strpos(\$url, 'header=false') < 0"))
		->toBeFalse('the old "< 0" guard must be gone');
});

/* Local copy of the fixed function. We avoid loading lib/functions.php
 * here because that file requires the full Cacti bootstrap. The shape
 * mirrors the production definition; the source-pattern test above
 * pins the production code to this same shape. */
if (!function_exists('_test_appendHeaderSuppression')) {
	function _test_appendHeaderSuppression($url) {
		if (strpos($url, 'header=false') === false) {
			return $url . (strpos($url, '?') ? '&' : '?') . 'header=false';
		}

		return $url;
	}
}

test('appendHeaderSuppression is idempotent on repeated calls', function () {
	$first  = _test_appendHeaderSuppression('graph.php?action=edit');
	$second = _test_appendHeaderSuppression($first);

	expect($first)->toBe('graph.php?action=edit&header=false');
	expect($second)->toBe($first);

	/* Already present and no querystring delimiter swap. */
	expect(_test_appendHeaderSuppression('graph.php?header=false'))->toBe('graph.php?header=false');
});
