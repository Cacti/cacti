<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

test('issue 7490 graph template filters require a complete valid value', function (): void {
	$source = file_get_contents(dirname(__DIR__, 4) . '/graphs.php');

	expect($source)->toContain("'regexp' => '/^(cg_[0-9]+|dq_[0-9]+|-?[0-9]+)$/'");

	$regexp = '/^(cg_[0-9]+|dq_[0-9]+|-?[0-9]+)$/';

	expect(filter_var('cg_12', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regexp]]))->toBe('cg_12')
		->and(filter_var('dq_7', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regexp]]))->toBe('dq_7')
		->and(filter_var('-1', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regexp]]))->toBe('-1')
		->and(filter_var('cg_12 OR 1=1', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regexp]]))->toBeFalse()
		->and(filter_var("dq_7\n1", FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regexp]]))->toBeFalse();
});
