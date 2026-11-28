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

/*
 * Security hardening regression tests for authentication, tree/branch/site
 * authorization, realtime graphing, external links, graph source disclosure,
 * session invalidation on password reset, automation credential column
 * filtering, and filepath validation in settings.php.
 */

$repoRoot = dirname(__DIR__, 3);

if (!function_exists('cacti_path_is_within')) {
	require_once $repoRoot . '/lib/functions.php';
}

/**
 * Extract a named PHP function body from a source file for targeted assertions.
 */
function extract_php_function_source($filePath, $functionName) {
	$src   = file_get_contents($filePath);
	$start = strpos($src, 'function ' . $functionName . '(');

	if ($start === false) {
		return '';
	}

	$bracePos = strpos($src, '{', $start);

	if ($bracePos === false) {
		return '';
	}

	$depth = 0;
	$len   = strlen($src);

	for ($i = $bracePos; $i < $len; $i++) {
		if ($src[$i] === '{') {
			$depth++;
		} elseif ($src[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($src, $start, $i - $start + 1);
			}
		}
	}

	return '';
}

describe('lib/auth.php authorization hardening', function () use ($repoRoot) {
	$authFile = $repoRoot . '/lib/auth.php';

	it('requires enabled groups in cacti_authorize_has_realm()', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'cacti_authorize_has_realm');

		expect($fn)->not->toBe('')
			->and($fn)->toContain('INNER JOIN user_auth_group AS uag')
			->and($fn)->toContain('ON uag.id = ugr.group_id')
			->and($fn)->toContain("AND uag.enabled = 'on'");
	});

	it('fails closed in get_allowed_tree_content() when no trees are allowed or requested tree is denied', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'get_allowed_tree_content');

		expect($fn)->not->toBe('')
			->and($fn)->toMatch('/if\s*\(\s*!cacti_sizeof\(\$trees\)\s*\)\s*\{\s*return\s+array\(\)\s*;/s')
			->and($fn)->toMatch('/if\s*\(\s*!isset\(\$trees\[\(int\)\s*\$tree_id\]\)\s*\)\s*\{\s*return\s+array\(\)\s*;/s')
			->and($fn)->toContain("gt.enabled = 'on'");
	});

	it('fails closed in get_allowed_branches() when no trees or no permitted devices exist', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'get_allowed_branches');

		expect($fn)->not->toBe('')
			->and($fn)->toMatch('/if\s*\(\s*!cacti_sizeof\(\$trees\)\s*\)\s*\{[^}]*return\s+array\(\)\s*;/s')
			->and($fn)->toContain("\$sql_hosts_where = 'AND 1 = 0';");
	});

	it('filters get_allowed_sites() by permitted devices and fails closed when none are allowed', function () use ($authFile) {
		$fn = extract_php_function_source($authFile, 'get_allowed_sites');

		expect($fn)->not->toBe('')
			->and($fn)->toContain('get_simple_device_perms($user_id)')
			->and($fn)->toContain('get_allowed_devices(')
			->and($fn)->toMatch('/if\s*\(\s*!cacti_sizeof\(\$hosts\)\s*\)\s*\{[^}]*return\s+array\(\)\s*;/s')
			->and($fn)->toContain('h.id IN (');
	});
});

describe('graph_view.php authorization and CSRF hardening', function () use ($repoRoot) {
	$src = file_get_contents($repoRoot . '/graph_view.php');

	it('enforces POST, CSRF check, and report realms on action=ajax_reports', function () use ($src) {
		$pos   = strpos($src, "case 'ajax_reports':");
		$end   = strpos($src, "case 'update_timespan':", $pos);
		$block = substr($src, $pos, $end - $pos);

		expect($block)->toContain("\$_SERVER['REQUEST_METHOD'] !== 'POST'")
			->and($block)->toContain('http_response_code(405)')
			->and($block)->toContain('csrf_check(false)')
			->and($block)->toContain('!is_realm_allowed(21) && !is_realm_allowed(22)')
			->and($block)->toContain('!is_graph_allowed($local_graph_id)')
			->and($block)->toContain('http_response_code(403)');
	});

	it('enforces show_tree view and is_tree_allowed() on action=get_node', function () use ($src) {
		$pos   = strpos($src, "case 'get_node':");
		$end   = strpos($src, "case 'tree_content':", $pos);
		$block = substr($src, $pos, $end - $pos);

		expect($block)->toContain("!is_view_allowed('show_tree')")
			->and($block)->toContain('!is_tree_allowed($tree_id)')
			->and($block)->toContain('http_response_code(403)');

		expect(strpos($block, '!is_tree_allowed($tree_id)'))
			->toBeLessThan(strpos($block, 'api_tree_get_main('));
	});
});

