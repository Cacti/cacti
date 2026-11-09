<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * After Boost, dsstats_launch_children('bmaster') starts one bchild process
 * per configured thread, and each bchild collects its own slice.  The parent
 * ran dsstats_get_and_store_ds_avgpeak_values() for thread 0 as well, which
 * with one thread read every file a second time.
 */

test('dsstats_boost_bottom leaves the collection to the children it launched', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/dsstats.php');

	expect(preg_match('/^function dsstats_boost_bottom\(.*?^}\R/ms', $source, $matches))->toBe(1)
		->and($matches[0])->toContain("dsstats_launch_children('bmaster');")
		->and($matches[0])->not->toContain('dsstats_get_and_store_ds_avgpeak_values(');
});
