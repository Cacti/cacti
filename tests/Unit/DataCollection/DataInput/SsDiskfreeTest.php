<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * scripts/ss_diskfree.php, the PHP port of diskfree.pl and diskfree.sh.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_diskfree.php';

const SS_DISKFREE_DF = "Filesystem     1024-blocks      Used Available Capacity Mounted on\n"
	. "/dev/sda1         20157744  18553884    579860      97% /\n"
	. "tmpfs               816344      1308    815036       1% /run\n"
	. "proc                     0         0         0        - /proc\n"
	. "/dev/sdb1        103081248  52428800  45403568      54% /srv/data\n";

test('one filesystem is reported for a path', function () {
	expect(ss_diskfree_parse(SS_DISKFREE_DF, true))->toBe('megabytes:579860 percent:97');
});

test('every filesystem is reported back to back without a path', function () {
	expect(ss_diskfree_parse(SS_DISKFREE_DF, false))
		->toBe('megabytes:579860 percent:97megabytes:815036 percent:1megabytes:45403568 percent:54');
});

test('df output without a filesystem line yields nothing', function () {
	expect(ss_diskfree_parse("Filesystem 1024-blocks Used Available Capacity Mounted on\n", true))->toBe('')
		->and(ss_diskfree_parse('', false))->toBe('');
});

test('rejected paths return U instead of listing every filesystem', function (string $path) {
	expect(ss_diskfree_collect($path))->toBe(['megabytes:U percent:U', 1])
		->and(ss_diskfree($path))->toBe('megabytes:U percent:U');
})->with(['-h', '--total', '/ /etc', '/;id', '$(id)', '/tmp/a b']);

test('the root filesystem is reported on the running host', function () {
	[$output, $status] = ss_diskfree_collect('/');

	expect($status)->toBe(0)->and($output)->toMatch('/^megabytes:\d+ percent:\d+$/');
});

test('a path that does not exist returns U', function () {
	expect(ss_diskfree_collect('/nonexistent/cacti/path'))->toBe(['megabytes:U percent:U', 1]);
});
