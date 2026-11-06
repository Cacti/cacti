<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

const CACTI_WEB           = true;
const MESSAGE_LEVEL_ERROR = 2;
$messages                 = [];

function cacti_sizeof(mixed $value): int {
	return is_array($value) ? count($value) : 0;
}

function __(string $message): string {
	return $message;
}

function raise_message(mixed ...$args): void {
	$GLOBALS['messages'][] = $args;
}

require dirname(__DIR__, 2) . '/lib/api_automation.php';

$input     = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$range     = $input['range'];
$detail    = automation_get_network_info($range);
$samples   = [];
$addresses = null;

if (is_array($detail)) {
	foreach ($input['offsets'] ?? [] as $offset) {
		$samples[] = automation_get_next_host($detail['start'], $detail['count'], $offset, $range);
	}

	if ($detail['count'] <= 4096 && $detail['count'] > 0) {
		$addresses = [];

		for ($offset = 0; $offset <= $detail['count']; $offset++) {
			$address = automation_get_next_host($detail['start'], $detail['count'], $offset, $range);

			if ($address === false) {
				break;
			}
			$addresses[] = $address;
		}
	}
}

print json_encode(['detail' => $detail, 'samples' => $samples, 'addresses' => $addresses,
	'messages'                 => $messages], JSON_THROW_ON_ERROR);
