#!/usr/bin/perl 

open(PROCESS, "ping -c 1 $ARGV[0] | grep icmp_seq |");
$ping = <PROCESS>;
close(PROCESS);
$ping =~ m/(.*time=)(.*) (ms|usec)/; 

print $2; 
