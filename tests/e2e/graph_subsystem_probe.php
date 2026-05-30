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
*/

chdir(dirname(__DIR__, 2));

require_once __DIR__ . '/../../include/global.php';
require_once CACTI_PATH_LIBRARY . '/functions.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

function graph_probe_assert(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: $message\n");
		exit(1);
	}
}

function graph_probe_file_contains(string $file, string $needle): bool {
	$contents = file_get_contents($file);

	return $contents !== false && strpos($contents, $needle) !== false;
}

$root = dirname(__DIR__, 2);

graph_probe_assert(
	graph_probe_file_contains($root . '/graph_image.php', '&effective_user='),
	'remote graph image requests must forward the effective user'
);

graph_probe_assert(
	graph_probe_file_contains($root . '/graph_image.php', 'rawurlencode((string) $variable)') &&
	graph_probe_file_contains($root . '/graph_image.php', 'rawurlencode((string) $value)'),
	'remote graph image request parameters must be URL encoded'
);

graph_probe_assert(
	graph_probe_file_contains($root . '/graph_image.php', '$image_begin_pos !== false') &&
	graph_probe_file_contains($root . '/graph_image.php', '$image_data_pos !== false'),
	'remote graph image responses must reject output without an image marker'
);

graph_probe_assert(
	graph_probe_file_contains($root . '/lib/rrd.php', '$last_graph_cf[$graph_item[\'data_source_name\']][$graph_item[\'local_data_template_rrd_id\']]'),
	'GPRINT consolidation-function cache must be keyed by data source and template RRD id'
);

graph_probe_assert(
	graph_probe_file_contains($root . '/graph_realtime.php', '$graph_contents === false && $output !== false'),
	'realtime graph output must fall back to returned graph bytes when the cache file is unavailable or unreadable'
);

graph_probe_assert(
	graph_probe_file_contains($root . '/lib/functions.php', 'finally') &&
	graph_probe_file_contains($root . '/lib/functions.php', 'restore_error_handler();'),
	'remote collector must restore the temporary error handler on every exit path'
);

$original_remote_port = db_fetch_cell_prepared(
	'SELECT value FROM settings WHERE name = ?',
	array('remote_agent_port')
);

$original_poller_host = db_fetch_cell_prepared(
	'SELECT hostname FROM poller WHERE id = ?',
	array(1)
);

try {
	db_execute_prepared(
		'REPLACE INTO settings (name, value) VALUES (?, ?)',
		array('remote_agent_port', '1')
	);

	db_execute_prepared(
		'UPDATE poller SET hostname = ? WHERE id = ?',
		array('127.0.0.1', 1)
	);

	$sentinel_handler = static function (): bool {
		return false;
	};

	set_error_handler($sentinel_handler);

	$result = call_remote_data_collector(
		1,
		'/cacti/remote_agent.php?action=graph_json&local_graph_id=1',
		'GRAPH_E2E'
	);

	$previous_handler = set_error_handler(static function (): bool {
		return false;
	});

	restore_error_handler();
	restore_error_handler();

	graph_probe_assert($result === false, 'closed remote collector connection must fail cleanly');
	graph_probe_assert($previous_handler === $sentinel_handler, 'remote collector must restore the prior error handler after failure');
} finally {
	if ($original_remote_port === false || $original_remote_port === null) {
		db_execute_prepared('DELETE FROM settings WHERE name = ?', array('remote_agent_port'));
	} else {
		db_execute_prepared(
			'REPLACE INTO settings (name, value) VALUES (?, ?)',
			array('remote_agent_port', $original_remote_port)
		);
	}

	if ($original_poller_host !== false && $original_poller_host !== null) {
		db_execute_prepared(
			'UPDATE poller SET hostname = ? WHERE id = ?',
			array($original_poller_host, 1)
		);
	}
}

print "PASS graph subsystem docker probe\n";
