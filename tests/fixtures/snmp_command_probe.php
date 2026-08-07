#!/usr/bin/env php
<?php

$mode = getenv('CACTI_SNMP_PROBE_MODE') ?: 'get';

if ($mode === 'timeout') {
	print 'Timeout: No Response';
	exit(1);
}

if ($mode === 'error') {
	print 'probe failure';
	exit(2);
}

if ($mode === 'tooBig') {
	print 'Error in packet: (tooBig)';
	exit(1);
}

if ($mode === 'walk') {
	print ".1.3.6.1.2.1.1.1.0 = STRING: coverage agent\n";
	print " continuation\n";
	print "invalid-oid = STRING: rejected\n";
	print ".1.3.6.1.2.1.1.2.0 = STRING: End of MIB\n";
	exit(0);
}

print 'STRING: "coverage value"';
