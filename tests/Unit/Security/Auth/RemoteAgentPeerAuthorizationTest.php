<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-h96j-8xhc-2p3w: remote_agent.php authorized the client on
 * get_client_addr(), which honours client-settable proxy headers
 * (X-Forwarded-For), allowing IP spoofing. The check must use the genuine TCP
 * peer.
 */

$root = dirname(__DIR__, 4);

test('remote_agent authorizes on the genuine peer address, not forwarded headers', function () use ($root) {
	$source = file_get_contents($root . '/remote_agent.php');

	$start = strpos($source, 'function remote_client_authorized(');
	expect($start)->not->toBeFalse();

	$body = substr($source, $start, 1200);

	expect($body)->toContain("\$client_addr = \$_SERVER['REMOTE_ADDR']")
		->and($body)->not->toContain('$client_addr = get_client_addr();');
});
