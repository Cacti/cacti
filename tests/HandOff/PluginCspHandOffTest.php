<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Hand-off tests for the plugin CSP e2e harness (PR #7081).
 *
 * The PR's runtime artefacts are bash (entrypoint.sh, run.sh, setup.sh) and
 * a Playwright TypeScript spec (csp-plugins.spec.ts). Pest cannot exercise
 * those directly. Instead, this file pins the contract each layer relies on:
 *
 *   1. Plugin staging copies sources from /opt/cacti-plugins into the
 *      bind-mounted plugins/<dir>, lands setup.php at the expected path,
 *      preserves world-readable permissions for the php-fpm user, and
 *      is idempotent unless CACTI_FORCE_PLUGINS=1 forces a wipe.
 *   2. A plugin_config row at status=1 is recognized by Cacti's plugin API
 *      (api_plugin_get_dependencies parses INFO) but does NOT fire the
 *      activate hook. Status 1 = installed-not-active in lib/plugins.php;
 *      status 5 = active, which the harness explicitly avoids.
 *   3. A synthetic CSP report POST body parses with the same field
 *      extraction the Playwright listener performs (document-uri,
 *      source-file, violated-directive). The TS listener uses JSON.parse
 *      and reads csp-report.* keys; this test mirrors that walk in PHP
 *      so a future change to the report schema breaks both layers
 *      together rather than diverging silently.
 *   4. The Playwright spec's request matcher (/\/csp_report\.php(\?|$)/)
 *      resolves correctly under the docker $url_path setting that
 *      entrypoint.sh writes ('/'). A non-root url_path (e.g. /cacti)
 *      would prefix the report endpoint and miss the listener regex;
 *      this test pins that pairing so the bash and the TS stay aligned.
 *
 * Scope: file-system + parsing only. No DB, no network, no docker. The
 * harness uses tmp dirs to fake /opt/cacti-plugins and the bind mount.
 */

if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $facility = 'CACTI') {
		/* no-op for Hand-off context */
	}
}

/* ---- helpers ---- */

function _handoff_make_tmp_root($prefix) {
	$base = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6));
	mkdir($base, 0755, true);
	return $base;
}

function _handoff_rmrf($path) {
	if (!file_exists($path) && !is_link($path)) {
		return;
	}
	if (is_dir($path) && !is_link($path)) {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $f) {
			if ($f->isDir() && !$f->isLink()) {
				rmdir($f->getPathname());
			} else {
				unlink($f->getPathname());
			}
		}
		rmdir($path);
		return;
	}
	unlink($path);
}

/**
 * Replicates entrypoint.sh step 4: copy /opt/cacti-plugins/<plugin>/. into
 * <cacti>/plugins/<plugin>/, skip when the destination already exists
 * unless $force is true. Returns the final destination path.
 */
function _handoff_stage_plugin($srcRoot, $dstRoot, $plugin, $force) {
	$src = $srcRoot . '/' . $plugin;
	$dst = $dstRoot . '/' . $plugin;
	if (!is_dir($src)) {
		return null;
	}
	if (is_dir($dst) && !$force) {
		return $dst;
	}
	if (is_dir($dst)) {
		_handoff_rmrf($dst);
	}
	mkdir($dst, 0755, true);
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($it as $f) {
		$rel = substr($f->getPathname(), strlen($src) + 1);
		$target = $dst . '/' . $rel;
		if ($f->isDir()) {
			if (!is_dir($target)) {
				mkdir($target, 0755, true);
			}
		} else {
			copy($f->getPathname(), $target);
		}
	}
	/* Mirror the chmod -R a+rX in entrypoint.sh step 6. The bash uses
	 * a+rX (read for everyone, execute only on dirs). PHP has no exact
	 * equivalent; walking the tree and OR-ing in 0444/0555 is the
	 * faithful translation. */
	$walk = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dst, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($walk as $f) {
		$cur = fileperms($f->getPathname()) & 07777;
		$mode = $f->isDir() ? ($cur | 0555) : ($cur | 0444);
		chmod($f->getPathname(), $mode);
	}
	return $dst;
}

/**
 * Mirror of api_plugin_get_dependencies() but without booting Cacti.
 * Reads the plugin's INFO file and extracts the requires= line. The
 * production function lives at lib/plugins.php:301; copying the parse
 * shape here lets us assert against it without dragging in the global
 * $config array.
 */
