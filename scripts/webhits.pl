#!/usr/bin/perl

if ($ARGV[0] eq "") {
	$log_path = "/var/log/httpd/access_log";
}else{
	$log_path = $ARGV[0];
}

$webhits = `wc -l $log_path`;
$webhits =~ s/.*\s(.*[0-9])//;

print $1;