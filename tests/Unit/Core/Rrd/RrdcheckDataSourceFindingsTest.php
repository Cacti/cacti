<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 4) . '/lib/rrd.php';
require_once dirname(__DIR__, 4) . '/lib/rrdcheck.php';

$GLOBALS['config']['cacti_server_os'] = (DIRECTORY_SEPARATOR === '\\' ? 'win32' : 'unix');
$GLOBALS['config']['base_path']       = sys_get_temp_dir();
$GLOBALS['config']['config_options_array']['path_cactilog']  = tempnam(sys_get_temp_dir(), 'clog');
$GLOBALS['config']['config_options_array']['log_destination'] = '1';
$GLOBALS['config']['config_options_array']['log_verbosity']   = '1';
$GLOBALS['config']['rra_path']                                = sys_get_temp_dir() . '/cacti_rra_test';

if (!is_dir($GLOBALS['config']['rra_path'])) {
	mkdir($GLOBALS['config']['rra_path'], 0777, true);
}

test('rrdcheck_ds_findings reports an extra RRDfile data source with a DEL spec', function () {
	$findings = rrdcheck_ds_findings(array('in' => 'in'), array('in' => array(), 'legacy' => array()), array(), '/rra/x.rrd');

	expect($findings[0]['message'])->toContain('includes 2');

	$del = array_values(array_filter($findings, fn($f) => $f['spec'] === 'DEL:legacy'));
	expect($del)->toHaveCount(1);
	expect($del[0]['message'])->toContain('no longer requires');
});

test('rrdcheck_ds_findings reports a missing data source with a DS add spec', function () {
	$cacti_ds = array('out' => array('type' => 'COUNTER', 'minimal_heartbeat' => 600, 'min' => 0, 'max' => 'U'));
	$findings = rrdcheck_ds_findings(array('in' => 'in', 'out' => 'out'), array('in' => array()), $cacti_ds, '/rra/x.rrd');

	expect($findings[0]['message'])->toContain('only includes 1');

	$add = array_values(array_filter($findings, fn($f) => $f['spec'] === 'DS:out:COUNTER:600:0:U'));
	expect($add)->toHaveCount(1);
	expect($add[0]['message'])->toContain('missing from the RRDfile');
});

test('rrdcheck_ds_findings falls back to a plain message when no Cacti definition exists', function () {
	$findings = rrdcheck_ds_findings(array('out' => 'out'), array(), array(), '/rra/x.rrd');

	$miss = array_values(array_filter($findings, fn($f) => str_contains($f['message'], 'exists in the Database')));
	expect($miss)->toHaveCount(1);
	expect($miss[0]['spec'])->toBe('');
});

test('rrdcheck_ds_findings flags an empty data source name', function () {
	$findings = rrdcheck_ds_findings(array('' => ''), array(), array(), '/rra/x.rrd');

	$empty = array_values(array_filter($findings, fn($f) => str_contains($f['message'], 'is empty')));
	expect($empty)->toHaveCount(1);
	expect($empty[0]['spec'])->toBe('');
});

test('rrdcheck_ds_findings is quiet for matching names with equal counts', function () {
	$findings = rrdcheck_ds_findings(array('keep' => 'keep', 'a' => 'a'), array('keep' => array(), 'b' => array()), array(), '/rra/x.rrd');

	$counts = array_values(array_filter($findings, fn($f) => str_contains($f['message'], 'Graph requires')));
	expect($counts)->toHaveCount(0);

	$del = array_values(array_filter($findings, fn($f) => $f['spec'] === 'DEL:b'));
	expect($del)->toHaveCount(1);

	$miss = array_values(array_filter($findings, fn($f) => str_contains($f['message'], "'a' exists in the Database")));
	expect($miss)->toHaveCount(1);
});

