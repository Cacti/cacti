<?php
declare(strict_types = 1);

// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/lib/functions.php';

$base = realpath(sys_get_temp_dir()) . '/cacti-queue-' . bin2hex(random_bytes(8));
$rra  = $base . '/rra';
mkdir($rra, 0700, true);
define('CACTI_PATH_BASE', $base);
define('CACTI_PATH_RRA', $rra);
cacti_fixture_source_function($root . '/poller_maintenance.php', 'rrdclean_queue_local_source', '');

try {
	file_put_contents($rra . '/valid.rrd', 'inside');
	file_put_contents($base . '/other.rrd', 'outside');
	symlink($base, $rra . '/link');
	$names   = ['valid.rrd', '<path_rra>/valid.rrd', '../other.rrd', '<path_cacti>/other.rrd', 'link/other.rrd', ['invalid']];
	$allowed = [];

	foreach ($names as $name) {
		$allowed[] = rrdclean_queue_local_source($name, $rra) !== null;
	}
	print json_encode($allowed, JSON_THROW_ON_ERROR) . "\n";
} finally {
	unlink($rra . '/link');
	unlink($rra . '/valid.rrd');
	unlink($base . '/other.rrd');
	rmdir($rra);
	rmdir($base);
}
