#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

if ($ARGV[0] eq "") {
	$log_path = "/var/log/httpd/access_log";
}else{
	$log_path = $ARGV[0];
}

# GHSA-x5pw-cg7j-mj4r: run wc through a list-form open with no shell, so a
# crafted log path cannot start a second shell and undo the argument escaping
# Cacti already applied to this field.
open(PROCESS, '-|', 'wc', '-l', '--', $log_path);
$webhits = <PROCESS>;
close(PROCESS);
$webhits =~ s/[\s]*([0-9]+).*//;

print $1;