describe('graph_realtime.php hardening', function () use ($repoRoot) {
	$src = file_get_contents($repoRoot . '/graph_realtime.php');

	it('enforces realtime_enabled with HTTP 403 before action switch dispatch', function () use ($src) {
		$enabledPos = strpos($src, "read_config_option('realtime_enabled') != 'on'");
		$switchPos  = strpos($src, "switch (get_request_var('action'))");

		expect($enabledPos)->not->toBeFalse()
			->and($switchPos)->not->toBeFalse()
			->and($enabledPos)->toBeLessThan($switchPos)
			->and(substr($src, $enabledPos, $switchPos - $enabledPos))->toContain('http_response_code(403)');
	});

	it('anchors graph_nolegend regex and validates sess_realtime_hash as hex', function () use ($src) {
		expect($src)->toContain("'/^(true|false)$/'")
			->and($src)->toContain("preg_match('/^[a-fA-F0-9]+$/', \$_SESSION['sess_realtime_hash'])");

		expect((bool) preg_match('/^(true|false)$/', 'true'))->toBeTrue();
		expect((bool) preg_match('/^(true|false)$/', 'false'))->toBeTrue();
		expect((bool) preg_match('/^(true|false)$/', 'true_extra'))->toBeFalse();
		expect((bool) preg_match('/^[a-fA-F0-9]+$/', 'deadbeef0123456789'))->toBeTrue();
		expect((bool) preg_match('/^[a-fA-F0-9]+$/', 'invalid-hash'))->toBeFalse();
	});

	it('checks is_graph_allowed() before shell_exec() and before cache file read in view', function () use ($src) {
		$checkPos = strpos($src, '$local_graph_id <= 0 || !is_graph_allowed($local_graph_id)');
		$shellPos = strpos($src, 'shell_exec(');

		expect($checkPos)->not->toBeFalse()
			->and($shellPos)->not->toBeFalse()
			->and($checkPos)->toBeLessThan($shellPos)
			->and($src)->toContain('cacti_escapeshellarg($hash)')
			->and($src)->toContain('$ds_step = (int) $graph_data_array[\'ds_step\'];');

		$viewPos   = strpos($src, "case 'view':");
		$viewBlock = substr($src, $viewPos, 400);

		expect($viewBlock)->toContain('$local_graph_id <= 0 || !is_graph_allowed($local_graph_id)')
			->and($viewBlock)->toContain('http_response_code(403)')
			->and(strpos($viewBlock, 'is_graph_allowed($local_graph_id)'))
			->toBeLessThan(strpos($viewBlock, 'file_get_contents('));
	});

	it('skips set_user_setting() when Guest account is active', function () use ($src) {
		expect(substr_count($src, 'if (cacti_user_can_save_own_settings())'))->toBeGreaterThanOrEqual(2);
	});
});

describe('link.php authentication, enabled check, and content containment', function () use ($repoRoot) {
	$src = file_get_contents($repoRoot . '/link.php');

	it('includes auth.php, requires enabled = on, and enforces cacti_path_is_within()', function () use ($src) {
		expect($src)->toContain("include('./include/auth.php');")
			->and($src)->toContain("\$user_auth_realm_filenames['link.php'] = -1;")
			->and($src)->toContain("AND enabled = 'on'")
			->and($src)->toContain('cacti_path_is_within($file, $basepath)')
			->and($src)->toContain('is_file($file)');
	});

	it('cacti_path_is_within() rejects sibling prefix directories', function () {
		$tmpBase    = sys_get_temp_dir() . '/cacti_link_test_' . uniqid('', true);
		$contentDir = $tmpBase . '/content';
		$siblingDir = $tmpBase . '/content_outside';

		mkdir($contentDir, 0700, true);
		mkdir($siblingDir, 0700, true);

		$insideFile  = $contentDir . '/page.html';
		$siblingFile = $siblingDir . '/page.html';

		file_put_contents($insideFile, 'ok');
		file_put_contents($siblingFile, 'no');

		try {
			expect(cacti_path_is_within($insideFile, $contentDir))->toBeTrue();
			expect(cacti_path_is_within($siblingFile, $contentDir))->toBeFalse();
		} finally {
			@unlink($insideFile);
			@unlink($siblingFile);
			@rmdir($contentDir);
			@rmdir($siblingDir);
			@rmdir($tmpBase);
		}
	});
});

