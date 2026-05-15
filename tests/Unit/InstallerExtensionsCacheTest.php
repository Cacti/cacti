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
 * Installer::getModules() cached its result in $this->extensions but the
 * guard read
 *   if (isset($this->extensions) || empty($this->extensions))
 * which is always true on the first call (isset is false, empty is true)
 * and always true on every subsequent call (isset is now true). The cache
 * never paid off; utility_php_extensions() ran on every request. The fix
 * inverts the isset() check so the cache is only rebuilt when the value
 * has not been computed yet or is empty.
 */

$source = file_get_contents(__DIR__ . '/../../lib/installer.php');

$start = strpos($source, 'private function getModules()');
expect($start)->not->toBeFalse();

$end  = strpos($source, "\n\t}", $start);
$body = substr($source, $start, $end !== false ? $end - $start : 2000);

test('getModules guard begins with !isset', function () use ($body) {
	expect($body)->toContain('!isset($this->extensions) || empty($this->extensions)');
});

test('getModules no longer contains the original always-true guard', function () use ($body) {
	/* The buggy form started "if (isset(...". The fixed form starts
	 * "if (!isset(...", so this substring cannot match the fix and is a
	 * clean negative check for the regression. */
	expect($body)->not->toContain('if (isset($this->extensions) || empty($this->extensions))');
});