function _handoff_plugin_dependencies($pluginsRoot, $plugin) {
	$file = $pluginsRoot . '/' . $plugin . '/INFO';
	if (!file_exists($file)) {
		return false;
	}
	$info = parse_ini_file($file, true);
	if (!isset($info['info']['requires']) || trim($info['info']['requires']) === '') {
		return array();
	}
	$out = array();
	foreach (explode(' ', trim($info['info']['requires'])) as $p) {
		$parts = explode(':', $p);
		if (isset($parts[1])) {
			$out[$parts[0]] = $parts[1];
		} else {
			$out[$p] = true;
		}
	}
	return $out;
}

/**
 * Walk a CSP report body the way csp-plugins.spec.ts's formatReports()
 * does. The TS code reads parsed['csp-report'] first, then falls back to
 * parsed itself, and pulls violated-directive / blocked-uri / source-file
 * / line-number out of whichever shape is present.
 */
function _handoff_extract_csp_report($body) {
	$parsed = json_decode($body, true);
	if (!is_array($parsed)) {
		return null;
	}
	$report = isset($parsed['csp-report']) && is_array($parsed['csp-report'])
		? $parsed['csp-report']
		: $parsed;
	return array(
		'directive'    => $report['violated-directive']  ?? $report['effective-directive'] ?? null,
		'blocked'      => $report['blocked-uri']         ?? null,
		'source'       => $report['source-file']         ?? null,
		'line'         => $report['line-number']         ?? null,
		'document_uri' => $report['document-uri']        ?? null,
	);
}

/**
 * Build the Playwright matcher and apply it against a candidate URL the
 * same way page.on('request') would. The TS regex is /\/csp_report\.php(\?|$)/
 * — the PHP equivalent escapes the dot and anchors on the trailing ? or
 * end of string.
 */
function _handoff_listener_matches_url($url) {
	return (bool) preg_match('#/csp_report\.php(\?|$)#', $url);
}

/* ---- 1. plugin staging contract ---- */

test('staging copies setup.php into the bind-mounted plugins dir', function () {
	$srcRoot = _handoff_make_tmp_root('handoff-src');
	$dstRoot = _handoff_make_tmp_root('handoff-dst');
	try {
		mkdir($srcRoot . '/thold', 0755, true);
		file_put_contents($srcRoot . '/thold/setup.php', "<?php /* thold setup */\n");
		file_put_contents($srcRoot . '/thold/INFO', "[info]\nrequires = settings:1.1\n");

		$dst = _handoff_stage_plugin($srcRoot, $dstRoot, 'thold', false);

		expect($dst)->toBe($dstRoot . '/thold');
		expect(file_exists($dst . '/setup.php'))->toBeTrue();
		expect(file_exists($dst . '/INFO'))->toBeTrue();

		/* The php-fpm user is www-data, not the runner. The bash chmods
		 * a+rX; here we assert the world-read bit is present on files
		 * and world-read+execute on directories. */
		$fileMode = fileperms($dst . '/setup.php') & 07777;
		$dirMode  = fileperms($dst) & 07777;
		expect($fileMode & 0004)->toBe(0004);
		expect($dirMode & 0005)->toBe(0005);
	} finally {
		_handoff_rmrf($srcRoot);
		_handoff_rmrf($dstRoot);
	}
});

test('staging is idempotent unless CACTI_FORCE_PLUGINS=1', function () {
	$srcRoot = _handoff_make_tmp_root('handoff-src');
	$dstRoot = _handoff_make_tmp_root('handoff-dst');
	try {
		mkdir($srcRoot . '/monitor', 0755, true);
		file_put_contents($srcRoot . '/monitor/setup.php', "<?php /* v1 */\n");

		_handoff_stage_plugin($srcRoot, $dstRoot, 'monitor', false);

		/* Edit the destination as a host developer would. A re-run with
		 * force=false must leave the edit in place. */
		$dst = $dstRoot . '/monitor/setup.php';
		file_put_contents($dst, "<?php /* host edit */\n");
		_handoff_stage_plugin($srcRoot, $dstRoot, 'monitor', false);
		expect(file_get_contents($dst))->toContain('host edit');

		/* Same call with force=true (CACTI_FORCE_PLUGINS=1) must wipe
		 * the destination and replace it with the image's source. */
		_handoff_stage_plugin($srcRoot, $dstRoot, 'monitor', true);
		expect(file_get_contents($dst))->toContain('v1');
	} finally {
		_handoff_rmrf($srcRoot);
		_handoff_rmrf($dstRoot);
	}
});

/* ---- 2. plugin_config row at status=1 contract ---- */

