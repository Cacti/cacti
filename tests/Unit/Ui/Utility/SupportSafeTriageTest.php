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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/utility.php';

// support.php returns early under the test-bootstrap gate, so only its function
// declarations load here. That skips the auth + dispatch path a unit test cannot
// satisfy while leaving the triage helpers callable.
require_once dirname(__DIR__, 4) . '/support.php';

beforeEach(function () {
	global $_CACTI_REQUEST;

	// get_request_var() caches validated values in these; clear them so the
	// redact toggle from one test cannot leak into the next.
	$_CACTI_REQUEST = [];
	unset($_REQUEST['redact'], $_REQUEST['tab']);
});

test('support_redact returns an empty string unchanged', function () {
	expect(support_redact(''))->toBe('');
});

test('tech_env_status maps each status to its badge class and icon', function () {
	expect(tech_env_status(DB_STATUS_SUCCESS, 'Installed'))
		->toContain("class='deviceUp'")->toContain('fa-check-circle')->toContain('Installed');

	expect(tech_env_status(DB_STATUS_ERROR, 'Missing'))
		->toContain("class='deviceDown'")->toContain('fa-times-circle');

	// tech_env_status escapes its text argument; angle brackets must not survive raw.
	expect(tech_env_status(DB_STATUS_WARNING, '<x>'))
		->toContain("class='deviceRecovering'")->toContain('fa-exclamation-triangle')->not->toContain('<x>');
});

test('show_tech_environment renders every section without a database', function () {
	global $config;

	$config[OPTIONS_CLI]['path_php_binary'] = PHP_BINARY;
	$config[OPTIONS_CLI]['path_rrdtool']    = '/cacti-tests/missing-rrdtool';
	$config[OPTIONS_CLI]['path_snmpget']    = PHP_BINARY;

	ob_start();
	show_tech_environment();
	$html = ob_get_clean();

	expect($html)->toContain(__('Required PHP Extensions'))
		->and($html)->toContain(__('Required Binaries'))
		->and($html)->toContain(__('Writable Directories'));
});

test('show_tech_environment flags a directory that is not writable', function () {
	global $config;

	$config[OPTIONS_CLI]['path_php_binary'] = PHP_BINARY;
	$config[OPTIONS_CLI]['path_rrdtool']    = PHP_BINARY;
	$config[OPTIONS_CLI]['path_snmpget']    = PHP_BINARY;

	$dir  = CACTI_PATH_RESOURCE;
	$mode = fileperms($dir) & 0777;

	expect(chmod($dir, 0555))->toBeTrue();

	try {
		clearstatcache(true, $dir);

		ob_start();
		show_tech_environment();
		$html = ob_get_clean();

		expect($html)->toContain(__('Not writable'));
	} finally {
		chmod($dir, $mode);
		clearstatcache(true, $dir);
	}
})->skip(function_exists('posix_getuid') && posix_getuid() === 0, 'root bypasses directory write permissions');

function safe_triage_seed_summary(): void {
	global $config, $poller_options, $database_hostname;

	$poller_options    = [1 => 'cactid', 2 => 'spine'];
	$database_hostname = 'localhost';

	$config[OPTIONS_CLI]['rsa_fingerprint'] = 'abcdef0123456789feedface';
	$config[OPTIONS_CLI]['poller_interval'] = '60';
	$config[OPTIONS_CLI]['stats_poller']    = '';
}

function safe_triage_uname_cell(string $html): string {
	$found = preg_match('#' . preg_quote(__('PHP uname'), '#') . '</td>\s*<td>(.*?)</td>#s', $html, $m);

	expect($found)->toBe(1);

	return $m[1];
}

test('show_tech_summary masks and escapes an installed SNMP banner when redacting', function () {
	global $config;

	safe_triage_seed_summary();

	// A real executable makes the SNMP tool count as installed, so the escaped
	// and redacted output branch runs instead of the pre-built status span.
	$config[OPTIONS_CLI]['path_snmpget'] = PHP_BINARY;

	// An executable path_spine that emits output drives the spine poller branch.
	$config[OPTIONS_CLI]['path_spine']   = '/bin/echo';
	$config[OPTIONS_CLI]['poller_type']  = '2';

	$_REQUEST['redact'] = '1';

	ob_start();
	show_tech_summary();
	$html = ob_get_clean();

	expect($html)->toContain('abcdef01')
		->and($html)->not->toContain('abcdef0123456789feedface');
});

