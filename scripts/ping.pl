#!/usr/bin/perl 

$ping = `ping -c 1 $ARGV[0] -w 1 | grep icmp_seq`; 
$ping =~ s/(.*time=)(.*) (ms|usec)//; 

print $2; 
