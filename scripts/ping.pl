#!/usr/bin/perl

open(PROCESS, "ping -c 1 $ARGV[0] | grep icmp_seq |");
$ping = <PROCESS>;
close(PROCESS);
$ping =~ m/(.*time=)(.*) (ms|usec)/;

if ($2 == "") {
	print "0";
}else{
	print $2;
}

