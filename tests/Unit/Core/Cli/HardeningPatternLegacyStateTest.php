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

namespace CactiHardeningLegacyStateFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
\cacti_fixture_source_function(dirname(__DIR__, 4) . '/tests/tools/check_hardening_patterns.php', 'legacy_serialization_escape_only', __NAMESPACE__);

test('existing serialized state may gain attribute escaping without introducing another field', function () {
	$raw     = 'print "<input type=\'hidden\' value=\'" . serialize($items) . "\'>";';
	$escaped = str_replace('serialize($items)', 'html_escape_attr(serialize($items))', $raw);

	expect(legacy_serialization_escape_only($escaped, [$raw], [$escaped]))->toBeTrue();
	expect(legacy_serialization_escape_only($escaped, [], [$escaped]))->toBeFalse();
	expect(legacy_serialization_escape_only($escaped, [$raw], [$raw, $escaped]))->toBeFalse();
	expect(legacy_serialization_escape_only($escaped, [$raw], [$escaped, $escaped]))->toBeFalse();
	expect(legacy_serialization_escape_only($raw, [$raw], [$raw]))->toBeFalse();
	expect(legacy_serialization_escape_only(str_replace('$items', '$different', $escaped), [$raw], [$escaped]))->toBeFalse();
	expect(legacy_serialization_escape_only(str_replace('html_escape_attr', 'html_escape', $escaped), [$raw], [$escaped]))->toBeFalse();
	expect(legacy_serialization_escape_only(str_replace('serialize($items)', 'serialize($items, 1)', $escaped), [$raw], [$escaped]))->toBeFalse();
});
