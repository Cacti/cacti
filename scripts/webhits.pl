#!/usr/bin/perl

if ($ARGV[0] eq "") {
	$log_path = "/var/log/httpd/access_log";
}else{
	$log_path = $ARGV[0];
}

open(PROCESS,"wc -l $log_path |");
$webhits = <PROCESS>;
close(PROCESS);
$webhits =~ s/[\s]*([0-9]+).*//;

print $1;