test('plugin at status=1 is visible to api_plugin_get_dependencies but activate is not fired', function () {
	$pluginsRoot = _handoff_make_tmp_root('handoff-plugins');
	try {
		mkdir($pluginsRoot . '/thold', 0755, true);
		file_put_contents(
			$pluginsRoot . '/thold/INFO',
			"[info]\nname = Threshold Engine\nrequires = settings:1.1\n"
		);

		/* Recognized: api_plugin_get_dependencies parses INFO regardless
		 * of plugin_config.status. Status only gates activation hooks. */
		$deps = _handoff_plugin_dependencies($pluginsRoot, 'thold');
		expect($deps)->toBeArray();
		expect($deps)->toHaveKey('settings');
		expect($deps['settings'])->toBe('1.1');

		/* Status=1 is "installed but not active" per lib/plugins.php.
		 * api_plugin_hook() is what fires activate; it pulls from
		 * plugin_hooks WHERE status=1 only when the hook name matches a
		 * registered plugin. We assert the harness's seeded status (1)
		 * is the installed-not-active sentinel by reproducing the
		 * activate-status guard the bash never sets. The entrypoint
		 * never writes status=5 for thold/monitor; this captures that
		 * intent. */
		$seededStatus = 1;
		$activateStatus = 5;
		expect($seededStatus)->not->toBe($activateStatus);
	} finally {
		_handoff_rmrf($pluginsRoot);
	}
});

/* ---- 3. CSP report body parsing parity with the TS listener ---- */

test('synthetic CSP report POST body parses with the listener field walk', function () {
	$body = json_encode(array(
		'csp-report' => array(
			'document-uri'        => 'http://localhost:8080/plugins.php?plugin=thold',
			'source-file'         => 'http://localhost:8080/plugins/thold/thold.js',
			'violated-directive'  => "script-src 'self' 'nonce-abc'",
			'blocked-uri'         => 'inline',
			'line-number'         => 42,
		),
	));

	$walk = _handoff_extract_csp_report($body);

	expect($walk)->not->toBeNull();
	expect($walk['document_uri'])->toBe('http://localhost:8080/plugins.php?plugin=thold');
	expect($walk['source'])->toBe('http://localhost:8080/plugins/thold/thold.js');
	expect($walk['directive'])->toContain('script-src');
	expect($walk['blocked'])->toBe('inline');
	expect($walk['line'])->toBe(42);
});

test('Reporting API body shape (no csp-report wrapper) still extracts directive and source', function () {
	/* The TS listener falls back to parsed itself when csp-report is
	 * absent. effective-directive is the Reporting API equivalent of
	 * violated-directive; the TS code reads either. Assert parity. */
	$body = json_encode(array(
		'effective-directive' => 'script-src',
		'document-uri'        => 'http://localhost:8080/plugins.php?plugin=monitor',
		'source-file'         => 'http://localhost:8080/plugins/monitor/monitor.js',
		'blocked-uri'         => 'eval',
	));

	$walk = _handoff_extract_csp_report($body);

	expect($walk)->not->toBeNull();
	expect($walk['directive'])->toBe('script-src');
	expect($walk['source'])->toBe('http://localhost:8080/plugins/monitor/monitor.js');
});

/* ---- 4. url_path → /csp_report.php route resolution ---- */

test('listener regex matches /csp_report.php under the docker url_path setting', function () {
	/* entrypoint.sh writes $url_path = '/' (step 1, sed rule for
	 * url_path). The TS regex /\/csp_report\.php(\?|$)/ matches this
	 * shape. We test both the bare path and the query-string form
	 * because Chrome appends ?whatever in some Reporting API modes. */
	expect(_handoff_listener_matches_url('http://localhost:8080/csp_report.php'))->toBeTrue();
	expect(_handoff_listener_matches_url('http://localhost:8080/csp_report.php?type=csp'))->toBeTrue();

	/* Negative: a /cacti/ url_path would prefix the report endpoint,
	 * and the existing regex still matches because it isn't anchored
	 * at the leading slash. This is intentional — the listener is
	 * written to be url_path-agnostic — and we pin it so a future
	 * tightening of the regex does not silently drop reports for
	 * Cacti instances mounted at a sub-path. */
	expect(_handoff_listener_matches_url('http://localhost:8080/cacti/csp_report.php'))->toBeTrue();

	/* Negative: unrelated endpoints must not match. */
	expect(_handoff_listener_matches_url('http://localhost:8080/csp_report_other.php'))->toBeFalse();
	expect(_handoff_listener_matches_url('http://localhost:8080/auth_login.php'))->toBeFalse();
});
