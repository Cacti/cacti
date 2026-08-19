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

function queue_e2e_handlers(array $handlers) : array {
	$handlers['queue-e2e.record'] = static function (CactiQueueEnvelope $message) : void {
		api_queue_renew($message, 60);
		$stored = db_execute_prepared('INSERT INTO queue_e2e_results
			(message_id, payload, handled_at)
			VALUES (?, ?, NOW())',
			[$message->messageId(), api_queue_json_encode($message->payload())]);

		if (!$stored) {
			throw new RuntimeException('Unable to store the queue E2E result.');
		}
	};

	return $handlers;
}
