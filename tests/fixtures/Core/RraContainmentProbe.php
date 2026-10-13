<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* Execute real filesystem checks without bootstrapping a database. */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 3) . '/lib/functions.php';

$directory = sys_get_temp_dir() . '/cacti-rra-' . bin2hex(random_bytes(12));
$rra       = $directory . '/rra';
$outside   = $directory . '/outside';
mkdir($directory, 0700);
mkdir($rra, 0700);
mkdir($outside, 0700);
mkdir($rra . '/existing', 0700);
file_put_contents($rra . '/existing/file.rrd', 'fixture');
$config = array('rra_path' => $rra);

try {
	if (!symlink($outside, $rra . '/pivot') ||
		!symlink($outside . '/missing', $rra . '/dangling') ||
		!symlink($outside . '/missing.rrd', $rra . '/dangling.rrd')) {
		throw new RuntimeException('Unable to create symlink fixtures');
	}

	$cases = array(
		'existing file'         => data_source_path_within_rra($rra . '/existing/file.rrd'),
		'missing leaf'          => data_source_path_within_rra($rra . '/existing/new.rrd'),
		'missing directories'   => data_source_path_within_rra($rra . '/new/nested/file.rrd'),
		'outside root'          => data_source_path_within_rra($outside . '/file.rrd'),
		'prefix collision'      => data_source_path_within_rra($rra . '-other/file.rrd'),
		'parent traversal'      => data_source_path_within_rra($rra . '/../outside/file.rrd'),
		'dot segment'           => data_source_path_within_rra($rra . '/./file.rrd'),
		'nul byte'              => data_source_path_within_rra($rra . "/file\0.rrd"),
		'existing symlink'      => data_source_path_within_rra($rra . '/pivot/file.rrd'),
		'dangling intermediate' => data_source_path_within_rra($rra . '/dangling/file.rrd'),
		'dangling leaf'         => data_source_path_within_rra($rra . '/dangling.rrd'),
	);
	mkdir($outside . '/missing', 0700);
	clearstatcache();
	$cases['activated symlink'] = data_source_path_within_rra($rra . '/dangling/file.rrd');
	$config['rra_path'] = $directory . '/absent';
	$cases['missing root'] = data_source_path_within_rra($config['rra_path'] . '/file.rrd');
	print json_encode($cases, JSON_THROW_ON_ERROR) . "\n";
} finally {
	foreach (array('pivot', 'dangling', 'dangling.rrd') as $link) {
		if (is_link($rra . '/' . $link)) {
			unlink($rra . '/' . $link);
		}
	}
	unlink($rra . '/existing/file.rrd');
	rmdir($rra . '/existing');
	if (is_dir($outside . '/missing')) {
		rmdir($outside . '/missing');
	}
	rmdir($outside);
	rmdir($rra);
	rmdir($directory);
}
