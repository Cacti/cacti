<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * RRDfile maintenance removes or archives only files below the RRA directory,
 * and drops a queue entry it will not act on so the purge loop ends. The data
 * source debug check probes the same path the poller writes to.
 */

namespace CactiRrdMaintenance;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

function read_config_option($name, $force = false) { return $name === 'storage_location' ? 0 : ''; }
function maint_debug($message) {}
function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') { $GLOBALS['maint_logs'][] = $string; }
function cacti_log_safe_value($value) { return $value; }
function cacti_sizeof($array) { return is_array($array) ? count($array) : 0; }
function rrdclean_create_path($path) { return is_dir($path) || mkdir($path, 0777, true); }
function set_config_option($name, $value) {}
function db_fetch_cell($sql) { return count($GLOBALS['maint_queue']); }
function db_fetch_assoc($sql) {
	if (++$GLOBALS['maint_fetches'] > 5) {
		throw new \RuntimeException('the purge loop did not end');
	}

	return array_values($GLOBALS['maint_queue']);
}
function db_fetch_assoc_prepared($sql, $params = array()) { return array(); }
function db_execute_prepared($sql, $params = array()) {
	unset($GLOBALS['maint_queue'][$params[0]]);

	return true;
}

foreach (array('rrdclean_path_within_rra', 'remove_files', 'rrdfile_purge') as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/poller_maintenance.php', $function, __NAMESPACE__);
}

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/functions.php', 'cacti_path_is_within', __NAMESPACE__);

beforeEach(function () {
	global $config, $archived, $purged, $poller_start;

	$this->root = sys_get_temp_dir() . '/cacti-rra-' . bin2hex(random_bytes(6));
	mkdir($this->root . '/real_rra/12', 0777, true);
	mkdir($this->root . '/elsewhere');
	symlink($this->root . '/real_rra', $this->root . '/rra');

	foreach (array('real_rra/flat_ds_1.rrd', 'real_rra/12/345.rrd', 'real_rra/has space.rrd', 'elsewhere/keep.rrd') as $file) {
		touch($this->root . '/' . $file);
	}

	$config       = array('rra_path' => $this->root . '/rra', 'base_path' => $this->root);
	$archived     = $purged = 0;
	$poller_start = microtime(true);

	$GLOBALS['maint_logs']    = array();
	$GLOBALS['maint_fetches'] = 0;
	$GLOBALS['maint_queue']   = array();

	foreach (array('<path_rra>/flat_ds_1.rrd', '12/345.rrd', 'has space.rrd', '../elsewhere/keep.rrd', '12/../../elsewhere/keep.rrd') as $id => $name) {
		$GLOBALS['maint_queue'][$name] = array('id' => $id + 1, 'name' => $name, 'local_data_id' => 0, 'action' => '1');
	}
});

afterEach(function () {
	$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

	foreach ($files as $file) {
		$file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
	}

	rmdir($this->root);
});

test('the purge removes files below the RRA directory, leaves others alone and ends', function () {
	rrdfile_purge(false);

	expect(file_exists($this->root . '/real_rra/flat_ds_1.rrd'))->toBeFalse()
		->and(file_exists($this->root . '/real_rra/12/345.rrd'))->toBeFalse()
		->and(file_exists($this->root . '/real_rra/has space.rrd'))->toBeFalse()
		->and(file_exists($this->root . '/elsewhere/keep.rrd'))->toBeTrue()
		->and($GLOBALS['maint_queue'])->toBe(array())
		->and($GLOBALS['maint_logs'])->toHaveCount(3);
});

test('a directory link that leaves the RRA directory is not followed', function () {
	symlink($this->root . '/elsewhere', $this->root . '/real_rra/link');
	$GLOBALS['maint_queue'] = array('link/keep.rrd' => array('id' => 1, 'name' => 'link/keep.rrd', 'local_data_id' => 0, 'action' => '1'));

	rrdfile_purge(false);

	expect(file_exists($this->root . '/elsewhere/keep.rrd'))->toBeTrue()
		->and($GLOBALS['maint_queue'])->toBe(array());
});

test('the data source debug check probes the path the poller uses', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/dsdebug.php');

	expect($source)->toContain("\$real_path = get_data_source_path(\$c['datasource'], true);")
		->and($source)->not->toContain("str_replace('<path_rra>', \$config['rra_path'], \$dtd['data_source_path'])");
});
