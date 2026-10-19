<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function request_coverage_normalization(bool $admitted): string {
	if (!$admitted) {
		throw new RuntimeException('unexecuted denial');
	}

	return implode(' ', [
		'first',
		$admitted ? 'admitted' : 'unexecuted alternative',
		'last',
	]);
}