test('rrdcheck_valid_tune_spec accepts well-formed DS and DEL specifications', function () {
	expect(rrdcheck_valid_tune_spec('DEL:traffic_in'))->toBeTrue();
	expect(rrdcheck_valid_tune_spec('DS:traffic_in:COUNTER:600:0:U'))->toBeTrue();
	expect(rrdcheck_valid_tune_spec('DS:x:GAUGE:300:-5:1.5'))->toBeTrue();
});

test('rrdcheck_valid_tune_spec rejects malformed or unsafe specifications', function () {
	expect(rrdcheck_valid_tune_spec('DEL:bad name'))->toBeFalse();
	expect(rrdcheck_valid_tune_spec('DEL:in; rm -rf /'))->toBeFalse();
	expect(rrdcheck_valid_tune_spec('DS:in:BOGUS:600:0:U'))->toBeFalse();
	expect(rrdcheck_valid_tune_spec('DS:toolongdatasourcename0:COUNTER:600:0:U'))->toBeFalse();
	expect(rrdcheck_valid_tune_spec('DS:in:COUNTER:x:0:U'))->toBeFalse();
});

test('rrdcheck_tune_rrdfile refuses a malformed tune specification', function () {
	$pipes  = array();
	$result = rrdcheck_tune_rrdfile($GLOBALS['config']['rra_path'] . '/x.rrd', 'DEL:in;rm', 1, false, $pipes);

	expect($result)->toContain('not valid');
});

test('rrdcheck_tune_rrdfile refuses a local RRDfile outside the RRA directory', function () {
	$pipes  = array();
	$path   = sys_get_temp_dir() . '/cacti_outside_' . uniqid() . '.rrd';
	$result = rrdcheck_tune_rrdfile($path, 'DEL:in', 1, false, $pipes);

	expect($result)->toContain('outside the RRA directory');
});

test('rrdcheck_tune_rrdfile skips a tune when the RRDfile is not writable', function () {
	$pipes  = array();
	$path   = $GLOBALS['config']['rra_path'] . '/missing_' . uniqid() . '/x.rrd';
	$result = rrdcheck_tune_rrdfile($path, 'DEL:in', 1, false, $pipes);

	expect($result)->toContain('not writable');
});

test('rrdcheck_tune_rrdfile runs the tune over the pipe and reports success', function () {
	$file = $GLOBALS['config']['rra_path'] . '/ok_' . uniqid() . '.rrd';
	touch($file);

	$stdin  = fopen('php://temp', 'r+');
	$stdout = fopen('php://temp', 'r+');

	fwrite($stdout, "OK\n");
	rewind($stdout);

	$pipes  = array($stdin, $stdout);
	$result = rrdcheck_tune_rrdfile($file, 'DEL:in', 1, false, $pipes);

	fclose($stdin);
	fclose($stdout);
	@unlink($file);

	expect($result)->toContain('run automatically');
});

test('rrdcheck_tune_rrdfile treats a missing OK acknowledgement as a failure', function () {
	$file = $GLOBALS['config']['rra_path'] . '/noack_' . uniqid() . '.rrd';
	touch($file);

	$stdin  = fopen('php://temp', 'r+');
	$stdout = fopen('php://temp', 'r+');

	$pipes  = array($stdin, $stdout);
	$result = rrdcheck_tune_rrdfile($file, 'DEL:in', 1, false, $pipes);

	fclose($stdin);
	fclose($stdout);
	@unlink($file);

	expect($result)->toContain('failed');
});

test('rrdcheck_tune_rrdfile reports a failing tune', function () {
	$file = $GLOBALS['config']['rra_path'] . '/fail_' . uniqid() . '.rrd';
	touch($file);

	$stdin  = fopen('php://temp', 'r+');
	$stdout = fopen('php://temp', 'r+');

	fwrite($stdout, "ERROR: boom\n");
	rewind($stdout);

	$pipes  = array($stdin, $stdout);
	$result = rrdcheck_tune_rrdfile($file, 'DEL:in', 1, false, $pipes);

	fclose($stdin);
	fclose($stdout);
	@unlink($file);

	expect($result)->toContain('failed');
});
