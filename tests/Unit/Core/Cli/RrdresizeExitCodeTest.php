<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('rrdresize reports a missing required data template as failure', function () : void {
	$source = file_get_contents(dirname(__DIR__, 4) . '/cli/rrdresize.php');
	expect($source)->not->toBeFalse();

	if ($source === false) {
		return;
	}

	$start  = strpos($source, 'if (!$data_template_id)');
	expect($start)->not->toBeFalse();

	if ($start === false) {
		return;
	}

	$body = substr($source, $start, 220);

	expect($body)->toContain('exit(1);')
		->not->toContain('exit(0);');
});