test('show_tech_summary shows the full fingerprint and raw SNMP status when not redacting', function () {
	global $config;

	safe_triage_seed_summary();

	// Missing SNMP tool exercises the not-installed status-span branch.
	$config[OPTIONS_CLI]['path_snmpget'] = '/cacti-tests/missing-snmpget';
	$config[OPTIONS_CLI]['poller_type']  = '1';

	ob_start();
	show_tech_summary();
	$html = ob_get_clean();

	expect($html)->toContain('abcdef0123456789feedface');
});

test('show_tech_summary escapes the PHP uname value when redacting', function () {
	global $config;

	safe_triage_seed_summary();

	$config[OPTIONS_CLI]['path_snmpget'] = PHP_BINARY;
	$config[OPTIONS_CLI]['poller_type']  = '1';

	$_REQUEST['redact'] = '1';

	ob_start();
	show_tech_summary();
	$html = ob_get_clean();

	// support_redact() substitutes the literal <host> for this node's name, so a
	// redacted uname always carries markup characters that must not reach the
	// browser raw.
	$redacted = support_redact(php_uname());

	expect($redacted)->toContain('<host>')
		->and(safe_triage_uname_cell($html))->toBe(html_escape($redacted));
});

test('show_tech_summary escapes the PHP uname value when not redacting', function () {
	global $config;

	safe_triage_seed_summary();

	$config[OPTIONS_CLI]['path_snmpget'] = PHP_BINARY;
	$config[OPTIONS_CLI]['poller_type']  = '1';

	ob_start();
	show_tech_summary();
	$html = ob_get_clean();

	expect(safe_triage_uname_cell($html))->toBe(html_escape(php_uname()));
});

test('support.php escapes every php_uname() value it prints', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/support.php');

	// The redact toggle only decides whether the value is masked first; both
	// branches still have to reach html_escape(). A host whose uname carries no
	// markup characters cannot show that from the rendered page, so pin it on
	// the statement instead. Deleting each html_escape() call along with its
	// balanced argument list leaves behind only the values that reach the page
	// raw, which keeps this tolerant of how the conditional is shaped.
	$found = preg_match_all('/print\s+[^;]*\bphp_uname\(\)[^;]*;/', $src, $m);

	expect($found)->toBeGreaterThan(0);

	foreach ($m[0] as $statement) {
		expect(preg_replace('/html_escape(\((?:[^()]++|(?1))*\))/', '', $statement))->not->toContain('php_uname(');
	}
});

test('show_tech_summary reports N/A when no RSA fingerprint is set', function () {
	global $config;

	safe_triage_seed_summary();

	$config[OPTIONS_CLI]['rsa_fingerprint'] = '';
	$config[OPTIONS_CLI]['path_snmpget']    = PHP_BINARY;
	$config[OPTIONS_CLI]['poller_type']     = '1';

	ob_start();
	show_tech_summary();
	$html = ob_get_clean();

	expect($html)->toContain(__('N/A'));
});

test('show_tech_log reports when the log is not available', function () {
	global $config;

	$config[OPTIONS_CLI]['path_cactilog'] = '';

	ob_start();
	show_tech_log();
	$html = ob_get_clean();

	expect($html)->toContain(__('Log not available.'));
});

test('show_tech_log reports when no severity lines are present', function () {
	global $config;

	$file = tempnam(sys_get_temp_dir(), 'triagelog');
	file_put_contents($file, "2026-01-01 INFO nothing interesting here\n");
	$config[OPTIONS_CLI]['path_cactilog'] = $file;

	try {
		ob_start();
		show_tech_log();
		$html = ob_get_clean();

		expect($html)->toContain(__('No recent WARN, ERROR or SECURITY log entries found.'));
	} finally {
		unlink($file);
	}
});

test('show_tech_log prints recent severity lines and redacts on request', function () {
	global $config;

	$file = tempnam(sys_get_temp_dir(), 'triagelog');
	file_put_contents($file, "2026-01-01 WARN failure on 192.168.5.5\n");
	$config[OPTIONS_CLI]['path_cactilog'] = $file;

	$_REQUEST['redact'] = '1';

	try {
		ob_start();
		show_tech_log();
		$html = ob_get_clean();

		expect($html)->toContain('WARN')
			->and($html)->toContain('&lt;ipv4&gt;')
			->and($html)->not->toContain('192.168.5.5');
	} finally {
		unset($_REQUEST['redact']);
		unlink($file);
	}
});

