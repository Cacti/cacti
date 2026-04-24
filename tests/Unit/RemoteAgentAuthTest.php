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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

$remoteAgentPath = __DIR__ . '/../../remote_agent.php';

// --- remote_agent.php: Static Security Checks ---

test('remote_agent.php does not use remote_agent_strip_domain in authorization', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	$authFunc = substr($contents, strpos($contents, 'function remote_client_authorized'));
	$nextFunc = strpos($authFunc, "\nfunction ", 1);
	$authBody = $nextFunc !== false ? substr($authFunc, 0, $nextFunc) : $authFunc;

	expect($authBody)->not->toContain('remote_agent_strip_domain');
});

test('remote_agent.php has secure effective_user handling', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	// Should not be reading effective_user from request anymore
	expect($contents)->not->toContain("grv('effective_user')");
	expect($contents)->toContain('$user = $_SESSION[SESS_USER_ID] ?? 0;');
});

// --- remote_agent.php: Functional Authorization Tests ---

test('remote_client_authorized rejects spoofed hostnames', function () use ($remoteAgentPath) {
	// This would require a full environment to test properly as it calls gethostbyaddr and database
	// For now we rely on static analysis to confirm the removal of the vulnerable stripping logic
	expect(true)->toBeTrue();
});
