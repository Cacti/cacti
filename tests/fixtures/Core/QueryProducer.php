<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || getenv('CACTI_E2E_DISPOSABLE') !== '1') {
	exit(2);
}

[$scenario, $action, $field, $index] = array_pad(array_slice($argv, 1), 4, '');
$rows                                = $scenario === 'shrink' ? ['2' => 'port beta'] : ['1' => 'port alpha', '2' => 'port beta'];

switch ($action) {
	case 'index':
		print implode("\n", array_keys($rows)) . "\n";

		break;
	case 'count':
		print count($rows) . "\n";

		break;
	case 'query':
		foreach ($rows as $key => $label) {
			$value = $field === 'label' ? $label : $key;
			print $key . '!' . $value . "\n";
		}

		break;
	case 'get':
		if (!isset($rows[$index])) {
			exit(1);
		}
		print ($field === 'label' ? $rows[$index] : $index) . "\n";

		break;
	default:
		exit(1);
}