test('support_redact masks IPv4, IPv6 and FQDN but leaves version numbers', function () {
	$out = support_redact('host db01.corp.example at 192.168.10.5 and fe80:0:0:0:0:0:0:1 running 1.7.2');

	expect($out)->toContain('<ipv4>')
		->and($out)->toContain('<ipv6>')
		->and($out)->toContain('<host>')
		->and($out)->toContain('1.7.2')
		->and($out)->not->toContain('192.168.10.5')
		->and($out)->not->toContain('fe80:0:0:0:0:0:0:1')
		->and($out)->not->toContain('db01.corp.example');
});

test('support_redact masks home directory path segments', function () {
	expect(support_redact('/home/alice/scripts'))->toContain('/home/<redacted>')
		->and(support_redact('/Users/bob/rra'))->toContain('/home/<redacted>');
});

test('support_redact masks this hosts own node name', function () {
	$node = php_uname('n');

	// Only exercised when the running host has a usable node name.
	if ($node == '' || strlen($node) <= 1) {
		expect(true)->toBeTrue();

		return;
	}

	expect(support_redact("logged in on $node now"))->toContain('<host>')
		->and(support_redact("logged in on $node now"))->not->toContain($node);
});

test('support_tail_severity returns nothing for an empty file', function () {
	$file = tempnam(sys_get_temp_dir(), 'triagelog');

	try {
		expect(support_tail_severity($file, 100, 4096))->toBe([]);
	} finally {
		unlink($file);
	}
});

test('support_tail_severity returns only WARN, ERROR and SECURITY lines', function () {
	$file = tempnam(sys_get_temp_dir(), 'triagelog');

	file_put_contents($file, implode("\n", [
		'2026-01-01 INFO normal line',
		'2026-01-01 WARN something odd',
		'2026-01-01 DEBUG noise',
		'2026-01-01 ERROR broke',
		'2026-01-01 SECURITY denied',
		'',
	]));

	try {
		$lines = support_tail_severity($file, 100, 65536);

		expect($lines)->toHaveCount(3)
			->and($lines[0])->toContain('WARN')
			->and($lines[1])->toContain('ERROR')
			->and($lines[2])->toContain('SECURITY');
	} finally {
		unlink($file);
	}
});

test('support_tail_severity caps the byte window and drops the partial first line', function () {
	$file = tempnam(sys_get_temp_dir(), 'triagelog');

	// Pad past the cap so the seek lands mid-line; the sliced fragment must be
	// discarded and only the trailing matches returned.
	$pad = str_repeat("2026-01-01 INFO padding line to grow the file\n", 200);
	file_put_contents($file, $pad . "2026-01-01 WARN near the end\n2026-01-01 ERROR last one\n");

	try {
		$lines = support_tail_severity($file, 100, 512);

		expect($lines)->toContain('2026-01-01 WARN near the end')
			->and($lines)->toContain('2026-01-01 ERROR last one');
	} finally {
		unlink($file);
	}
});

test('support_tail_severity limits the number of returned lines', function () {
	$file = tempnam(sys_get_temp_dir(), 'triagelog');

	$many = '';
	for ($i = 0; $i < 20; $i++) {
		$many .= "2026-01-01 WARN entry $i\n";
	}
	file_put_contents($file, $many);

	try {
		$lines = support_tail_severity($file, 5, 65536);

		expect($lines)->toHaveCount(5)
			->and($lines[4])->toContain('entry 19');
	} finally {
		unlink($file);
	}
});

test('support_tail_severity returns nothing when the file cannot be opened', function () {
	$file = tempnam(sys_get_temp_dir(), 'triagelog');
	file_put_contents($file, "2026-01-01 WARN unreadable\n");
	chmod($file, 0000);

	try {
		clearstatcache(true, $file);
		expect(support_tail_severity($file, 100, 4096))->toBe([]);
	} finally {
		chmod($file, 0644);
		unlink($file);
	}
})->skip(function_exists('posix_getuid') && posix_getuid() === 0, 'root bypasses file read permissions');