describe('graph source print_source realm 27 gating', function () use ($repoRoot) {
	it('gates action=properties behind is_realm_allowed(27) in graph.php', function () use ($repoRoot) {
		$src   = file_get_contents($repoRoot . '/graph.php');
		$pos   = strpos($src, "case 'properties':");
		$block = substr($src, $pos, 350);

		expect($block)->toContain('!is_realm_allowed(27)')
			->and(strpos($block, '!is_realm_allowed(27)'))
			->toBeLessThan(strpos($block, "\$graph_data_array['print_source']"));
	});

	it('gates show_source to print_source behind is_realm_allowed(27) across all graph endpoints', function () use ($repoRoot) {
		$files = array(
			'graph_image.php',
			'graph_json.php',
			'graph_xport.php',
			'graph_realtime.php',
		);

		foreach ($files as $file) {
			$src = file_get_contents($repoRoot . '/' . $file);

			expect($src)->toContain("if (!isempty_request_var('show_source') && is_realm_allowed(27))");
		}
	});
});

describe('user_admin.php session invalidation on password reset', function () use ($repoRoot) {
	it('deletes active sessions for target user while preserving self session when resetting own password', function () use ($repoRoot) {
		$src   = file_get_contents($repoRoot . '/user_admin.php');
		$start = strpos($src, '$old_password = db_fetch_cell_prepared');
		$end   = strpos($src, '/* check duplicate username */', $start);
		$block = substr($src, $start, $end - $start);

		expect($block)->toContain('DELETE FROM user_auth_cache')
			->and($block)->toContain('DELETE FROM sessions')
			->and($block)->toContain('AND id != ?')
			->and($block)->toContain('session_id()');
	});
});

describe('automation rules sensitive host credential column rejection', function () use ($repoRoot) {
	$sensitiveColumns = array(
		'snmp_community',
		'snmp_username',
		'snmp_password',
		'snmp_auth_protocol',
		'snmp_priv_passphrase',
		'snmp_priv_protocol',
		'snmp_context',
		'snmp_engine_id',
	);

	it('identifies all sensitive host credential columns in cacti_automation_is_sensitive_host_field()', function () use ($repoRoot, $sensitiveColumns) {
		$fn = extract_php_function_source($repoRoot . '/lib/auth.php', 'cacti_automation_is_sensitive_host_field');

		expect($fn)->not->toBe('')
			->and($fn)->toContain('strtolower(trim((string) $field))')
			->and($fn)->toContain("strrpos(\$normalized, '.')")
			->and($fn)->toContain('in_array($column, $sensitive_host_columns, true)');

		foreach ($sensitiveColumns as $col) {
			expect($fn)->toContain("'" . $col . "'");
		}

		if (function_exists('cacti_automation_is_sensitive_host_field')) {
			foreach ($sensitiveColumns as $col) {
				expect(cacti_automation_is_sensitive_host_field($col))->toBeTrue();
				expect(cacti_automation_is_sensitive_host_field('h.' . $col))->toBeTrue();
				expect(cacti_automation_is_sensitive_host_field('H.' . strtoupper($col)))->toBeTrue();
			}

			expect(cacti_automation_is_sensitive_host_field('h.description'))->toBeFalse();
		}
	});

	it('enforces cacti_automation_is_sensitive_host_field() across tree and graph rule save handlers', function () use ($repoRoot) {
		$treeSrc  = file_get_contents($repoRoot . '/automation_tree_rules.php');
		$graphSrc = file_get_contents($repoRoot . '/automation_graph_rules.php');

		expect(substr_count($treeSrc, 'cacti_automation_is_sensitive_host_field('))->toBeGreaterThanOrEqual(2);
		expect(substr_count($graphSrc, 'cacti_automation_is_sensitive_host_field('))->toBeGreaterThanOrEqual(2);
	});
});

describe('settings.php filepath validation persistence guard', function () use ($repoRoot) {
	it('sets $continue = false when is_valid_pathname() fails', function () use ($repoRoot) {
		$src   = file_get_contents($repoRoot . '/settings.php');
		$start = strpos($src, "elseif (\$field_array['method'] == 'filepath')");
		$end   = strpos($src, "elseif (\$field_array['method'] == 'textbox_password')", $start);
		$block = substr($src, $start, $end - $start);

		expect($block)->toMatch('/!is_valid_pathname\([^{]+\)\s*\{[^}]*\$errors\[36\]\s*=\s*36;[^}]*\$continue\s*=\s*false;/s');
	});

	it('is_valid_pathname() accepts clean paths and rejects disallowed characters', function () {
		expect(is_valid_pathname('/var/log/cacti/cacti.log'))->toBeTrue();
		expect(is_valid_pathname('/usr/bin/rrdtool'))->toBeTrue();
		expect(is_valid_pathname('/tmp/bad;path'))->toBeFalse();
		expect(is_valid_pathname('/tmp/bad path'))->toBeFalse();
	});
});
