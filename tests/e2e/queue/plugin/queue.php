<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function queue_e2e_handlers(array $handlers) : array {
	$handlers['queue-e2e.fail'] = static function (CactiQueueMessage $message) : void {
		throw new RuntimeException('Injected queue handler failure.');
	};
	$handlers['queue-e2e.record'] = static function (CactiQueueMessage $message) : void {
		api_queue_renew($message, 60);
		$envelope = $GLOBALS['cacti_queue_active_envelopes'][spl_object_id($message)];
		$stored   = db_execute_prepared('INSERT INTO queue_e2e_results
			(message_id, payload, handled_at)
			VALUES (?, ?, NOW())',
			[api_queue_message_id_from_envelope($envelope), api_queue_json_encode($message->payload())]);

		if (!$stored) {
			throw new RuntimeException('Unable to store the queue E2E result.');
		}
	};

	return $handlers;
}
